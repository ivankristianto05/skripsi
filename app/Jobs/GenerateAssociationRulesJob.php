<?php

namespace App\Jobs;

use App\Models\AssociationRule;
use App\Models\Itemset;
use App\Models\Produk;
use App\Models\Transaksi;
use App\Services\AprioriService;
// Ganti penggunaan konstanta AprioriController dengan config() jika sudah dipindahkan
use App\Http\Controllers\AprioriController; // Digunakan untuk fallback konstanta cache
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

            // PERBAIKAN: Ambil SEMUA itemsets (termasuk yang tidak frequent) untuk support count map
            // Ini diperlukan karena kita butuh support count dari individual items untuk menghitung lift
            $allItemsetModels = Itemset::where('apriori_batch_id', $this->aprioriBatchId)
                ->whereNotNull('support_count')
                ->get();

            // Kunci: items_hash, Nilai: support_count (untuk rumus confidence dan lift)
            $supportCountMap = $allItemsetModels->pluck('support_count', 'items_hash');
            
            // Debug: Log beberapa entries dari support count map
            Log::debug("GenerateAssociationRulesJob (Job 3): Support count map contains " . $supportCountMap->count() . " entries.");
            Log::debug("GenerateAssociationRulesJob (Job 3): First 5 support count entries: " . json_encode($supportCountMap->take(5)->toArray()));

            $totalTransactions = AprioriService::getTotalTransactions();
            if ($totalTransactions == 0) {
                Log::error("GenerateAssociationRulesJob (Job 3): Total transactions is zero. Lift calculation will result in 0 or be inaccurate.");
                // Proses tetap lanjut, lift akan 0
            }

            $generatedRulesData = [];

            // Generate rules dari 2-itemsets
            $frequentItemsetModels->where('item_count', 2)->each(
                function (Itemset $itemsetL2) use (&$generatedRulesData, $supportCountMap, $totalTransactions) {
                    $itemsL2 = $itemsetL2->items; // items sudah array
                    if (count($itemsL2) !== 2) return;

                    // Aturan {A} => {B}
                    $this->tryGenerateAndStoreRule([$itemsL2[0]], [$itemsL2[1]], $itemsetL2, $supportCountMap, $totalTransactions, $generatedRulesData);
                    // Aturan {B} => {A}
                    $this->tryGenerateAndStoreRule([$itemsL2[1]], [$itemsL2[0]], $itemsetL2, $supportCountMap, $totalTransactions, $generatedRulesData);
                }
            );

            // Generate rules dari 3-itemsets (HANYA ANTECEDENT 1 ITEM)
            $frequentItemsetModels->where('item_count', 3)->each(
                function (Itemset $itemsetL3) use (&$generatedRulesData, $supportCountMap, $totalTransactions) {
                    $itemsL3 = $itemsetL3->items; // items sudah array
                    if (count($itemsL3) !== 3) return;

                    // Aturan {A} => {B, C}
                    $this->tryGenerateAndStoreRule([$itemsL3[0]], [$itemsL3[1], $itemsL3[2]], $itemsetL3, $supportCountMap, $totalTransactions, $generatedRulesData);
                    // Aturan {B} => {A, C}
                    $this->tryGenerateAndStoreRule([$itemsL3[1]], [$itemsL3[0], $itemsL3[2]], $itemsetL3, $supportCountMap, $totalTransactions, $generatedRulesData);
                    // Aturan {C} => {A, B}
                    $this->tryGenerateAndStoreRule([$itemsL3[2]], [$itemsL3[0], $itemsL3[1]], $itemsetL3, $supportCountMap, $totalTransactions, $generatedRulesData);
                }
            );

            if (!empty($generatedRulesData)) {
                foreach (array_chunk($generatedRulesData, 200) as $chunk) {
                    AssociationRule::insert($chunk);
                }
                Log::info("GenerateAssociationRulesJob (Job 3): Inserted " . count($generatedRulesData) . " rules for Batch ID {$this->aprioriBatchId}.");
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
     * Helper untuk membuat hash dari itemset subset (antecedent/consequent)
     * menggunakan metode pengurutan kategori yang konsisten.
     */
    private function generateSubsetHash(array $items): string
    {
        $sortedItems = AprioriService::sortItemsByCategory($items, $this->produkKategoriMap, $this->kategoriUrutan);
        return implode('|', $sortedItems);
    }

    /**
     * Generate dan simpan aturan jika memenuhi syarat.
     */
    private function tryGenerateAndStoreRule(
        array $antecedent, 
        array $consequent, 
        Itemset $itemsetXY, 
        SupportCollection $supportCountMap, 
        int $totalTransactions, 
        array &$generatedRulesData
    ) {
        // Generate hash untuk antecedent dan consequent
        $antecedentHash = $this->generateSubsetHash($antecedent);
        $consequentHash = $this->generateSubsetHash($consequent);
    
        // Debug logging
        Log::debug("Trying to generate rule: " . json_encode($antecedent) . " => " . json_encode($consequent));
        Log::debug("Antecedent hash: {$antecedentHash}, Consequent hash: {$consequentHash}");
    
        // Validasi support count untuk antecedent (X)
        if (!isset($supportCountMap[$antecedentHash])) {
            Log::debug("GenerateRule: Antecedent hash '{$antecedentHash}' (from " . json_encode($antecedent) . ") not found in support map for Batch {$this->aprioriBatchId}. ItemsetXY: {$itemsetXY->items_hash}. Skipping rule.");
            return;
        }
        
        $supportCountX = $supportCountMap[$antecedentHash];
    
        if ($supportCountX == 0) {
            Log::debug("GenerateRule: Support count for antecedent " . json_encode($antecedent) . " is zero. Batch: {$this->aprioriBatchId}. Skipping rule.");
            return;
        }
    
        // Hitung confidence: Confidence(X => Y) = Support(X ∪ Y) / Support(X)
        $supportCountXY = $itemsetXY->support_count;
        $confidence = $supportCountXY / $supportCountX;
    
        Log::debug("Confidence calculation: Support(X∪Y)={$supportCountXY} / Support(X)={$supportCountX} = {$confidence}");
    
        // Check apakah confidence memenuhi threshold
        if ($confidence >= $this->minConfidenceThreshold) {
            $lift = 0;
            $supportCountY = null;
            Log::debug("Lift calculation: Confidence({$confidence}) / Support(Y)({$probabilityY}) = {$lift}");

            // Cari support count untuk consequent (Y)
            if (isset($supportCountMap[$consequentHash])) {
                $supportCountY = $supportCountMap[$consequentHash];
                Log::debug("Found consequent support count using hash '{$consequentHash}': {$supportCountY}");
            } else {
                Log::debug("Consequent hash '{$consequentHash}' not found in support map");
                
                // Untuk single item consequent, coba alternatif hash (langsung kode produk)
                if (count($consequent) == 1) {
                    $alternativeHash = $consequent[0];
                    if (isset($supportCountMap[$alternativeHash])) {
                        $supportCountY = $supportCountMap[$alternativeHash];
                        Log::debug("Found consequent support count using alternative hash '{$alternativeHash}': {$supportCountY}");
                    } else {
                        Log::debug("Alternative hash '{$alternativeHash}' also not found in support map");
                    }
                }
            }
    
            // Hitung lift jika semua data tersedia
            if ($supportCountY !== null && $supportCountY > 0 && $totalTransactions > 0) {
                // Support(Y) = Count(Y) / Total Transactions
                $supportY = $supportCountY / $totalTransactions;
                
                if ($supportY > 0) {
                    // Lift = Confidence(X => Y) / Support(Y)
                    $lift = $confidence / $supportY;
                    
                    Log::debug("Lift calculation: Confidence({$confidence}) / Support(Y)({$supportY}) = {$lift}");
                    
                    // Alternatif perhitungan lift untuk verifikasi:
                    // $supportXY = $supportCountXY / $totalTransactions;
                    // $supportX = $supportCountX / $totalTransactions;
                    // $liftAlt = $supportXY / ($supportX * $supportY);
                    // Log::debug("Alternative lift calculation: Support(X∪Y)({$supportXY}) / (Support(X)({$supportX}) * Support(Y)({$supportY})) = {$liftAlt}");
                } else {
                    Log::debug("LIFT=0 because Support(Y) is zero. Consequent: " . json_encode($consequent));
                }
            } else {
                $reasons = [];
                if ($supportCountY === null) $reasons[] = "supportCountY is null";
                if ($supportCountY !== null && $supportCountY <= 0) $reasons[] = "supportCountY <= 0 ({$supportCountY})";
                if ($totalTransactions <= 0) $reasons[] = "totalTransactions <= 0 ({$totalTransactions})";
                
                Log::debug("LIFT=0 because: " . implode(', ', $reasons) . ". Consequent: " . json_encode($consequent) . " (Hash: {$consequentHash})");
            }
    
            // Siapkan data untuk penyimpanan
            // Urutkan antecedent dan consequent sesuai kategori untuk konsistensi
            $sortedAntecedentForStorage = AprioriService::sortItemsByCategory(
                $antecedent, 
                $this->produkKategoriMap, 
                $this->kategoriUrutan
            );
            $sortedConsequentForStorage = AprioriService::sortItemsByCategory(
                $consequent, 
                $this->produkKategoriMap, 
                $this->kategoriUrutan
            );
    
            // Tambahkan rule ke array data
            $generatedRulesData[] = [
                'apriori_batch_id' => $this->aprioriBatchId,
                'antecedent' => json_encode($sortedAntecedentForStorage),
                'consequent' => json_encode($sortedConsequentForStorage),
                'confidence' => round($confidence, 4),
                'lift' => round($lift, 4),
                'support_value_rule' => round($itemsetXY->support_value, 4),
                'created_at' => now(),
                'updated_at' => now(),
            ];
            
            Log::info("RULE GENERATED: " . json_encode($sortedAntecedentForStorage) . " => " . json_encode($sortedConsequentForStorage) . 
                     " | Confidence: " . round($confidence, 4) . 
                     ", Lift: " . round($lift, 4) . 
                     ", Support: " . round($itemsetXY->support_value, 4) .
                     " | Support counts - X: {$supportCountX}, Y: " . ($supportCountY ?? 'N/A') . ", XY: {$supportCountXY}");
        } else {
            Log::debug("Rule " . json_encode($antecedent) . " => " . json_encode($consequent) . 
                      " does not meet minimum confidence threshold. Confidence: {$confidence}, Required: {$this->minConfidenceThreshold}");
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
                // Ambil nilai status dari config berdasarkan identifiernya
                $statusValue = config("apriori_settings.global_statuses.{$statusConfigKey}", $statusConfigKey); // Fallback ke identifier jika tidak ada di config

                Cache::put($statusKeyPrefix . $this->aprioriBatchId, $statusValue, now()->addDays(7));
                Log::info("GenerateAssociationRulesJob (Job 3): Global status updated for Batch ID {$this->aprioriBatchId} to {$statusValue}. Reason: {$reason}");
            }
        } catch (\Exception $e) {
            Log::error("GenerateAssociationRulesJob (Job 3): Failed to update global status for Batch ID {$this->aprioriBatchId}. Error: " . $e->getMessage());
        }
    }
}