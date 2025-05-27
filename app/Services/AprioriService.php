<?php

namespace App\Services;

use App\Models\Produk;
use App\Models\Transaksi;
use App\Models\ProdukTransaksi;
use App\Jobs\ProcessAprioriItemsets; // Import Job
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str; // Untuk UUID

class AprioriService
{
    /**
     * Dispatch job untuk menghasilkan itemset di background.
     *
     * @param float $minSupport
     * @param string|null $targetTembakau
     * @return string Kunci cache yang akan digunakan untuk menyimpan hasil.
     */
    public static function dispatchItemsetCombinationJob($minSupportThreshold = 0.1, $targetProdukKode = null, $existingBatchId = null)
    {
        $aprioriBatchId = $existingBatchId ?: (string) Str::uuid();

        ProcessAprioriItemsets::dispatch($minSupportThreshold, $targetProdukKode, $aprioriBatchId);

        $context = $targetProdukKode ? "Interaktif (Target: {$targetProdukKode})" : "Global";
        Log::info("AprioriService: Dispatched ProcessAprioriItemsets job (Job 1). Context: {$context}, Batch ID: {$aprioriBatchId}, MinSupport for Job 2: {$minSupportThreshold}");

        return $aprioriBatchId;
    }

    /**
     * Mengambil hasil frequent itemsets yang telah diproses oleh job.
     *
     * @param string $cacheKey
     * @return array|null Hasil itemset atau null jika belum tersedia/gagal.
     */
    public static function getProcessedItemsets($cacheKey)
    {
        // Logika ini mungkin tidak lagi relevan jika hasil disimpan di DB dan status via Cache keys global
        if (Cache::has($cacheKey . '_processing_status') && Cache::get($cacheKey . '_processing_status') === 'pending') {
            return ['status' => 'pending', 'message' => 'Proses pembentukan itemset masih berjalan.'];
        }
        $result = Cache::get($cacheKey);
        if ($result) {
            return $result;
        }
        return null;
    }

    /**
     * Menentukan urutan kategori berdasarkan target yang dipilih user (PUBLIC STATIC)
     */
    public static function getDynamicCategoryOrder($targetKode, $produkKategori)
    {
        if (!$targetKode || !isset($produkKategori[$targetKode])) {
            return ['tembakau' => 1, 'filter' => 2, 'kertas' => 3];
        }
        $targetKategori = $produkKategori[$targetKode];
        switch ($targetKategori) {
            case 'tembakau':
                return ['tembakau' => 1, 'filter' => 2, 'kertas' => 3];
            case 'filter':
                return ['filter' => 1, 'tembakau' => 2, 'kertas' => 3];
            case 'kertas':
                return ['kertas' => 1, 'tembakau' => 2, 'filter' => 3];
            default:
                return ['tembakau' => 1, 'filter' => 2, 'kertas' => 3];
        }
    }

    /**
     * Mengurutkan item dalam array berdasarkan kategori dengan urutan yang ditentukan (PUBLIC STATIC)
     */
    public static function sortItemsByCategory($items, $produkKategori, $kategoriUrutan)
    {
        usort($items, function ($kodeProdukA, $kodeProdukB) use ($produkKategori, $kategoriUrutan) {
            $kategoriA = $produkKategori[$kodeProdukA] ?? 'unknown';
            $kategoriB = $produkKategori[$kodeProdukB] ?? 'unknown';
            $prioritasA = $kategoriUrutan[$kategoriA] ?? 999;
            $prioritasB = $kategoriUrutan[$kategoriB] ?? 999;
            return $prioritasA <=> $prioritasB;
        });
        return $items;
    }

    /**
     * Menghitung support dari itemset (PUBLIC STATIC)
     */
    public static function calculateSupport($itemsets, $minSupport = 0.1)
    {
        $totalTransactions = self::getTotalTransactions();
        if ($totalTransactions == 0) {
            return []; // Hindari division by zero
        }
        $itemsetsWithSupport = [];
        foreach ($itemsets as $itemset) {
            $supportCount = self::getSupportCount($itemset);
            $supportPercentage = $supportCount / $totalTransactions;
            if ($supportPercentage >= $minSupport) {
                $itemsetsWithSupport[] = [
                    'itemset' => $itemset,
                    'support_count' => $supportCount,
                    'support_percentage' => round($supportPercentage, 4),
                    'total_transactions' => $totalTransactions
                ];
            }
        }
        usort($itemsetsWithSupport, function ($a, $b) {
            return $b['support_percentage'] <=> $a['support_percentage'];
        });
        return $itemsetsWithSupport;
    }

    /**
     * Mendapatkan jumlah total transaksi (PUBLIC STATIC)
     */
    public static function getTotalTransactions()
    {
        return Transaksi::count();
    }

    /**
     * Menghitung support count dari itemset tertentu (PUBLIC STATIC)
     */
    public static function getSupportCount($itemset)
    {
        // Optimasi: Ambil transaksi yang relevan saja jika memungkinkan
        // Untuk saat ini, kita tetap menggunakan logika awal
        $transaksi = Transaksi::with('produkTransaksis:kode_transaksi,kode_produk') // Hanya pilih kolom yang dibutuhkan
                                ->whereHas('produkTransaksis', function($query) use ($itemset) {
                                    // Opsi: pre-filter transaksi yang mungkin mengandung itemset
                                    // Ini bisa kompleks, jadi untuk awal kita filter di PHP
                                })
                                ->get();
        $supportCount = 0;

        foreach ($transaksi as $t) {
            $produkDalamTransaksi = $t->produkTransaksis->pluck('kode_produk')->toArray();
            $itemsetAda = true;
            foreach ($itemset as $kodeProduk) {
                if (!in_array($kodeProduk, $produkDalamTransaksi)) {
                    $itemsetAda = false;
                    break;
                }
            }
            if ($itemsetAda) {
                $supportCount++;
            }
        }
        return $supportCount;
    }

    /**
     * Menerjemahkan itemset dengan support ke nama produk (PUBLIC STATIC)
     */
    public static function translateItemsetsWithSupport($itemsetsWithSupport, $produkNama)
    {
        $translatedItemsets = [];
        foreach ($itemsetsWithSupport as $itemsetData) {
            $translatedCombination = [];
            foreach ($itemsetData['itemset'] as $kodeProduk) {
                $translatedCombination[] = $produkNama[$kodeProduk] ?? $kodeProduk;
            }
            $translatedItemsets[] = [
                'itemset' => implode(' - ', $translatedCombination),
                'itemset_codes' => $itemsetData['itemset'],
                'support_count' => $itemsetData['support_count'],
                'support_percentage' => $itemsetData['support_percentage'],
                'total_transactions' => $itemsetData['total_transactions']
            ];
        }
        return $translatedItemsets;
    }

    /**
     * Menghilangkan duplikasi array (PUBLIC STATIC)
     */
    public static function removeDuplicateArrays($arrays)
    {
        $uniqueArrays = [];
        $seenCombinations = [];
        foreach ($arrays as $array) {
            $sortedArray = $array; // Asumsikan $array sudah di-sort oleh sortItemsByCategory
            // sort($sortedArray); // Uncomment jika ingin sort leksikografis tambahan untuk hash yang lebih ketat
            $signature = implode('|', $sortedArray);
            if (!in_array($signature, $seenCombinations)) {
                $seenCombinations[] = $signature;
                $uniqueArrays[] = $array;
            }
        }
        return $uniqueArrays;
    }


    /**
     * Generate association rules.
     * Method ini sekarang akan mengambil frequent itemsets dari cache.
     */
    public static function generateAssociationRules($itemsetCacheKey, $minConfidence = 0.5)
    {
        $frequentItemsets = self::getProcessedItemsets($itemsetCacheKey);

        if (!$frequentItemsets || !isset($frequentItemsets['itemsets_2'])) {
             Log::warning("Frequent itemsets not found or incomplete in cache for key: {$itemsetCacheKey} when generating association rules.");
             return ['error' => "Data itemset (key: {$itemsetCacheKey}) tidak ditemukan atau belum lengkap. Proses rules tidak dapat dilanjutkan."];
        }
        
        // Ambil targetTembakau dan produkNama dari hasil itemset jika ada, atau fetch ulang
        $targetTembakau = $frequentItemsets['target_tembakau_code'] ?? null;
        $produkNama = Produk::pluck('nama_produk', 'kode_produk')->toArray(); // Fetch ulang untuk kepastian

        $rules = [];

        // Generate rules dari 2-itemsets
        if(isset($frequentItemsets['itemsets_2'])) {
            foreach ($frequentItemsets['itemsets_2'] as $itemset) {
                $codes = $itemset['itemset_codes']; // Sudah 'itemset_codes' dari translateItemsetsWithSupport
                if (count($codes) == 2) {
                    if ($targetTembakau) {
                        if (in_array($targetTembakau, $codes)) {
                            $otherItem = ($codes[0] == $targetTembakau) ? $codes[1] : $codes[0];
                            // Rule: Target -> Other
                            $rule1 = self::calculateRule([$targetTembakau], [$otherItem], $minConfidence, $produkNama);
                            if ($rule1) $rules[] = $rule1;
                            // Rule: Other -> Target
                            $rule2 = self::calculateRule([$otherItem], [$targetTembakau], $minConfidence, $produkNama);
                            if ($rule2) $rules[] = $rule2;
                        }
                    } else {
                        $ruleAB = self::calculateRule([$codes[0]], [$codes[1]], $minConfidence, $produkNama);
                        if ($ruleAB) $rules[] = $ruleAB;
                        $ruleBA = self::calculateRule([$codes[1]], [$codes[0]], $minConfidence, $produkNama);
                        if ($ruleBA) $rules[] = $ruleBA;
                    }
                }
            }
        }

        // Generate rules dari 3-itemsets
        if(isset($frequentItemsets['itemsets_3'])) {
            foreach ($frequentItemsets['itemsets_3'] as $itemset) {
                $codes = $itemset['itemset_codes'];
                if (count($codes) == 3) {
                    if ($targetTembakau && in_array($targetTembakau, $codes)) {
                        $remainingItems = array_values(array_diff($codes, [$targetTembakau]));
                        if(count($remainingItems) == 2) {
                            // Rule: Target -> [Other1, Other2]
                            $rule = self::calculateRule([$targetTembakau], $remainingItems, $minConfidence, $produkNama);
                            if ($rule) $rules[] = $rule;
                            // Rule: [Other1] -> [Target, Other2] (dan permutasinya)
                            // Rule: [Other1, Other2] -> Target
                            $rule = self::calculateRule($remainingItems, [$targetTembakau], $minConfidence, $produkNama);
                            if ($rule) $rules[] = $rule;

                            // Rule: Other1 -> [Target, Other2]
                            $rule = self::calculateRule([$remainingItems[0]], [$targetTembakau, $remainingItems[1]], $minConfidence, $produkNama);
                            if ($rule) $rules[] = $rule;
                             // Rule: Other2 -> [Target, Other1]
                            $rule = self::calculateRule([$remainingItems[1]], [$targetTembakau, $remainingItems[0]], $minConfidence, $produkNama);
                            if ($rule) $rules[] = $rule;
                        }
                    } elseif (!$targetTembakau) {
                        // A -> BC
                        $rule = self::calculateRule([$codes[0]], [$codes[1], $codes[2]], $minConfidence, $produkNama); if ($rule) $rules[] = $rule;
                        // B -> AC
                        $rule = self::calculateRule([$codes[1]], [$codes[0], $codes[2]], $minConfidence, $produkNama); if ($rule) $rules[] = $rule;
                        // C -> AB
                        $rule = self::calculateRule([$codes[2]], [$codes[0], $codes[1]], $minConfidence, $produkNama); if ($rule) $rules[] = $rule;
                        // AB -> C
                        $rule = self::calculateRule([$codes[0], $codes[1]], [$codes[2]], $minConfidence, $produkNama); if ($rule) $rules[] = $rule;
                        // AC -> B
                        $rule = self::calculateRule([$codes[0], $codes[2]], [$codes[1]], $minConfidence, $produkNama); if ($rule) $rules[] = $rule;
                        // BC -> A
                        $rule = self::calculateRule([$codes[1], $codes[2]], [$codes[0]], $minConfidence, $produkNama); if ($rule) $rules[] = $rule;
                    }
                }
            }
        }

        // Menghilangkan duplikasi rules berdasarkan antecedent dan consequent
        $uniqueRules = [];
        $seenRuleSignatures = [];
        foreach ($rules as $rule) {
            $antecedentSorted = $rule['antecedent_codes']; sort($antecedentSorted);
            $consequentSorted = $rule['consequent_codes']; sort($consequentSorted);
            $signature = implode(',', $antecedentSorted) . '=>' . implode(',', $consequentSorted);
            if(!in_array($signature, $seenRuleSignatures)){
                $seenRuleSignatures[] = $signature;
                $uniqueRules[] = $rule;
            }
        }
        $rules = $uniqueRules;


        usort($rules, function ($a, $b) {
            return $b['confidence'] <=> $a['confidence'];
        });

        return $rules;
    }


    /**
     * Menghitung confidence dari rule (PUBLIC STATIC)
     * $produkNama ditambahkan sebagai parameter untuk efisiensi
     */
    public static function calculateRule($antecedent, $consequent, $minConfidence, $produkNama)
    {
        $antecedentSupportCount = self::getSupportCount($antecedent);
        if ($antecedentSupportCount == 0) return null;

        $unionItemset = array_merge($antecedent, $consequent);
        // Pastikan tidak ada duplikasi item dalam unionItemset sebelum menghitung support
        $unionItemset = array_unique($unionItemset);
        sort($unionItemset); // Konsistensi
        
        $unionSupportCount = self::getSupportCount($unionItemset);
        $confidence = $unionSupportCount / $antecedentSupportCount;

        if ($confidence >= $minConfidence) {
            $totalTransactions = self::getTotalTransactions();
            $supportPercentageUnion = $totalTransactions > 0 ? ($unionSupportCount / $totalTransactions) : 0;
            
            return [
                'antecedent' => array_map(fn ($code) => $produkNama[$code] ?? $code, $antecedent),
                'consequent' => array_map(fn ($code) => $produkNama[$code] ?? $code, $consequent),
                'antecedent_codes' => $antecedent,
                'consequent_codes' => $consequent,
                'confidence' => round($confidence, 4),
                'antecedent_support_count' => $antecedentSupportCount, // Diubah dari antecedent_support
                'union_support_count' => $unionSupportCount,       // Diubah dari union_support
                'support_percentage' => round($supportPercentageUnion, 4),
                'lift' => self::calculateLift($antecedent, $consequent, $antecedentSupportCount, $unionSupportCount, $totalTransactions) // Pass counts
            ];
        }
        return null;
    }

    /**
     * Menghitung lift dari rule (PUBLIC STATIC)
     * Dioptimasi dengan menerima support counts
     */
    public static function calculateLift($antecedent, $consequent, $antecedentSupportCount, $unionSupportCount, $totalTransactions)
    {
        if ($antecedentSupportCount == 0 || $totalTransactions == 0) {
            return 0;
        }
        $consequentSupportCount = self::getSupportCount($consequent);
        if ($consequentSupportCount == 0) {
            return 0;
        }

        // Confidence = P(Consequent | Antecedent) = support(Antecedent U Consequent) / support(Antecedent)
        $confidence = $unionSupportCount / $antecedentSupportCount;
        
        // P(Consequent) = support(Consequent) / totalTransactions
        $consequentProbability = $consequentSupportCount / $totalTransactions;
        if ($consequentProbability == 0) {
            return 0; // Hindari division by zero jika P(Consequent) adalah 0
        }
        
        $lift = $confidence / $consequentProbability;
        return round($lift, 4);
    }
    public static function getBasicStatistics()
    {
        $totalTransactions = self::getTotalTransactions();
        $totalProducts = Produk::count();
        $avgProductsPerTransaction = 0;

        if ($totalTransactions > 0) {
            $totalProductsInTransactions = ProdukTransaksi::count(); // Jumlah baris di produk_transaksi
            $avgProductsPerTransaction = round($totalProductsInTransactions / $totalTransactions, 2);
        }

        return [
            'total_transactions' => $totalTransactions,
            'total_products' => $totalProducts,
            'avg_products_per_transaction' => $avgProductsPerTransaction,
            'products_by_category' => Produk::selectRaw('kategori_produk, COUNT(*) as count')
                ->groupBy('kategori_produk')
                ->pluck('count', 'kategori_produk')
                ->toArray()
        ];
    }

    public static function getTopSellingProducts($limit = 10)
    {
        // ... implementasi tidak berubah ...
        $totalTransactions = self::getTotalTransactions(); // Diperlukan untuk persentase
        if ($totalTransactions == 0) return []; // hindari division by zero

        $topProducts = ProdukTransaksi::selectRaw('kode_produk, COUNT(*) as frequency')
            ->groupBy('kode_produk')
            ->orderBy('frequency', 'desc')
            ->limit($limit)
            ->get();

        $produkNama = Produk::pluck('nama_produk', 'kode_produk')->toArray();

        $result = [];
        foreach ($topProducts as $product) {
            $result[] = [
                'kode_produk' => $product->kode_produk,
                'nama_produk' => $produkNama[$product->kode_produk] ?? $product->kode_produk,
                'frequency' => $product->frequency,
                'percentage' => round(($product->frequency / $totalTransactions) * 100, 2)
            ];
        }
        return $result;
    }
}