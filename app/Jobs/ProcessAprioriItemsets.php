<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Services\AprioriService;
use App\Models\Produk;
use App\Models\Itemset;
use App\Http\Controllers\AprioriController;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use App\Jobs\CalculateAprioriSupportJob;

class ProcessAprioriItemsets implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected float $minSupportThreshold;
    protected ?string $targetTembakau;
    protected string $aprioriBatchId;
    protected float $minConfidenceThreshold;

    public function __construct(
        float $minSupportThreshold = 0.1,
        ?string $targetTembakau = null,
        string $aprioriBatchId,
        float $minConfidenceThreshold = 0.1
    ) {
        $this->minSupportThreshold = $minSupportThreshold;
        $this->targetTembakau = $targetTembakau;
        $this->aprioriBatchId = $aprioriBatchId;
        $this->minConfidenceThreshold = $minConfidenceThreshold;
    }

    public function handle()
    {
        $context = $this->targetTembakau ? "Interaktif (Target: {$this->targetTembakau})" : "Global";
        Log::info("ProcessAprioriItemsets (Job 1) started. Context: {$context}, Batch ID: {$this->aprioriBatchId}");

        DB::beginTransaction();

        try {
            if ($this->targetTembakau) {
                $targetProduk = Produk::where('kode_produk', $this->targetTembakau)->first();
                if (!$targetProduk) {
                    Log::error("ProcessAprioriItemsets (Job 1): Target produk {$this->targetTembakau} not found. Batch ID: {$this->aprioriBatchId}");
                    DB::rollBack();
                    return;
                }
            }

            $deletedCount = Itemset::where('apriori_batch_id', $this->aprioriBatchId)->delete();
            Log::info("ProcessAprioriItemsets (Job 1): Cleared {$deletedCount} existing itemsets for Batch ID {$this->aprioriBatchId}");

            $produkKategori = Produk::pluck('kategori_produk', 'kode_produk')->toArray();
            $kategoriUrutan = AprioriService::getDynamicCategoryOrder($this->targetTembakau, $produkKategori);
            $produk = Produk::all();

            $itemset1Combinations = [];
            $itemset2Combinations = [];
            $itemset3Combinations = [];

            if ($this->targetTembakau) {
                // Untuk target tembakau, tetap buat single itemset untuk target
                $itemset1Combinations[] = [$this->targetTembakau];
                
                foreach ($produk as $produkB) {
                    if ($produkB->kode_produk != $this->targetTembakau &&
                        isset($produkKategori[$this->targetTembakau]) && isset($produkKategori[$produkB->kode_produk]) &&
                        $produkKategori[$this->targetTembakau] != $produkKategori[$produkB->kode_produk]) {
                        
                        // PERBAIKAN: Tambahkan single itemset untuk produk B juga
                        $itemset1Combinations[] = [$produkB->kode_produk];
                        
                        $combination2 = [$this->targetTembakau, $produkB->kode_produk];
                        $itemset2Combinations[] = AprioriService::sortItemsByCategory($combination2, $produkKategori, $kategoriUrutan);
                        
                        foreach ($produk as $produkC) {
                            if ($produkC->kode_produk != $this->targetTembakau && $produkC->kode_produk != $produkB->kode_produk &&
                                isset($produkKategoriBag[$produkC->kode_produk]) &&
                                $produkKategori[$produkC->kode_produk] != $produkKategori[$this->targetTembakau] &&
                                $produkKategori[$produkC->kode_produk] != $produkKategori[$produkB->kode_produk]) {
                                
                                // PERBAIKAN: Tambahkan single itemset untuk produk C juga
                                $itemset1Combinations[] = [$produkC->kode_produk];
                                
                                $combination3 = [$this->targetTembakau, $produkB->kode_produk, $produkC->kode_produk];
                                $itemset3Combinations[] = AprioriService::sortItemsByCategory($combination3, $produkKategori, $kategoriUrutan);
                            }
                        }
                    }
                }
            } else {
                // Untuk global, buat semua single itemsets
                foreach ($produk as $produkItem) {
                    $itemset1Combinations[] = [$produkItem->kode_produk];
                }
                
                foreach ($produk as $produkA) {
                    foreach ($produk as $produkB) {
                        if ($produkA->kode_produk != $produkB->kode_produk &&
                            isset($produkKategori[$produkA->kode_produk]) && isset($produkKategori[$produkB->kode_produk]) &&
                            $produkKategori[$produkA->kode_produk] != $produkKategori[$produkB->kode_produk]) {
                            
                            $combination2 = [$produkA->kode_produk, $produkB->kode_produk];
                            $itemset2Combinations[] = AprioriService::sortItemsByCategory($combination2, $produkKategori, $kategoriUrutan);
                            
                            foreach ($produk as $produkC) {
                                if ($produkC->kode_produk != $produkA->kode_produk && $produkC->kode_produk != $produkB->kode_produk &&
                                    isset($produkKategori[$produkC->kode_produk]) &&
                                    $produkKategori[$produkC->kode_produk] != $produkKategori[$produkA->kode_produk] &&
                                    $produkKategori[$produkC->kode_produk] != $produkKategori[$produkB->kode_produk]) {
                                    
                                    $combination3 = [$produkA->kode_produk, $produkB->kode_produk, $produkC->kode_produk];
                                    $itemset3Combinations[] = AprioriService::sortItemsByCategory($combination3, $produkKategori, $kategoriUrutan);
                                }
                            }
                        }
                    }
                }
            }

            $uniqueItemset1 = AprioriService::removeDuplicateArrays($itemset1Combinations);
            $uniqueItemset2 = AprioriService::removeDuplicateArrays($itemset2Combinations);
            $uniqueItemset3 = AprioriService::removeDuplicateArrays($itemset3Combinations);
            
            Log::info("ProcessAprioriItemsets (Job 1): Unique combinations - 1-item: " . count($uniqueItemset1) . ", 2-item: " . count($uniqueItemset2) . ", 3-item: " . count($uniqueItemset3));

            $allUniqueItemsets = array_merge($uniqueItemset1, $uniqueItemset2, $uniqueItemset3);
            
            if (empty($allUniqueItemsets)) {
                Log::warning("ProcessAprioriItemsets (Job 1): No unique itemsets generated for Batch ID {$this->aprioriBatchId}");
            }

            $actualInsertedThisRun = 0;
            $chunkSize = 500;
            
            foreach (array_chunk($allUniqueItemsets, $chunkSize) as $chunk) {
                $batchDataToInsert = [];
                
                foreach ($chunk as $items) {
                    if (empty($items)) continue;
                    
                    // PERBAIKAN: Konsisten gunakan sorting untuk hash generation
                    $sortedItems = AprioriService::sortItemsByCategory($items, $produkKategori, $kategoriUrutan);
                    $itemsHash = implode('|', $sortedItems);
                    
                    $batchDataToInsert[] = [
                        'items' => json_encode($sortedItems), // Simpan yang sudah disort
                        'items_hash' => $itemsHash,
                        'item_count' => count($sortedItems),
                        'apriori_batch_id' => $this->aprioriBatchId,
                        'support_count' => null,
                        'support_value' => null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }

                if (!empty($batchDataToInsert)) {
                    DB::table('itemsets')->insert($batchDataToInsert);
                    $actualInsertedThisRun += count($batchDataToInsert);
                }
            }
            
            Log::info("ProcessAprioriItemsets (Job 1): Actual itemsets inserted: {$actualInsertedThisRun}");

            DB::commit();

            $globalBatchIdKey = config('apriori_settings.cache_keys.global_active_batch_id', AprioriController::CACHE_KEY_GLOBAL_ACTIVE_BATCH_ID);
            $cachedGlobalBatchId = Cache::get($globalBatchIdKey);
            
            if ($cachedGlobalBatchId === $this->aprioriBatchId) {
                $statusToSet = ($actualInsertedThisRun > 0)
                    ? config('apriori_settings.global_statuses.job1b_completed_job2b_dispatched', AprioriController::STATUS_GLOBAL_JOB1B_COMPLETED_JOB2B_DISPATCHED)
                    : 'job1b_completed_no_itemsets';
                    
                Cache::put(
                    config('apriori_settings.cache_keys.global_batch_status_prefix', AprioriController::CACHE_KEY_GLOBAL_BATCH_STATUS_PREFIX) . $this->aprioriBatchId,
                    $statusToSet,
                    now()->addDays(7)
                );
                Log::info("ProcessAprioriItemsets (Job 1): Global status updated to {$statusToSet}");
            }

            if ($actualInsertedThisRun > 0) {
                CalculateAprioriSupportJob::dispatch(
                    $this->aprioriBatchId,
                    $this->minSupportThreshold,
                    $this->minConfidenceThreshold,
                    $this->targetTembakau
                )->delay(now()->addSeconds(5));
                
                Log::info("ProcessAprioriItemsets (Job 1): Dispatched CalculateAprioriSupportJob (Job 2)");
            } else {
                Log::warning("ProcessAprioriItemsets (Job 1): No itemsets inserted, skipping Job 2 dispatch");
            }

        } catch (\Exception $e) {
            DB::rollBack();
            $failedStatusIdentifier = $this->targetTembakau ? 'job1a_failed' : 'job1b_failed';
            $this->updateGlobalStatusJob1Failed($failedStatusIdentifier);
            
            Log::error("ProcessAprioriItemsets (Job 1) failed: " . $e->getMessage(), [
                'exception_trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    private function updateGlobalStatusJob1Failed(string $statusIdentifier)
    {
        try {
            $globalBatchIdKey = config('apriori_settings.cache_keys.global_active_batch_id', AprioriController::CACHE_KEY_GLOBAL_ACTIVE_BATCH_ID);
            $cachedGlobalBatchId = Cache::get($globalBatchIdKey);

            if ($cachedGlobalBatchId === $this->aprioriBatchId) {
                $statusKeyPrefix = config('apriori_settings.cache_keys.global_batch_status_prefix', AprioriController::CACHE_KEY_GLOBAL_BATCH_STATUS_PREFIX);
                $failedStatusPrefix = config('apriori_settings.global_statuses.failed_prefix', AprioriController::STATUS_GLOBAL_FAILED_PREFIX);
                $statusToStore = $failedStatusPrefix . $statusIdentifier;

                Cache::put($statusKeyPrefix . $this->aprioriBatchId, $statusToStore, now()->addDays(7));
                Log::info("ProcessAprioriItemsets (Job 1): Global status updated upon failure to {$statusToStore}");
            }
        } catch (\Exception $e) {
            Log::error("ProcessAprioriItemsets (Job 1): Failed to update global status upon job failure: " . $e->getMessage());
        }
    }
}