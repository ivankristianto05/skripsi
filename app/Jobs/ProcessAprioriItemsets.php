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
use App\Http\Controllers\AprioriController; // Untuk konstanta cache keys
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use App\Jobs\CalculateAprioriSupportJob; // Import Job 2

class ProcessAprioriItemsets implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $minSupportThreshold; // Untuk diteruskan ke Job 2
    protected $targetTembakau;      // null untuk Job 1b (global)
    protected $aprioriBatchId;      // ID unik untuk proses ini

    /**
     * Create a new job instance.
     *
     * @param float $minSupportThreshold
     * @param string|null $targetTembakau
     * @param string $aprioriBatchId Wajib ada, dibuat oleh AprioriService atau pemicu lainnya
     * @return void
     */
    public function __construct($minSupportThreshold = 0.1, $targetTembakau = null, string $aprioriBatchId)
    {
        $this->minSupportThreshold = $minSupportThreshold;
        $this->targetTembakau = $targetTembakau;
        $this->aprioriBatchId = $aprioriBatchId; // Selalu gunakan batchId yang diberikan
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $context = $this->targetTembakau ? "Interaktif (Target: {$this->targetTembakau})" : "Global";
        Log::info("ProcessAprioriItemsets (Job 1) started. Context: {$context}, Batch ID: {$this->aprioriBatchId}, MinSupport for Job 2: {$this->minSupportThreshold}");

        try {
            // Debug: Cek apakah targetTembakau ada di database
            if ($this->targetTembakau) {
                $targetProduk = Produk::where('kode_produk', $this->targetTembakau)->first();
                if (!$targetProduk) {
                    Log::error("ProcessAprioriItemsets (Job 1): Target produk {$this->targetTembakau} not found in database");
                    return;
                }
                Log::info("ProcessAprioriItemsets (Job 1): Target produk found - {$targetProduk->kode_produk} ({$targetProduk->kategori_produk})");
            }

            // Hapus itemset lama dengan batch_id yang sama (jika ada) untuk menghindari duplikasi
            $deletedCount = Itemset::where('apriori_batch_id', $this->aprioriBatchId)->count();
            Itemset::where('apriori_batch_id', $this->aprioriBatchId)->delete();
            Log::info("ProcessAprioriItemsets (Job 1): Cleared {$deletedCount} existing itemsets for Batch ID {$this->aprioriBatchId}");

            $produkKategori = Produk::pluck('kategori_produk', 'kode_produk')->toArray();
            Log::info("ProcessAprioriItemsets (Job 1): Found " . count($produkKategori) . " products in database");
            
            $kategoriUrutan = AprioriService::getDynamicCategoryOrder($this->targetTembakau, $produkKategori);
            $produk = Produk::all(); // Ambil semua produk untuk membuat kombinasi

            $itemset1Combinations = [];
            $itemset2Combinations = [];
            $itemset3Combinations = [];

            // --- Logika Pembentukan Kombinasi Itemset ---
            if ($this->targetTembakau) {
                Log::info("ProcessAprioriItemsets (Job 1): Processing for target tembakau {$this->targetTembakau}");
                
                // Logika jika ada targetTembakau (Job 1a)
                $itemset1Combinations[] = [$this->targetTembakau];
                Log::info("ProcessAprioriItemsets (Job 1): Added 1-itemset: " . json_encode([$this->targetTembakau]));
                
                $combinations2Count = 0;
                $combinations3Count = 0;
                
                foreach ($produk as $produkB) {
                    if ($produkB->kode_produk != $this->targetTembakau &&
                        isset($produkKategori[$this->targetTembakau]) && isset($produkKategori[$produkB->kode_produk]) &&
                        $produkKategori[$this->targetTembakau] != $produkKategori[$produkB->kode_produk]) {

                        $combination2 = [$this->targetTembakau, $produkB->kode_produk];
                        $sortedCombination2 = AprioriService::sortItemsByCategory($combination2, $produkKategori, $kategoriUrutan);
                        $itemset2Combinations[] = $sortedCombination2;
                        $combinations2Count++;
                        
                        if ($combinations2Count <= 3) { // Log only first 3 for brevity
                            Log::info("ProcessAprioriItemsets (Job 1): Added 2-itemset: " . json_encode($sortedCombination2));
                        }

                        foreach ($produk as $produkC) {
                            if ($produkC->kode_produk != $this->targetTembakau &&
                                $produkC->kode_produk != $produkB->kode_produk &&
                                isset($produkKategori[$produkC->kode_produk]) &&
                                $produkKategori[$produkC->kode_produk] != $produkKategori[$this->targetTembakau] &&
                                $produkKategori[$produkC->kode_produk] != $produkKategori[$produkB->kode_produk]) {
                                
                                $combination3 = [$this->targetTembakau, $produkB->kode_produk, $produkC->kode_produk];
                                $sortedCombination3 = AprioriService::sortItemsByCategory($combination3, $produkKategori, $kategoriUrutan);
                                $itemset3Combinations[] = $sortedCombination3;
                                $combinations3Count++;
                                
                                if ($combinations3Count <= 3) { // Log only first 3 for brevity
                                    Log::info("ProcessAprioriItemsets (Job 1): Added 3-itemset: " . json_encode($sortedCombination3));
                                }
                            }
                        }
                    }
                }
                
                Log::info("ProcessAprioriItemsets (Job 1): Generated combinations - 2-item: {$combinations2Count}, 3-item: {$combinations3Count}");
                
            } else {
                Log::info("ProcessAprioriItemsets (Job 1): Processing for global analysis");
                
                // Logika jika TIDAK ada targetTembakau (Job 1b - Global)
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
                                if ($produkC->kode_produk != $produkA->kode_produk &&
                                    $produkC->kode_produk != $produkB->kode_produk &&
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
            // --- Akhir Logika Pembentukan Kombinasi ---

            Log::info("ProcessAprioriItemsets (Job 1): Raw combinations - 1-item: " . count($itemset1Combinations) . ", 2-item: " . count($itemset2Combinations) . ", 3-item: " . count($itemset3Combinations));

            $uniqueItemset1 = AprioriService::removeDuplicateArrays($itemset1Combinations);
            $uniqueItemset2 = AprioriService::removeDuplicateArrays($itemset2Combinations);
            $uniqueItemset3 = AprioriService::removeDuplicateArrays($itemset3Combinations);

            Log::info("ProcessAprioriItemsets (Job 1): Unique combinations - 1-item: " . count($uniqueItemset1) . ", 2-item: " . count($uniqueItemset2) . ", 3-item: " . count($uniqueItemset3));

            $itemsetsToInsert = [];
            $totalInserted = 0;

            $prepareItemsetData = function (array $itemsArray) {
                $itemsHash = implode('|', $itemsArray); // Asumsi $itemsArray sudah diurutkan dgn benar
                return [
                    'items' => json_encode($itemsArray),
                    'items_hash' => $itemsHash,
                    'item_count' => count($itemsArray),
                    'apriori_batch_id' => $this->aprioriBatchId,
                    'support_count' => null, // Akan diisi Job 2
                    'support_value' => null, // Akan diisi Job 2
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            };

            $allUniqueItemsets = array_merge($uniqueItemset1, $uniqueItemset2, $uniqueItemset3);
            Log::info("ProcessAprioriItemsets (Job 1): Total unique itemsets to insert: " . count($allUniqueItemsets));

            if (empty($allUniqueItemsets)) {
                Log::warning("ProcessAprioriItemsets (Job 1): No itemsets generated for Batch ID {$this->aprioriBatchId}");
                
                // Debug: Check why no itemsets were generated
                if ($this->targetTembakau) {
                    $targetKategori = $produkKategori[$this->targetTembakau] ?? 'UNKNOWN';
                    Log::info("ProcessAprioriItemsets (Job 1): Target category: {$targetKategori}");
                    
                    $otherCategories = array_unique(array_values($produkKategori));
                    $differentCategories = array_filter($otherCategories, function($cat) use ($targetKategori) {
                        return $cat !== $targetKategori;
                    });
                    
                    Log::info("ProcessAprioriItemsets (Job 1): Available different categories: " . json_encode($differentCategories));
                    Log::info("ProcessAprioriItemsets (Job 1): Products by category: " . json_encode(array_count_values($produkKategori)));
                }
                
                return;
            }

            // Chunk data untuk insert agar tidak terlalu besar jika itemset sangat banyak
            $chunkSize = 500;
            foreach (array_chunk($allUniqueItemsets, $chunkSize) as $chunkIndex => $chunk) {
                $batchDataToInsert = [];
                foreach ($chunk as $items) {
                    if (empty($items)) continue; // Lewati jika array item kosong
                    $batchDataToInsert[] = $prepareItemsetData($items);
                }

                if (!empty($batchDataToInsert)) {
                    Log::info("ProcessAprioriItemsets (Job 1): Inserting chunk " . ($chunkIndex + 1) . " with " . count($batchDataToInsert) . " itemsets");
                    
                    // Gunakan bulk insert dengan DB::table untuk performa yang lebih baik
                    try {
                        DB::table('itemsets')->insert($batchDataToInsert);
                        $totalInserted += count($batchDataToInsert);
                        Log::info("ProcessAprioriItemsets (Job 1): Successfully inserted chunk " . ($chunkIndex + 1));
                    } catch (\Illuminate\Database\QueryException $e) {
                        // Jika masih ada masalah duplikasi, coba insert satu per satu dengan ignore
                        Log::warning("ProcessAprioriItemsets (Job 1): Bulk insert failed for chunk " . ($chunkIndex + 1) . ", trying individual inserts. Error: " . $e->getMessage());
                        
                        $individualInsertCount = 0;
                        foreach ($batchDataToInsert as $itemData) {
                            try {
                                DB::table('itemsets')->insertOrIgnore($itemData);
                                $individualInsertCount++;
                                $totalInserted++;
                            } catch (\Exception $individualError) {
                                Log::warning("ProcessAprioriItemsets (Job 1): Failed to insert individual itemset: " . json_encode($itemData) . ". Error: " . $individualError->getMessage());
                            }
                        }
                        Log::info("ProcessAprioriItemsets (Job 1): Individual insert completed: {$individualInsertCount}/{" . count($batchDataToInsert) . "}");
                    }
                }
            }

            Log::info("ProcessAprioriItemsets (Job 1) finished. Context: {$context}, Batch ID: {$this->aprioriBatchId}. Total unique combinations processed/inserted: {$totalInserted}. [1-item: " . count($uniqueItemset1) . ", 2-item: " . count($uniqueItemset2) . ", 3-item: " . count($uniqueItemset3) . "]");

            // Cek apakah ini proses global dan update statusnya di cache
            $globalBatchIdKey = defined('App\Http\Controllers\AprioriController::CACHE_KEY_GLOBAL_ACTIVE_BATCH_ID') 
                ? AprioriController::CACHE_KEY_GLOBAL_ACTIVE_BATCH_ID 
                : 'apriori_global_active_batch_id';
                
            $cachedGlobalBatchId = Cache::get($globalBatchIdKey);
            
            if ($cachedGlobalBatchId === $this->aprioriBatchId) {
                $statusKey = defined('App\Http\Controllers\AprioriController::CACHE_KEY_GLOBAL_BATCH_STATUS_PREFIX') 
                    ? AprioriController::CACHE_KEY_GLOBAL_BATCH_STATUS_PREFIX . $this->aprioriBatchId
                    : 'apriori_global_batch_status_' . $this->aprioriBatchId;
                    
                $statusValue = defined('App\Http\Controllers\AprioriController::STATUS_JOB1B_COMPLETED_JOB2B_DISPATCHED') 
                    ? AprioriController::STATUS_JOB1B_COMPLETED_JOB2B_DISPATCHED
                    : 'job1b_completed_job2b_dispatched';
                    
                Cache::put($statusKey, $statusValue, now()->addDays(7));
                Log::info("ProcessAprioriItemsets (Job 1): Global status updated for Batch ID {$this->aprioriBatchId} to {$statusValue}.");
            }

            // Dispatch Job 2 (CalculateAprioriSupportJob)
            if ($totalInserted > 0) {
                CalculateAprioriSupportJob::dispatch($this->aprioriBatchId, $this->minSupportThreshold)
                                        ->delay(now()->addSeconds(5)); // Beri jeda sedikit jika perlu
                Log::info("ProcessAprioriItemsets (Job 1): Dispatched CalculateAprioriSupportJob (Job 2) for Batch ID {$this->aprioriBatchId}.");
            } else {
                 Log::warning("ProcessAprioriItemsets (Job 1): No itemsets generated for Batch ID {$this->aprioriBatchId}. Skipping dispatch of Job 2.");
                 
                 // Update status global jika tidak ada itemset
                 if ($cachedGlobalBatchId === $this->aprioriBatchId) {
                     $statusKey = defined('App\Http\Controllers\AprioriController::CACHE_KEY_GLOBAL_BATCH_STATUS_PREFIX') 
                         ? AprioriController::CACHE_KEY_GLOBAL_BATCH_STATUS_PREFIX . $this->aprioriBatchId
                         : 'apriori_global_batch_status_' . $this->aprioriBatchId;
                         
                     Cache::put($statusKey, 'job1b_completed_no_itemsets', now()->addDays(7));
                 }
                 
                 // Untuk target tembakau, ini masalah serius
                 if ($this->targetTembakau) {
                     Log::error("ProcessAprioriItemsets (Job 1): CRITICAL - No itemsets generated for target tembakau {$this->targetTembakau}. This should not happen.");
                 }
            }

        } catch (\Exception $e) {
            Log::error("ProcessAprioriItemsets (Job 1) failed. Context: {$context}, Batch ID: {$this->aprioriBatchId}. Error: " . $e->getMessage(), [
                'exception' => $e
            ]);

            // Update status global jika gagal
            $globalBatchIdKey = defined('App\Http\Controllers\AprioriController::CACHE_KEY_GLOBAL_ACTIVE_BATCH_ID') 
                ? AprioriController::CACHE_KEY_GLOBAL_ACTIVE_BATCH_ID 
                : 'apriori_global_active_batch_id';
                
            $cachedGlobalBatchId = Cache::get($globalBatchIdKey);
            
            if ($cachedGlobalBatchId === $this->aprioriBatchId) {
                $statusKey = defined('App\Http\Controllers\AprioriController::CACHE_KEY_GLOBAL_BATCH_STATUS_PREFIX') 
                    ? AprioriController::CACHE_KEY_GLOBAL_BATCH_STATUS_PREFIX . $this->aprioriBatchId
                    : 'apriori_global_batch_status_' . $this->aprioriBatchId;
                    
                $failedStatus = defined('App\Http\Controllers\AprioriController::STATUS_FAILED_PREFIX') 
                    ? AprioriController::STATUS_FAILED_PREFIX . 'job1b'
                    : 'failed_job1b';
                    
                Cache::put($statusKey, $failedStatus, now()->addDays(7));
            }
            throw $e; // Re-throw agar job bisa di-retry atau ditandai gagal oleh Laravel
        }
    }
}