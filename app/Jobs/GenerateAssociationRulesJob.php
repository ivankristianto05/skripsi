<?php

namespace App\Jobs;

use App\Models\AssociationRule;
use App\Models\Itemset;
use App\Models\Produk;
use App\Models\Transaksi;
use App\Services\AprioriService;
use App\Http\Controllers\AprioriController;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GenerateAssociationRulesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected string $aprioriBatchId;
    protected float $minSupportThreshold;
    protected float $minConfidenceThreshold;
    protected ?string $targetProdukKode;
    protected array $produkKategoriMap;
    protected array $kategoriUrutan;
    
    // Set untuk mencegah duplikasi rules
    protected array $generatedRulesSet = [];
    
    // PERBAIKAN: Cache untuk mapping hash ke support count yang lebih robust
    protected array $hashToSupportMap = [];

    public function __construct(string $aprioriBatchId, float $minSupportThreshold, float $minConfidenceThreshold, ?string $targetProdukKode)
    {
        $this->aprioriBatchId = $aprioriBatchId;
        $this->minSupportThreshold = $minSupportThreshold;
        $this->minConfidenceThreshold = $minConfidenceThreshold;
        $this->targetProdukKode = $targetProdukKode;
    }

    public function handle()
    {
        Log::info("GenerateAssociationRulesJob (Job 3) started. Batch ID: {$this->aprioriBatchId}, MinSupport: {$this->minSupportThreshold}, MinConfidence: {$this->minConfidenceThreshold}, TargetProduk: " . ($this->targetProdukKode ?: 'GLOBAL'));

        $this->produkKategoriMap = Produk::pluck('kategori_produk', 'kode_produk')->toArray();
        $this->kategoriUrutan = AprioriService::getDynamicCategoryOrder($this->targetProdukKode, $this->produkKategoriMap);

        DB::beginTransaction();

        try {
            AssociationRule::where('apriori_batch_id', $this->aprioriBatchId)->delete();
            Log::info("GenerateAssociationRulesJob (Job 3): Cleared existing rules for Batch ID {$this->aprioriBatchId}");

            $frequentItemsetModels = Itemset::where('apriori_batch_id', $this->aprioriBatchId)
                ->whereNotNull('support_value')
                ->where('support_value', '>=', $this->minSupportThreshold)
                ->orderBy('item_count')
                ->get();

            if ($frequentItemsetModels->isEmpty()) {
                Log::warning("GenerateAssociationRulesJob (Job 3): No frequent itemsets found meeting MinSupport >= {$this->minSupportThreshold} for Batch ID {$this->aprioriBatchId}. No rules will be generated.");
                $this->updateGlobalStatusAfterCompletion("No frequent itemsets to process for rules.");
                DB::commit();
                return;
            }
            Log::info("GenerateAssociationRulesJob (Job 3): Found {$frequentItemsetModels->count()} frequent itemsets to process.");

            // PERBAIKAN: Buat comprehensive mapping untuk support count lookup
            $this->buildComprehensiveSupportMap();

            $totalTransactions = AprioriService::getTotalTransactions();
            if ($totalTransactions == 0) {
                Log::error("GenerateAssociationRulesJob (Job 3): Total transactions is zero. Lift calculation will result in 0 or be inaccurate.");
            }

            $generatedRulesData = [];
            
            // Counter untuk tracking
            $ruleAttempts = 0;
            $ruleGenerated = 0;
            $ruleDuplicates = 0;

            // Generate rules dari 2-itemsets
            $frequentItemsetModels->where('item_count', 2)->each(
                function (Itemset $itemsetL2) use (&$generatedRulesData, $totalTransactions, &$ruleAttempts, &$ruleGenerated, &$ruleDuplicates) {
                    $itemsL2 = $itemsetL2->items;
                    if (count($itemsL2) !== 2) return;

                    // Pastikan items sudah terurut konsisten
                    $sortedItems = AprioriService::sortItemsByCategory($itemsL2, $this->produkKategoriMap, $this->kategoriUrutan);
                    
                    Log::info("Processing 2-itemset: " . json_encode($itemsL2) . " -> sorted: " . json_encode($sortedItems));
                    
                    // Aturan {A} => {B}
                    $this->tryGenerateAndStoreRule([$sortedItems[0]], [$sortedItems[1]], $itemsetL2, $totalTransactions, $generatedRulesData, $ruleAttempts, $ruleGenerated, $ruleDuplicates);
                }
            );

            // Generate rules dari 3-itemsets (HANYA ANTECEDENT 1 ITEM)
            $frequentItemsetModels->where('item_count', 3)->each(
                function (Itemset $itemsetL3) use (&$generatedRulesData, $totalTransactions, &$ruleAttempts, &$ruleGenerated, &$ruleDuplicates) {
                    $itemsL3 = $itemsetL3->items;
                    if (count($itemsL3) !== 3) return;

                    // Pastikan items sudah terurut konsistent
                    $sortedItems = AprioriService::sortItemsByCategory($itemsL3, $this->produkKategoriMap, $this->kategoriUrutan);
                    
                    Log::info("Processing 3-itemset: " . json_encode($itemsL3) . " -> sorted: " . json_encode($sortedItems));

                    // Aturan {A} => {B, C}
                    $this->tryGenerateAndStoreRule([$sortedItems[0]], [$sortedItems[1], $sortedItems[2]], $itemsetL3, $totalTransactions, $generatedRulesData, $ruleAttempts, $ruleGenerated, $ruleDuplicates);
                }
            );

            Log::info("Rule generation summary: Attempts={$ruleAttempts}, Generated={$ruleGenerated}, Duplicates={$ruleDuplicates}");

            if (!empty($generatedRulesData)) {
                // Cek duplikasi di level array sebelum insert
                $uniqueRules = [];
                $arrayDuplicates = 0;
                
                foreach ($generatedRulesData as $rule) {
                    $ruleKey = $rule['antecedent'] . '=>' . $rule['consequent'];
                    if (!isset($uniqueRules[$ruleKey])) {
                        $uniqueRules[$ruleKey] = $rule;
                    } else {
                        $arrayDuplicates++;
                        Log::warning("Array level duplicate found: {$ruleKey}");
                    }
                }
                
                Log::info("Array deduplication: Original={" . count($generatedRulesData) . "}, Unique={" . count($uniqueRules) . "}, Duplicates={$arrayDuplicates}");
                
                $finalRulesData = array_values($uniqueRules);
                
                foreach (array_chunk($finalRulesData, 200) as $chunk) {
                    AssociationRule::insert($chunk);
                }
                Log::info("GenerateAssociationRulesJob (Job 3): Inserted " . count($finalRulesData) . " unique rules for Batch ID {$this->aprioriBatchId}.");
            } else {
                Log::info("GenerateAssociationRulesJob (Job 3): No rules met the minimum confidence for Batch ID {$this->aprioriBatchId}.");
            }

            DB::commit();
            $this->updateGlobalStatusAfterCompletion("All rules generated or no rules met criteria.");
            Log::info("GenerateAssociationRulesJob (Job 3) finished successfully. Batch ID: {$this->aprioriBatchId}.");

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("GenerateAssociationRulesJob (Job 3) FAILED. Batch ID: {$this->aprioriBatchId}. Error: " . $e->getMessage(), [
                'exception_trace' => $e->getTraceAsString()
            ]);
            $this->updateGlobalStatus(config('apriori_settings.global_statuses.failed_prefix', AprioriController::STATUS_GLOBAL_FAILED_PREFIX) . 'job3_exception', "Exception occurred.");
            throw $e;
        }
    }

    /**
     * PERBAIKAN: Buat comprehensive mapping untuk lookup support count yang lebih robust
     */
    private function buildComprehensiveSupportMap()
    {
        // Ambil SEMUA itemsets untuk support count map
        $allItemsetModels = Itemset::where('apriori_batch_id', $this->aprioriBatchId)
            ->whereNotNull('support_count')
            ->get();

        Log::info("Building comprehensive support map from " . $allItemsetModels->count() . " itemsets");

        foreach ($allItemsetModels as $itemset) {
            $items = $itemset->items;
            $supportCount = $itemset->support_count;
            
            // Pastikan items di-sort konsisten
            $sortedItems = AprioriService::sortItemsByCategory($items, $this->produkKategoriMap, $this->kategoriUrutan);
            
            // Hash utama (dengan pipe separator)
            $primaryHash = implode('|', $sortedItems);
            $this->hashToSupportMap[$primaryHash] = $supportCount;
            
            // TAMBAHAN: Hash alternatif untuk single item (tanpa pipe)
            if (count($sortedItems) == 1) {
                $this->hashToSupportMap[$sortedItems[0]] = $supportCount;
            }
            
            // TAMBAHAN: Hash dengan format JSON untuk kemudahan matching
            $jsonHash = json_encode($sortedItems);
            $this->hashToSupportMap[$jsonHash] = $supportCount;
            
            Log::debug("Support map entry: Primary='{$primaryHash}', JSON='{$jsonHash}', Support={$supportCount}");
        }
        
        Log::info("Comprehensive support map built with " . count($this->hashToSupportMap) . " entries");
        
        // Debug: Log beberapa contoh entries
        $counter = 0;
        foreach ($this->hashToSupportMap as $hash => $count) {
            if ($counter < 10) { // Hanya log 10 pertama untuk debug
                Log::debug("Support map example: Hash='{$hash}' => Count={$count}");
                $counter++;
            } else {
                break;
            }
        }
    }

    /**
     * PERBAIKAN: Helper untuk membuat hash yang konsisten dari itemset subset
     */
    private function generateSubsetHash(array $items): string
    {
        if (empty($items)) {
            return '';
        }
        
        // PASTIKAN pengurutan konsisten menggunakan AprioriService
        $sortedItems = AprioriService::sortItemsByCategory($items, $this->produkKategoriMap, $this->kategoriUrutan);
        return implode('|', $sortedItems);
    }

    /**
     * PERBAIKAN: Lookup support count dengan multiple fallback strategies
     */
    private function findSupportCount(array $items): ?int
    {
        if (empty($items)) {
            return null;
        }
        
        // Pastikan items terurut konsisten
        $sortedItems = AprioriService::sortItemsByCategory($items, $this->produkKategoriMap, $this->kategoriUrutan);
        
        // Strategy 1: Hash dengan pipe separator
        $pipeHash = implode('|', $sortedItems);
        if (isset($this->hashToSupportMap[$pipeHash])) {
            Log::debug("Found support count using pipe hash '{$pipeHash}': " . $this->hashToSupportMap[$pipeHash]);
            return $this->hashToSupportMap[$pipeHash];
        }
        
        // Strategy 2: Single item tanpa pipe
        if (count($sortedItems) == 1) {
            $singleHash = $sortedItems[0];
            if (isset($this->hashToSupportMap[$singleHash])) {
                Log::debug("Found support count using single hash '{$singleHash}': " . $this->hashToSupportMap[$singleHash]);
                return $this->hashToSupportMap[$singleHash];
            }
        }
        
        // Strategy 3: JSON format
        $jsonHash = json_encode($sortedItems);
        if (isset($this->hashToSupportMap[$jsonHash])) {
            Log::debug("Found support count using JSON hash '{$jsonHash}': " . $this->hashToSupportMap[$jsonHash]);
            return $this->hashToSupportMap[$jsonHash];
        }
        
        // Strategy 4: Pattern matching - cari semua hash yang mengandung items yang sama
        foreach ($this->hashToSupportMap as $hash => $count) {
            // Skip JSON format dalam pattern matching untuk menghindari konflik
            if (strpos($hash, '[') === 0) continue;
            
            $hashItems = explode('|', $hash);
            if (count($hashItems) == count($sortedItems) && 
                empty(array_diff($sortedItems, $hashItems)) && 
                empty(array_diff($hashItems, $sortedItems))) {
                Log::debug("Found support count using pattern matching '{$hash}': {$count}");
                return $count;
            }
        }
        
        Log::warning("Could not find support count for items: " . json_encode($sortedItems) . " (tried hash: '{$pipeHash}')");
        return null;
    }

    /**
     * PERBAIKAN: Buat unique rule identifier untuk mencegah duplikasi
     */
    private function createRuleIdentifier(array $antecedent, array $consequent): string
    {
        $sortedAntecedent = AprioriService::sortItemsByCategory($antecedent, $this->produkKategoriMap, $this->kategoriUrutan);
        $sortedConsequent = AprioriService::sortItemsByCategory($consequent, $this->produkKategoriMap, $this->kategoriUrutan);
        
        return json_encode($sortedAntecedent) . '=>' . json_encode($sortedConsequent);
    }

    /**
     * PERBAIKAN: Generate dan simpan aturan dengan pencegahan duplikasi dan lookup yang lebih robust
     */
    private function tryGenerateAndStoreRule(
        array $antecedent,
        array $consequent,
        Itemset $itemsetXY,
        // SupportCollection $supportCountMap, // Tidak lagi dilewatkan, gunakan $this->hashToSupportMap
        int $totalTransactions,
        array &$generatedRulesData,
        int &$ruleAttempts,
        int &$ruleGenerated,
        int &$ruleDuplicates
    ) {
        $ruleAttempts++;

        $sortedAntecedent = AprioriService::sortItemsByCategory($antecedent, $this->produkKategoriMap, $this->kategoriUrutan);
        $sortedConsequent = AprioriService::sortItemsByCategory($consequent, $this->produkKategoriMap, $this->kategoriUrutan);
        
        $ruleIdentifier = $this->createRuleIdentifier($sortedAntecedent, $sortedConsequent); // createRuleIdentifier menggunakan sorted
        
        if (isset($this->generatedRulesSet[$ruleIdentifier])) {
            $ruleDuplicates++;
            // Log::info("DUPLICATE RULE DETECTED - Skipping: {$ruleIdentifier}");
            return;
        }
        // Tandai aturan ini sebagai sedang diproses agar tidak dibuat lagi oleh panggilan lain dalam job ini
        $this->generatedRulesSet[$ruleIdentifier] = true;


        // === TAMBAHKAN FILTER TARGET DI SINI ===
        if ($this->targetProdukKode) {
            // Cek apakah produk target ada di antecedent ATAU consequent
            // Kita menggunakan $sortedAntecedent dan $sortedConsequent karena itu yang akan disimpan dan digunakan untuk hash
            $targetInAntecedent = in_array($this->targetProdukKode, $sortedAntecedent);
            $targetInConsequent = in_array($this->targetProdukKode, $sortedConsequent);

            if (!$targetInAntecedent && !$targetInConsequent) {
                Log::debug("GenerateRule: SKIPPING rule " . json_encode($sortedAntecedent) . " => " . json_encode($sortedConsequent) . " because target produk '{$this->targetProdukKode}' is not involved. Batch: {$this->aprioriBatchId}.");
                // Hapus dari set agar tidak dihitung duplikat jika ada variasi lain yang valid
                unset($this->generatedRulesSet[$ruleIdentifier]);
                return; // Lewati aturan ini karena tidak melibatkan produk target
            }
        }
        // === AKHIR FILTER TARGET ===


        // Generate hash untuk lookup support menggunakan item yang sudah di-sort
        $antecedentHash = implode('|', $sortedAntecedent);
        $consequentHash = implode('|', $sortedConsequent);

        // Log::debug("Trying to generate rule: " . json_encode($sortedAntecedent) . " => " . json_encode($sortedConsequent));
        // Log::debug("Rule identifier: {$ruleIdentifier}");
        // Log::debug("Antecedent hash: {$antecedentHash}, Consequent hash: {$consequentHash}");

        $supportCountX = $this->findSupportCount($sortedAntecedent); // Gunakan $this->hashToSupportMap
        if ($supportCountX === null || $supportCountX == 0) {
            Log::debug("GenerateRule: Support count for antecedent " . json_encode($sortedAntecedent) . " (Hash: {$antecedentHash}) not found or zero in map. Skipping rule.");
            unset($this->generatedRulesSet[$ruleIdentifier]); // Hapus dari set karena rule tidak jadi dibuat
            return;
        }

        $supportCountXY = $itemsetXY->support_count;
        $confidence = $supportCountXY / $supportCountX;

        // Log::debug("Confidence calculation: Support(X∪Y)={$supportCountXY} / Support(X)={$supportCountX} = {$confidence}");

        if ($confidence >= $this->minConfidenceThreshold) {
            $lift = 0;
            $supportCountY = $this->findSupportCount($sortedConsequent); // Gunakan $this->hashToSupportMap

            if ($supportCountY !== null && $supportCountY > 0 && $totalTransactions > 0) {
                $probabilityY = $supportCountY / $totalTransactions;
                if ($probabilityY > 0) {
                    $lift = $confidence / $probabilityY;
                }
            } else {
                 Log::debug("LIFT=0 for rule {$ruleIdentifier} because SuppCountY for consequent (Hash: {$consequentHash}) is " . ($supportCountY === null ? 'NOT FOUND in map' : "zero or less ({$supportCountY})") . " or totalTransactions is zero.");
            }

            $generatedRulesData[] = [
                'apriori_batch_id' => $this->aprioriBatchId,
                'antecedent' => json_encode($sortedAntecedent),
                'consequent' => json_encode($sortedConsequent),
                'confidence' => round($confidence, 4),
                'lift' => round($lift, 4),
                'support_value_rule' => round($itemsetXY->support_value, 4),
                'created_at' => now(),
                'updated_at' => now(),
            ];
            $ruleGenerated++;
            // Log::info("RULE GENERATED: ...");
        } else {
             unset($this->generatedRulesSet[$ruleIdentifier]); // Hapus dari set karena rule tidak jadi dibuat (tidak lolos confidence)
        }
    }

    private function updateGlobalStatusAfterCompletion(string $reason = "")
    {
        $this->updateGlobalStatus(config('apriori_settings.global_statuses.completed', 'completed'), $reason);
    }

    private function updateGlobalStatus(string $statusConfigKey, string $reason = "")
    {
        try {
            $globalBatchIdKey = config('apriori_settings.cache_keys.global_active_batch_id', AprioriController::CACHE_KEY_GLOBAL_ACTIVE_BATCH_ID);
            $cachedGlobalBatchId = Cache::get($globalBatchIdKey);

            if ($cachedGlobalBatchId === $this->aprioriBatchId) {
                $statusKeyPrefix = config('apriori_settings.cache_keys.global_batch_status_prefix', AprioriController::CACHE_KEY_GLOBAL_BATCH_STATUS_PREFIX);
                $statusValue = config("apriori_settings.global_statuses.{$statusConfigKey}", $statusConfigKey);

                Cache::put($statusKeyPrefix . $this->aprioriBatchId, $statusValue, now()->addDays(7));
                Log::info("GenerateAssociationRulesJob (Job 3): Global status updated for Batch ID {$this->aprioriBatchId} to {$statusValue}. Reason: {$reason}");
            }
        } catch (\Exception $e) {
            Log::error("GenerateAssociationRulesJob (Job 3): Failed to update global status for Batch ID {$this->aprioriBatchId}. Error: " . $e->getMessage());
        }
    }
}