<?php
namespace App\Services;

use App\Models\Produk;
use App\Models\Transaksi;
use App\Models\ProdukTransaksi;

class AprioriService
{
    public static function getCustomItemsets($minSupport = 0.1, $targetTembakau = null)
    {
        // Ambil daftar produk beserta kategorinya
        $produkKategori = Produk::pluck('kategori_produk', 'kode_produk')->toArray();
        $produkNama = Produk::pluck('nama_produk', 'kode_produk')->toArray();

        // Ambil semua produk yang ada di database
        $produk = Produk::all();

        $itemset1 = [];
        $itemset2 = [];
        $itemset3 = [];

        // Filter produk berdasarkan target tembakau jika ada
        if ($targetTembakau) {
            // Membuat 1-itemset hanya untuk target tembakau
            $itemset1[] = [$targetTembakau];

            // Membuat kombinasi 2-itemset dan 3-itemset yang melibatkan target tembakau
            foreach ($produk as $produkB) {
                // Pastikan produk B berbeda dari target tembakau dan berasal dari kategori yang berbeda
                if ($produkB->kode_produk != $targetTembakau && 
                    $produkKategori[$targetTembakau] != $produkKategori[$produkB->kode_produk]) {
                    
                    // Kombinasi 2-itemset dengan target tembakau
                    $combination2 = [$targetTembakau, $produkB->kode_produk];
                    sort($combination2);
                    $itemset2[] = $combination2;

                    foreach ($produk as $produkC) {
                        // Pastikan produk C berbeda dari target tembakau dan B, dan berasal dari kategori yang berbeda
                        if ($produkC->kode_produk != $targetTembakau && 
                            $produkC->kode_produk != $produkB->kode_produk &&
                            $produkKategori[$produkC->kode_produk] != $produkKategori[$targetTembakau] &&
                            $produkKategori[$produkC->kode_produk] != $produkKategori[$produkB->kode_produk]) {
                            
                            // Kombinasi 3-itemset dengan target tembakau
                            $combination3 = [$targetTembakau, $produkB->kode_produk, $produkC->kode_produk];
                            sort($combination3);
                            $itemset3[] = $combination3;
                        }
                    }
                }
            }
        } else {
            // Logika asli jika tidak ada filter tembakau
            foreach ($produk as $produkItem) {
                $itemset1[] = [$produkItem->kode_produk];
            }

            foreach ($produk as $produkA) {
                foreach ($produk as $produkB) {
                    if ($produkA->kode_produk != $produkB->kode_produk && 
                        $produkKategori[$produkA->kode_produk] != $produkKategori[$produkB->kode_produk]) {
                        
                        $combination2 = [$produkA->kode_produk, $produkB->kode_produk];
                        sort($combination2);
                        $itemset2[] = $combination2;

                        foreach ($produk as $produkC) {
                            if ($produkC->kode_produk != $produkA->kode_produk && 
                                $produkC->kode_produk != $produkB->kode_produk &&
                                $produkKategori[$produkC->kode_produk] != $produkKategori[$produkA->kode_produk] &&
                                $produkKategori[$produkC->kode_produk] != $produkKategori[$produkB->kode_produk]) {
                                
                                $combination3 = [$produkA->kode_produk, $produkB->kode_produk, $produkC->kode_produk];
                                sort($combination3);
                                $itemset3[] = $combination3;
                            }
                        }
                    }
                }
            }
        }

        // Menghilangkan duplikasi
        $itemset1 = self::removeDuplicateArrays($itemset1);
        $itemset2 = self::removeDuplicateArrays($itemset2);
        $itemset3 = self::removeDuplicateArrays($itemset3);
        
        // Mengurutkan itemset berdasarkan kategori produk
        $itemset1 = self::sortItemsetByCategory($itemset1, $produkKategori);
        $itemset2 = self::sortItemsetByCategory($itemset2, $produkKategori);
        $itemset3 = self::sortItemsetByCategory($itemset3, $produkKategori);

        // Menghitung support untuk setiap itemset
        $itemset1WithSupport = self::calculateSupport($itemset1, $minSupport);
        $itemset2WithSupport = self::calculateSupport($itemset2, $minSupport);
        $itemset3WithSupport = self::calculateSupport($itemset3, $minSupport);

        // Menerjemahkan itemset dari kode produk ke nama produk
        $frequentItemsets = [
            'itemsets_1' => self::translateItemsetsWithSupport($itemset1WithSupport, $produkNama),
            'itemsets_2' => self::translateItemsetsWithSupport($itemset2WithSupport, $produkNama),
            'itemsets_3' => self::translateItemsetsWithSupport($itemset3WithSupport, $produkNama),
            'total_transactions' => self::getTotalTransactions(),
            'target_tembakau' => $targetTembakau ? $produkNama[$targetTembakau] : null,
        ];

        return $frequentItemsets;
    }

    // Fungsi untuk menghitung support dari itemset
    private static function calculateSupport($itemsets, $minSupport = 0.1)
    {
        $totalTransactions = self::getTotalTransactions();
        $itemsetsWithSupport = [];

        foreach ($itemsets as $itemset) {
            $supportCount = self::getSupportCount($itemset);
            $supportPercentage = $totalTransactions > 0 ? $supportCount / $totalTransactions : 0;

            // Hanya ambil itemset yang memenuhi minimum support
            if ($supportPercentage >= $minSupport) {
                $itemsetsWithSupport[] = [
                    'itemset' => $itemset,
                    'support_count' => $supportCount,
                    'support_percentage' => round($supportPercentage, 4),
                    'total_transactions' => $totalTransactions
                ];
            }
        }

        // Urutkan berdasarkan support percentage (descending)
        usort($itemsetsWithSupport, function($a, $b) {
            return $b['support_percentage'] <=> $a['support_percentage'];
        });

        return $itemsetsWithSupport;
    }

    // Fungsi untuk mendapatkan jumlah total transaksi
    private static function getTotalTransactions()
    {
        return Transaksi::count();
    }

    // Fungsi untuk menghitung support count dari itemset tertentu
    private static function getSupportCount($itemset)
    {
        // Ambil semua transaksi beserta produk yang dibeli
        $transaksi = Transaksi::with('produkTransaksis.produk')->get();
        $supportCount = 0;

        foreach ($transaksi as $t) {
            // Ambil kode produk dari transaksi ini
            $produkDalamTransaksi = $t->produkTransaksis->pluck('produk.kode_produk')->toArray();
            
            // Cek apakah semua item dalam itemset ada dalam transaksi ini
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

    // Fungsi untuk menerjemahkan itemset dengan support ke nama produk
    private static function translateItemsetsWithSupport($itemsetsWithSupport, $produkNama)
    {
        $translatedItemsets = [];

        foreach ($itemsetsWithSupport as $itemsetData) {
            $translatedCombination = [];

            foreach ($itemsetData['itemset'] as $kodeProduk) {
                // Gunakan nama produk jika tersedia, jika tidak gunakan kode produk
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

    // Fungsi untuk menghilangkan duplikasi array
    private static function removeDuplicateArrays($arrays)
    {
        $uniqueArrays = [];
        $seenCombinations = [];

        foreach ($arrays as $array) {
            // Buat signature dari array untuk deteksi duplikasi
            $signature = implode('|', $array);
            
            if (!in_array($signature, $seenCombinations)) {
                $seenCombinations[] = $signature;
                $uniqueArrays[] = $array;
            }
        }

        return $uniqueArrays;
    }

    // Fungsi untuk mengurutkan itemset berdasarkan kategori
    private static function sortItemsetByCategory($itemsets, $produkKategori)
    {
        // Definisi urutan kategori
        $kategoriUrutan = ['tembakau' => 1, 'filter' => 2, 'kertas' => 3];

        // Urutkan setiap itemset berdasarkan kategori produk
        foreach ($itemsets as &$itemset) {
            usort($itemset, function($kodeProdukA, $kodeProdukB) use ($produkKategori, $kategoriUrutan) {
                $kategoriA = $produkKategori[$kodeProdukA] ?? 'unknown';
                $kategoriB = $produkKategori[$kodeProdukB] ?? 'unknown';
                
                $prioritasA = $kategoriUrutan[$kategoriA] ?? 999;
                $prioritasB = $kategoriUrutan[$kategoriB] ?? 999;
                
                return $prioritasA <=> $prioritasB;
            });
        }

        return $itemsets;
    }

    // Fungsi tambahan untuk mendapatkan association rules dengan filter
    public static function generateAssociationRules($minSupport = 0.1, $minConfidence = 0.5, $targetTembakau = null)
    {
        $frequentItemsets = self::getCustomItemsets($minSupport, $targetTembakau);
        $rules = [];

        // Generate rules dari 2-itemsets
        foreach ($frequentItemsets['itemsets_2'] as $itemset) {
            $codes = $itemset['itemset_codes'];
            if (count($codes) == 2) {
                // Jika ada filter tembakau, pastikan salah satu adalah tembakau target
                if ($targetTembakau) {
                    if (in_array($targetTembakau, $codes)) {
                        // Rule tembakau -> produk lain
                        if ($codes[0] == $targetTembakau) {
                            $rule = self::calculateRule([$codes[0]], [$codes[1]], $minConfidence);
                            if ($rule) $rules[] = $rule;
                        } else {
                            $rule = self::calculateRule([$codes[1]], [$codes[0]], $minConfidence);
                            if ($rule) $rules[] = $rule;
                        }
                        
                        // Rule produk lain -> tembakau
                        if ($codes[0] == $targetTembakau) {
                            $rule = self::calculateRule([$codes[1]], [$codes[0]], $minConfidence);
                            if ($rule) $rules[] = $rule;
                        } else {
                            $rule = self::calculateRule([$codes[0]], [$codes[1]], $minConfidence);
                            if ($rule) $rules[] = $rule;
                        }
                    }
                } else {
                    // Logika asli tanpa filter
                    $ruleAB = self::calculateRule([$codes[0]], [$codes[1]], $minConfidence);
                    if ($ruleAB) $rules[] = $ruleAB;

                    $ruleBA = self::calculateRule([$codes[1]], [$codes[0]], $minConfidence);
                    if ($ruleBA) $rules[] = $ruleBA;
                }
            }
        }

        // Generate rules dari 3-itemsets
        foreach ($frequentItemsets['itemsets_3'] as $itemset) {
            $codes = $itemset['itemset_codes'];
            if (count($codes) == 3) {
                // Jika ada filter tembakau, pastikan tembakau target ada dalam itemset
                if ($targetTembakau && in_array($targetTembakau, $codes)) {
                    // Rules dengan tembakau sebagai antecedent
                    $consequent = array_values(array_diff($codes, [$targetTembakau]));
                    $rule = self::calculateRule([$targetTembakau], $consequent, $minConfidence);
                    if ($rule) $rules[] = $rule;

                    // Rules dengan tembakau sebagai consequent
                    foreach ($codes as $kodeProduk) {
                        if ($kodeProduk != $targetTembakau) {
                            $antecedent = [$kodeProduk];
                            $consequent = [$targetTembakau];
                            $rule = self::calculateRule($antecedent, $consequent, $minConfidence);
                            if ($rule) $rules[] = $rule;
                        }
                    }
                } elseif (!$targetTembakau) {
                    // Logika asli tanpa filter
                    for ($i = 0; $i < 3; $i++) {
                        $antecedent = [$codes[$i]];
                        $consequent = array_values(array_diff($codes, $antecedent));
                        $rule = self::calculateRule($antecedent, $consequent, $minConfidence);
                        if ($rule) $rules[] = $rule;
                    }

                    for ($i = 0; $i < 3; $i++) {
                        $consequent = [$codes[$i]];
                        $antecedent = array_values(array_diff($codes, $consequent));
                        $rule = self::calculateRule($antecedent, $consequent, $minConfidence);
                        if ($rule) $rules[] = $rule;
                    }
                }
            }
        }

        // Urutkan rules berdasarkan confidence (descending)
        usort($rules, function($a, $b) {
            return $b['confidence'] <=> $a['confidence'];
        });

        return $rules;
    }

    // Fungsi untuk menghitung confidence dari rule
    private static function calculateRule($antecedent, $consequent, $minConfidence)
    {
        $antecedentSupport = self::getSupportCount($antecedent);
        $unionSupport = self::getSupportCount(array_merge($antecedent, $consequent));
        
        if ($antecedentSupport == 0) return null;
        
        $confidence = $unionSupport / $antecedentSupport;
        
        if ($confidence >= $minConfidence) {
            $produkNama = Produk::pluck('nama_produk', 'kode_produk')->toArray();
            $totalTransactions = self::getTotalTransactions();
            
            return [
                'antecedent' => array_map(fn($code) => $produkNama[$code] ?? $code, $antecedent),
                'consequent' => array_map(fn($code) => $produkNama[$code] ?? $code, $consequent),
                'antecedent_codes' => $antecedent,
                'consequent_codes' => $consequent,
                'confidence' => round($confidence, 4),
                'antecedent_support' => $antecedentSupport,
                'union_support' => $unionSupport, // Changed from 'support' to 'union_support'
                'support_percentage' => round($unionSupport / $totalTransactions, 4),
                'lift' => self::calculateLift($antecedent, $consequent)
            ];
        }
        
        return null;
    }

    // Fungsi untuk menghitung lift dari rule
    private static function calculateLift($antecedent, $consequent)
    {
        $totalTransactions = self::getTotalTransactions();
        $antecedentSupport = self::getSupportCount($antecedent);
        $consequentSupport = self::getSupportCount($consequent);
        $unionSupport = self::getSupportCount(array_merge($antecedent, $consequent));
        
        if ($antecedentSupport == 0 || $consequentSupport == 0 || $totalTransactions == 0) {
            return 0;
        }
        
        $confidence = $unionSupport / $antecedentSupport;
        $consequentProbability = $consequentSupport / $totalTransactions;
        
        if ($consequentProbability == 0) return 0;
        
        $lift = $confidence / $consequentProbability;
        
        return round($lift, 4);
    }

    // Fungsi tambahan untuk mendapatkan statistik dasar
    public static function getBasicStatistics()
    {
        $totalTransactions = self::getTotalTransactions();
        $totalProducts = Produk::count();
        $avgProductsPerTransaction = 0;
        
        if ($totalTransactions > 0) {
            $totalProductsInTransactions = ProdukTransaksi::count();
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

    // Fungsi untuk mendapatkan top selling products
    public static function getTopSellingProducts($limit = 10)
    {
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
                'percentage' => round($product->frequency / self::getTotalTransactions() * 100, 2)
            ];
        }
        
        return $result;
    }
}