<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\Itemset;
use App\Models\Transaksi;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\AprioriController;
use App\Jobs\GenerateAssociationRulesJob;

class CalculateAprioriSupportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected string $aprioriBatchId;
    protected float $minSupportThreshold;
    protected float $minConfidenceThreshold; 
    protected ?string $targetProdukKode;

    public function __construct(string $aprioriBatchId, float $minSupportThreshold = 0.1, float $minConfidenceThreshold = 0.1, ?string $targetProdukKode = null)
    {
        $this->aprioriBatchId = $aprioriBatchId;
        $this->minSupportThreshold = $minSupportThreshold;
        $this->minConfidenceThreshold = $minConfidenceThreshold; 
        $this->targetProdukKode = $targetProdukKode;
    }

    public function handle()
    {
        Log::info("CalculateAprioriSupportJob (Job 2) started. Batch ID: {$this->aprioriBatchId}");
        $processingSuccess = false;

        try {
            $itemsets = Itemset::where('apriori_batch_id', $this->aprioriBatchId)
                                ->whereNull('support_value')
                                ->get();

            if ($itemsets->isEmpty()) {
                Log::warning("CalculateAprioriSupportJob (Job 2): No itemsets found requiring support calculation");
                
                $totalItemsetsInBatch = Itemset::where('apriori_batch_id', $this->aprioriBatchId)->count();
                $itemsetsWithSupportInBatch = Itemset::where('apriori_batch_id', $this->aprioriBatchId)->whereNotNull('support_value')->count();

                if ($totalItemsetsInBatch > 0 && $totalItemsetsInBatch === $itemsetsWithSupportInBatch) {
                    Log::info("CalculateAprioriSupportJob (Job 2): All itemsets already have support calculated");
                    $processingSuccess = true;
                } else if ($totalItemsetsInBatch === 0) {
                    Log::warning("CalculateAprioriSupportJob (Job 2): No itemsets exist for batch");
                    $this->updateGlobalStatus('job1b_completed_no_itemsets');
                    return;
                }
            } else {
                Log::info("Found {$itemsets->count()} itemsets to process for support calculation");

                $totalTransaksi = Transaksi::count();
                if ($totalTransaksi == 0) {
                    Log::warning("CalculateAprioriSupportJob (Job 2): No transactions found");
                    Itemset::where('apriori_batch_id', $this->aprioriBatchId)
                           ->whereNull('support_value')
                           ->update(['support_count' => 0, 'support_value' => 0]);
                    $processingSuccess = true;
                } else {
                    Log::info("Total transactions: {$totalTransaksi}");

                    if ($this->tryWithRelationships($itemsets, $totalTransaksi)) {
                        Log::info("Successfully processed itemset supports using relationships method");
                        $processingSuccess = true;
                    } else {
                        Log::info("Trying fallback method with raw queries");
                        if ($this->handleWithRawQueries($itemsets, $totalTransaksi)) {
                             Log::info("Successfully processed itemset supports using raw queries fallback");
                             $processingSuccess = true;
                        } else {
                            Log::error("CalculateAprioriSupportJob (Job 2): Both methods failed");
                        }
                    }
                }
            }

            if ($processingSuccess) {
                Log::info("CalculateAprioriSupportJob (Job 2) completed successfully");
                $this->updateGlobalStatus('job2b_completed_job3b_dispatched');

                GenerateAssociationRulesJob::dispatch(
                    $this->aprioriBatchId,
                    $this->minSupportThreshold,
                    $this->minConfidenceThreshold,
                    $this->targetProdukKode
                )->delay(now()->addSeconds(5));

                Log::info("CalculateAprioriSupportJob (Job 2): Dispatched GenerateAssociationRulesJob (Job 3)");
            } else {
                Log::error("CalculateAprioriSupportJob (Job 2): Did not dispatch Job 3 due to processing failure");
                if (!$itemsets->isEmpty() && $totalTransaksi > 0) {
                    $this->updateGlobalStatus('failed_job2b_processing');
                }
            }

        } catch (\Exception $e) {
            Log::error("CalculateAprioriSupportJob (Job 2) failed: " . $e->getMessage(), [
                'exception' => $e->getTraceAsString()
            ]);
            $this->updateGlobalStatus('failed_job2b_exception');
            throw $e;
        }
    }

    private function tryWithRelationships($itemsets, $totalTransaksi): bool
    {
        try {
            $relationshipNames = ['produkTransaksis', 'produks', 'detailTransaksi', 'detail_transaksi', 'details', 'transaksiDetails', 'produkDetails', 'items'];
            $validRelationship = null;

            foreach ($relationshipNames as $relationName) {
                if (method_exists(Transaksi::class, $relationName)) {
                    try {
                        $testTransaksiWithData = Transaksi::with($relationName)->has($relationName)->first();
                        if ($testTransaksiWithData && $testTransaksiWithData->{$relationName}) {
                            $validRelationship = $relationName;
                            Log::info("Found valid relationship: {$relationName}");
                            break;
                        }
                    } catch (\Exception $e) {
                        Log::debug("Relationship test for '{$relationName}' failed: " . $e->getMessage());
                        continue;
                    }
                }
            }

            if (!$validRelationship) {
                Log::info("No valid relationship found, will attempt fallback");
                return false;
            }

            $eagerLoads = [$validRelationship];
            $sampleRelatedModel = Transaksi::first()->{$validRelationship}()->getRelated();
            if ($sampleRelatedModel && method_exists($sampleRelatedModel, 'produk')) {
                $eagerLoads[] = $validRelationship . '.produk';
            }

            $transaksiList = Transaksi::with($eagerLoads)->get();

            if ($transaksiList->isEmpty()) {
                Log::warning("No transactions found with relationship '{$validRelationship}'");
                return false;
            }
            
            Log::info("Found {$transaksiList->count()} transactions using relationship '{$validRelationship}'");

            $updatedCount = 0;
            foreach ($itemsets as $itemset) {
                $items = $itemset->items;
                if (empty($items) || !is_array($items)) {
                    Log::warning("Invalid items format for itemset ID {$itemset->id}");
                    continue;
                }
                
                $supportCount = 0;
                foreach ($transaksiList as $transaksi) {
                    $produkInTransaksi = $this->extractProdukCodes($transaksi, $validRelationship);
                    if ($this->itemsetExistsInTransaction($items, $produkInTransaksi)) {
                        $supportCount++;
                    }
                }

                $supportValue = $totalTransaksi > 0 ? $supportCount / $totalTransaksi : 0;
                $itemset->update([
                    'support_count' => $supportCount,
                    'support_value' => round($supportValue, 4)
                ]);
                $updatedCount++;
                
                if ($supportValue >= $this->minSupportThreshold) {
                    Log::info("Frequent itemset: " . json_encode($items) . " (Support: {$supportValue})");
                }
            }
            
            Log::info("Updated {$updatedCount} itemsets using relationships method");
            return true;

        } catch (\Exception $e) {
            Log::error("tryWithRelationships method failed: " . $e->getMessage());
            return false;
        }
    }

    private function handleWithRawQueries($itemsets, $totalTransaksi): bool
    {
        Log::info("Using fallback method with raw queries for {$itemsets->count()} itemsets");
        try {
            $updatedCount = 0;
            foreach ($itemsets as $itemset) {
                $items = $itemset->items;
                if (empty($items) || !is_array($items)) {
                    Log::warning("Invalid items format for itemset ID {$itemset->id} (raw query)");
                    continue;
                }

                $supportCount = $this->calculateSupportWithRawQuery($items);
                $supportValue = $totalTransaksi > 0 ? $supportCount / $totalTransaksi : 0;

                $itemset->update([
                    'support_count' => $supportCount,
                    'support_value' => round($supportValue, 4)
                ]);
                $updatedCount++;
                
                if ($supportValue >= $this->minSupportThreshold) {
                    Log::info("Frequent itemset (raw query): " . json_encode($items) . " (Support: {$supportValue})");
                }
            }
            Log::info("Fallback method completed. Updated {$updatedCount} itemsets");
            return true;
        } catch (\Exception $e) {
            Log::error("handleWithRawQueries method failed: " . $e->getMessage());
            return false;
        }
    }

    private function extractProdukCodes($transaksi, $relationshipName)
    {
        $produkCodes = [];
        try {
            $relationData = $transaksi->{$relationshipName};
            if (!$relationData || ($relationData instanceof \Illuminate\Database\Eloquent\Collection && $relationData->isEmpty())) {
                return $produkCodes;
            }
            
            foreach ($relationData as $item) {
                if (isset($item->produk) && isset($item->produk->kode_produk)) {
                    $produkCodes[] = $item->produk->kode_produk;
                } elseif (isset($item->kode_produk)) {
                    $produkCodes[] = $item->kode_produk;
                } elseif (isset($item->produk_kode)) {
                    $produkCodes[] = $item->produk_kode;
                }
            }
            
            if (empty($produkCodes) && method_exists($relationData, 'pluck')) {
                $possibleFields = ['produk.kode_produk', 'kode_produk', 'produk_kode'];
                foreach ($possibleFields as $field) {
                    try {
                        $codes = $relationData->pluck($field)->filter()->toArray();
                        if (!empty($codes)) { 
                            $produkCodes = $codes; 
                            break; 
                        }
                    } catch (\Exception $e) { 
                        continue; 
                    }
                }
            }
        } catch (\Exception $e) {
            Log::debug("Error extracting produk codes for transaction {$transaksi->id}: " . $e->getMessage());
        }
        return array_unique($produkCodes);
    }

    private function itemsetExistsInTransaction($itemset, $produkInTransaksi)
    {
        if (empty($itemset) || empty($produkInTransaksi)) return false;
        foreach ($itemset as $item) {
            if (!in_array($item, $produkInTransaksi)) return false;
        }
        return true;
    }
    
    private function calculateSupportWithRawQuery($items)
    {
        // Struktur 1: transaksis -> produk_transaksis -> produks
        try {
            $placeholders = str_repeat('?,', count($items) - 1) . '?';
            $query = "SELECT DISTINCT t.id FROM transaksis t 
                      INNER JOIN produk_transaksis pt ON t.id = pt.transaksi_id 
                      INNER JOIN produks p ON pt.produk_id = p.id 
                      WHERE p.kode_produk IN ({$placeholders}) 
                      GROUP BY t.id 
                      HAVING COUNT(DISTINCT p.kode_produk) = ?";
            $params = array_merge($items, [count($items)]);
            $result = DB::select($query, $params);
            if (isset($result)) return count($result);
        } catch (\Exception $e) { 
            Log::debug("Raw query structure 1 failed: " . $e->getMessage()); 
        }

        // Struktur 2: transaksis -> detail_transaksis -> produks
        try {
            $placeholders = str_repeat('?,', count($items) - 1) . '?';
            $query = "SELECT DISTINCT t.id FROM transaksis t 
                      INNER JOIN detail_transaksis dt ON t.id = dt.transaksi_id 
                      INNER JOIN produks p ON dt.produk_id = p.id 
                      WHERE p.kode_produk IN ({$placeholders}) 
                      GROUP BY t.id 
                      HAVING COUNT(DISTINCT p.kode_produk) = ?";
            $params = array_merge($items, [count($items)]);
            $result = DB::select($query, $params);
            if (isset($result)) return count($result);
        } catch (\Exception $e) { 
            Log::debug("Raw query structure 2 failed: " . $e->getMessage()); 
        }

        // Struktur 3: Direct kode_produk in detail table
        try {
            $placeholders = str_repeat('?,', count($items) - 1) . '?';
            $query = "SELECT DISTINCT t.id FROM transaksis t 
                      INNER JOIN detail_transaksis dt ON t.id = dt.transaksi_id 
                      WHERE dt.kode_produk IN ({$placeholders}) 
                      GROUP BY t.id 
                      HAVING COUNT(DISTINCT dt.kode_produk) = ?";
            $params = array_merge($items, [count($items)]);
            $result = DB::select($query, $params);
            if (isset($result)) return count($result);
        } catch (\Exception $e) { 
            Log::debug("Raw query structure 3 failed: " . $e->getMessage()); 
        }

        Log::warning("All raw query structures failed for itemset: " . json_encode($items));
        return 0;
    }

    private function updateGlobalStatus(string $statusIdentifier)
    {
        try {
            $globalBatchIdKey = config('apriori_settings.cache_keys.global_active_batch_id', AprioriController::CACHE_KEY_GLOBAL_ACTIVE_BATCH_ID);
            $cachedGlobalBatchId = Cache::get($globalBatchIdKey);

            if ($cachedGlobalBatchId === $this->aprioriBatchId) {
                $statusKeyPrefix = config('apriori_settings.cache_keys.global_batch_status_prefix', AprioriController::CACHE_KEY_GLOBAL_BATCH_STATUS_PREFIX);
                $statusToStore = config("apriori_settings.global_statuses.{$statusIdentifier}", $statusIdentifier);

                Cache::put($statusKeyPrefix . $this->aprioriBatchId, $statusToStore, now()->addDays(7));
                Log::info("CalculateAprioriSupportJob (Job 2): Global status updated to {$statusToStore}");
            }
        } catch (\Exception $e) {
            Log::error("CalculateAprioriSupportJob (Job 2): Failed to update global status: " . $e->getMessage());
        }
    }
}