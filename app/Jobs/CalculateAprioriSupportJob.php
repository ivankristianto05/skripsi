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

class CalculateAprioriSupportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $aprioriBatchId;
    protected $minSupportThreshold;

    /**
     * Create a new job instance.
     *
     * @param string $aprioriBatchId
     * @param float $minSupportThreshold
     * @return void
     */
    public function __construct(string $aprioriBatchId, float $minSupportThreshold = 0.1)
    {
        $this->aprioriBatchId = $aprioriBatchId;
        $this->minSupportThreshold = $minSupportThreshold;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        Log::info("CalculateAprioriSupportJob (Job 2) started. Batch ID: {$this->aprioriBatchId}, MinSupport: {$this->minSupportThreshold}");

        try {
            // Ambil semua itemset untuk batch ini
            $itemsets = Itemset::where('apriori_batch_id', $this->aprioriBatchId)->get();
            
            if ($itemsets->isEmpty()) {
                Log::warning("CalculateAprioriSupportJob (Job 2): No itemsets found for Batch ID {$this->aprioriBatchId}");
                return;
            }

            Log::info("Found {$itemsets->count()} itemsets to process");
            
            // Debug: Check the first itemset structure
            if ($itemsets->count() > 0) {
                $firstItemset = $itemsets->first();
                Log::info("Sample itemset structure - ID: {$firstItemset->id}, Items type: " . gettype($firstItemset->items) . ", Items content: " . (is_array($firstItemset->items) ? json_encode($firstItemset->items) : $firstItemset->items));
            }

            // Hitung total transaksi
            $totalTransaksi = Transaksi::count();
            
            if ($totalTransaksi == 0) {
                Log::warning("CalculateAprioriSupportJob (Job 2): No transactions found");
                return;
            }

            Log::info("Total transactions: {$totalTransaksi}");

            // Metode 1: Coba dengan relationship yang ada
            if ($this->tryWithRelationships($itemsets, $totalTransaksi)) {
                Log::info("Successfully processed with relationships method");
                $this->updateGlobalStatus('completed');
                return;
            }

            // Metode 2: Fallback dengan raw query
            Log::info("Trying fallback method with raw queries");
            $this->handleWithRawQueries($itemsets, $totalTransaksi);
            
            Log::info("CalculateAprioriSupportJob (Job 2) completed successfully");
            $this->updateGlobalStatus('completed');

        } catch (\Exception $e) {
            Log::error("CalculateAprioriSupportJob (Job 2) failed. Batch ID: {$this->aprioriBatchId}. Error: " . $e->getMessage(), [
                'exception' => $e
            ]);

            $this->updateGlobalStatus('failed_job2');
            throw $e;
        }
    }

    /**
     * Coba proses dengan relationships
     */
    private function tryWithRelationships($itemsets, $totalTransaksi)
    {
        try {
            // Daftar kemungkinan nama relationship
            $relationshipNames = [
                'produkTransaksis',
                'produks', 
                'detailTransaksi',
                'detail_transaksi',
                'details',
                'transaksiDetails',
                'produkDetails',
                'items'
            ];
            
            $validRelationship = null;
            $testTransaksi = null;

            // Cari relationship yang valid
            foreach ($relationshipNames as $relationName) {
                if (method_exists(Transaksi::class, $relationName)) {
                    try {
                        $testTransaksi = Transaksi::with($relationName)->first();
                        if ($testTransaksi && $testTransaksi->{$relationName}) {
                            $validRelationship = $relationName;
                            Log::info("Found valid relationship: {$relationName}");
                            break;
                        }
                    } catch (\Exception $e) {
                        Log::debug("Relationship test failed for {$relationName}: " . $e->getMessage());
                        continue;
                    }
                }
            }

            if (!$validRelationship) {
                Log::info("No valid relationship found, will use fallback method");
                return false;
            }

            // Ambil semua transaksi dengan relationship
            $transaksiList = Transaksi::with([$validRelationship, $validRelationship . '.produk'])->get();
            
            if ($transaksiList->isEmpty()) {
                Log::warning("No transactions found with relationship");
                return false;
            }

            Log::info("Found {$transaksiList->count()} transactions with relationship {$validRelationship}");
            
            // Debug: Check first transaction structure
            if ($transaksiList->count() > 0) {
                $firstTrans = $transaksiList->first();
                $relationData = $firstTrans->{$validRelationship};
                Log::info("Sample transaction ID: {$firstTrans->id}, Relation data count: " . ($relationData ? $relationData->count() : 0));
                
                if ($relationData && $relationData->count() > 0) {
                    $firstRelation = $relationData->first();
                    Log::info("First relation item structure: " . json_encode([
                        'has_produk' => isset($firstRelation->produk),
                        'has_kode_produk' => isset($firstRelation->kode_produk),
                        'has_produk_kode' => isset($firstRelation->produk_kode),
                        'produk_kode_if_exists' => isset($firstRelation->produk->kode_produk) ? $firstRelation->produk->kode_produk : 'N/A'
                    ]));
                }
            }

            $updatedCount = 0;
            
            foreach ($itemsets as $itemset) {
                // Handle both JSON string and array formats
                if (is_string($itemset->items)) {
                    $items = json_decode($itemset->items, true);
                } else {
                    $items = $itemset->items; // Already an array
                }
                
                if (empty($items) || !is_array($items)) {
                    Log::warning("Invalid items format for itemset ID {$itemset->id}: " . gettype($itemset->items));
                    continue;
                }
                
                $supportCount = 0;

                // Hitung support count untuk itemset ini
                foreach ($transaksiList as $transaksi) {
                    $produkInTransaksi = $this->extractProdukCodes($transaksi, $validRelationship);
                    
                    // Cek apakah semua item dalam itemset ada di transaksi ini
                    if ($this->itemsetExistsInTransaction($items, $produkInTransaksi)) {
                        $supportCount++;
                    }
                }

                // Hitung support value
                $supportValue = $totalTransaksi > 0 ? $supportCount / $totalTransaksi : 0;

                // Update itemset
                $itemset->update([
                    'support_count' => $supportCount,
                    'support_value' => round($supportValue, 4)
                ]);

                $updatedCount++;

                // Log untuk itemset dengan support tinggi
                if ($supportValue >= $this->minSupportThreshold) {
                    Log::info("High support itemset: " . json_encode($items) . " (Count: {$supportCount}, Value: {$supportValue})");
                }
            }

            Log::info("Updated {$updatedCount} itemsets using relationships method");
            return true;

        } catch (\Exception $e) {
            Log::error("Relationships method failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Extract kode produk dari transaksi berdasarkan relationship
     */
    private function extractProdukCodes($transaksi, $relationshipName)
    {
        $produkCodes = [];
        
        try {
            $relationData = $transaksi->{$relationshipName};
            
            if (!$relationData || $relationData->isEmpty()) {
                return $produkCodes;
            }

            // Coba berbagai cara mengambil kode produk
            foreach ($relationData as $item) {
                // Jika ada relasi produk
                if (isset($item->produk) && isset($item->produk->kode_produk)) {
                    $produkCodes[] = $item->produk->kode_produk;
                }
                // Jika langsung ada kode_produk
                elseif (isset($item->kode_produk)) {
                    $produkCodes[] = $item->kode_produk;
                }
                // Jika ada produk_kode (variasi nama field)
                elseif (isset($item->produk_kode)) {
                    $produkCodes[] = $item->produk_kode;
                }
            }

            // Jika masih kosong, coba akses langsung collection
            if (empty($produkCodes)) {
                if (method_exists($relationData, 'pluck')) {
                    // Coba pluck dari berbagai kemungkinan field
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
            }

        } catch (\Exception $e) {
            Log::debug("Error extracting produk codes for transaction {$transaksi->id}: " . $e->getMessage());
        }

        return array_unique($produkCodes);
    }

    /**
     * Cek apakah itemset ada dalam transaksi
     */
    private function itemsetExistsInTransaction($itemset, $produkInTransaksi)
    {
        if (empty($itemset) || empty($produkInTransaksi)) {
            return false;
        }

        // Semua item dalam itemset harus ada di produkInTransaksi
        foreach ($itemset as $item) {
            if (!in_array($item, $produkInTransaksi)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Fallback method menggunakan raw queries
     */
    private function handleWithRawQueries($itemsets, $totalTransaksi)
    {
        Log::info("Using fallback method with raw queries");
        
        try {
            $updatedCount = 0;

            foreach ($itemsets as $itemset) {
                // Handle both JSON string and array formats
                if (is_string($itemset->items)) {
                    $items = json_decode($itemset->items, true);
                } else {
                    $items = $itemset->items; // Already an array
                }
                
                if (empty($items) || !is_array($items)) {
                    Log::warning("Invalid items format for itemset ID {$itemset->id}: " . gettype($itemset->items));
                    continue;
                }

                $supportCount = $this->calculateSupportWithRawQuery($items);

                // Hitung support value
                $supportValue = $totalTransaksi > 0 ? $supportCount / $totalTransaksi : 0;

                // Update itemset
                $itemset->update([
                    'support_count' => $supportCount,
                    'support_value' => round($supportValue, 4)
                ]);

                $updatedCount++;

                // Log untuk itemset dengan support tinggi
                if ($supportValue >= $this->minSupportThreshold) {
                    Log::info("High support itemset (raw): " . json_encode($items) . " (Count: {$supportCount}, Value: {$supportValue})");
                }
            }

            Log::info("Fallback method completed. Updated {$updatedCount} itemsets");

        } catch (\Exception $e) {
            Log::error("Fallback method failed: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Hitung support dengan raw query - mencoba berbagai struktur tabel
     */
    private function calculateSupportWithRawQuery($items)
    {
        // Struktur 1: transaksis -> produk_transaksis -> produks
        try {
            $placeholders = str_repeat('?,', count($items) - 1) . '?';
            
            $query = "
                SELECT DISTINCT t.id 
                FROM transaksis t
                INNER JOIN produk_transaksis pt ON t.id = pt.transaksi_id
                INNER JOIN produks p ON pt.produk_id = p.id
                WHERE p.kode_produk IN ({$placeholders})
                GROUP BY t.id
                HAVING COUNT(DISTINCT p.kode_produk) = ?
            ";

            $params = array_merge($items, [count($items)]);
            $result = DB::select($query, $params);
            
            if (!empty($result)) {
                return count($result);
            }
        } catch (\Exception $e) {
            Log::debug("Query structure 1 failed: " . $e->getMessage());
        }

        // Struktur 2: transaksis -> detail_transaksi -> produks
        try {
            $placeholders = str_repeat('?,', count($items) - 1) . '?';
            
            $query = "
                SELECT DISTINCT t.id 
                FROM transaksis t
                INNER JOIN detail_transaksi dt ON t.id = dt.transaksi_id
                INNER JOIN produks p ON dt.produk_id = p.id
                WHERE p.kode_produk IN ({$placeholders})
                GROUP BY t.id
                HAVING COUNT(DISTINCT p.kode_produk) = ?
            ";

            $params = array_merge($items, [count($items)]);
            $result = DB::select($query, $params);
            
            if (!empty($result)) {
                return count($result);
            }
        } catch (\Exception $e) {
            Log::debug("Query structure 2 failed: " . $e->getMessage());
        }

        // Struktur 3: transaksis -> transaksi_details -> produks
        try {
            $placeholders = str_repeat('?,', count($items) - 1) . '?';
            
            $query = "
                SELECT DISTINCT t.id 
                FROM transaksis t
                INNER JOIN transaksi_details td ON t.id = td.transaksi_id
                INNER JOIN produks p ON td.produk_id = p.id
                WHERE p.kode_produk IN ({$placeholders})
                GROUP BY t.id
                HAVING COUNT(DISTINCT p.kode_produk) = ?
            ";

            $params = array_merge($items, [count($items)]);
            $result = DB::select($query, $params);
            
            return count($result);
            
        } catch (\Exception $e) {
            Log::debug("Query structure 3 failed: " . $e->getMessage());
        }

        // Jika semua struktur gagal, return 0
        Log::warning("All query structures failed for itemset: " . json_encode($items));
        return 0;
    }

    /**
     * Update global status
     */
    private function updateGlobalStatus($status)
    {
        try {
            $globalBatchIdKey = defined('App\Http\Controllers\AprioriController::CACHE_KEY_GLOBAL_ACTIVE_BATCH_ID') 
                ? AprioriController::CACHE_KEY_GLOBAL_ACTIVE_BATCH_ID 
                : 'apriori_global_active_batch_id';
                
            $cachedGlobalBatchId = Cache::get($globalBatchIdKey);
            
            if ($cachedGlobalBatchId === $this->aprioriBatchId) {
                $statusKey = defined('App\Http\Controllers\AprioriController::CACHE_KEY_GLOBAL_BATCH_STATUS_PREFIX') 
                    ? AprioriController::CACHE_KEY_GLOBAL_BATCH_STATUS_PREFIX . $this->aprioriBatchId
                    : 'apriori_global_batch_status_' . $this->aprioriBatchId;
                    
                $finalStatus = $status === 'completed' 
                    ? (defined('App\Http\Controllers\AprioriController::STATUS_COMPLETED') 
                        ? AprioriController::STATUS_COMPLETED 
                        : 'completed')
                    : (defined('App\Http\Controllers\AprioriController::STATUS_FAILED_PREFIX') 
                        ? AprioriController::STATUS_FAILED_PREFIX . 'job2'
                        : 'failed_job2');
                        
                Cache::put($statusKey, $finalStatus, now()->addDays(7));
                Log::info("CalculateAprioriSupportJob (Job 2): Global status updated for Batch ID {$this->aprioriBatchId} to {$finalStatus}.");
            }
        } catch (\Exception $e) {
            Log::error("Failed to update global status: " . $e->getMessage());
        }
    }
}