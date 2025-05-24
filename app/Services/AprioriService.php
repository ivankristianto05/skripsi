<?php
namespace App\Services;

use App\Models\Produk;
use App\Models\Transaksi;
use App\Models\ProdukTransaksi;

class AprioriService
{
    public static function getCustomItemsets($minSupport = 0.1)
    {
        // Ambil daftar produk beserta kategorinya
        $produkKategori = Produk::pluck('kategori_produk', 'kode_produk')->toArray();
        $produkNama = Produk::pluck('nama_produk', 'kode_produk')->toArray();

        // Ambil semua produk yang ada di database
        $produk = Produk::all();

        $itemset1 = [];
        $itemset2 = [];
        $itemset3 = [];

        // Membuat 1-itemset (individual items)
        foreach ($produk as $produkItem) {
            $itemset1[] = [$produkItem->kode_produk];
        }

        // Membuat kombinasi 2-itemset dan 3-itemset dari produk dengan kategori berbeda
        foreach ($produk as $produkA) {
            foreach ($produk as $produkB) {
                // Pastikan produk A dan B berbeda kode dan berasal dari kategori yang berbeda
                if ($produkA->kode_produk != $produkB->kode_produk && 
                    $produkKategori[$produkA->kode_produk] != $produkKategori[$produkB->kode_produk]) {
                    
                    // Kombinasi 2-itemset
                    $combination2 = [$produkA->kode_produk, $produkB->kode_produk];
                    sort($combination2); // Urutkan untuk konsistensi
                    $itemset2[] = $combination2;

                    foreach ($produk as $produkC) {
                        // Pastikan produk C berbeda dari A dan B, dan berasal dari kategori yang berbeda
                        if ($produkC->kode_produk != $produkA->kode_produk && 
                            $produkC->kode_produk != $produkB->kode_produk &&
                            $produkKategori[$produkC->kode_produk] != $produkKategori[$produkA->kode_produk] &&
                            $produkKategori[$produkC->kode_produk] != $produkKategori[$produkB->kode_produk]) {
                            
                            // Kombinasi 3-itemset
                            $combination3 = [$produkA->kode_produk, $produkB->kode_produk, $produkC->kode_produk];
                            sort($combination3); // Urutkan untuk konsistensi
                            $itemset3[] = $combination3;
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

    // Fungsi tambahan untuk mendapatkan association rules
    public static function generateAssociationRules($minSupport = 0.1, $minConfidence = 0.5)
    {
        $frequentItemsets = self::getCustomItemsets($minSupport);
        $rules = [];

        // Generate rules dari 2-itemsets
        foreach ($frequentItemsets['itemsets_2'] as $itemset) {
            $codes = $itemset['itemset_codes'];
            if (count($codes) == 2) {
                // Rule A -> B
                $ruleAB = self::calculateRule([$codes[0]], [$codes[1]], $minConfidence);
                if ($ruleAB) $rules[] = $ruleAB;

                // Rule B -> A
                $ruleBA = self::calculateRule([$codes[1]], [$codes[0]], $minConfidence);
                if ($ruleBA) $rules[] = $ruleBA;
            }
        }

        // Generate rules dari 3-itemsets
        foreach ($frequentItemsets['itemsets_3'] as $itemset) {
            $codes = $itemset['itemset_codes'];
            if (count($codes) == 3) {
                // Rules dengan 1 antecedent, 2 consequent
                for ($i = 0; $i < 3; $i++) {
                    $antecedent = [$codes[$i]];
                    $consequent = array_values(array_diff($codes, $antecedent));
                    $rule = self::calculateRule($antecedent, $consequent, $minConfidence);
                    if ($rule) $rules[] = $rule;
                }

                // Rules dengan 2 antecedent, 1 consequent
                for ($i = 0; $i < 3; $i++) {
                    $consequent = [$codes[$i]];
                    $antecedent = array_values(array_diff($codes, $consequent));
                    $rule = self::calculateRule($antecedent, $consequent, $minConfidence);
                    if ($rule) $rules[] = $rule;
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
            
            return [
                'antecedent' => array_map(fn($code) => $produkNama[$code] ?? $code, $antecedent),
                'consequent' => array_map(fn($code) => $produkNama[$code] ?? $code, $consequent),
                'antecedent_codes' => $antecedent,
                'consequent_codes' => $consequent,
                'antecedent_support' => $antecedentSupport,
                'union_support' => $unionSupport,
                'confidence' => round($confidence, 4),
                'total_transactions' => self::getTotalTransactions()
            ];
        }
        
        return null;
    }
}