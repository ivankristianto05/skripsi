<?php
namespace App\Services;

use App\Models\Produk;

class AprioriService
{
    public static function getCustomItemsets($minSupport = 0)
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

        // Menghilangkan duplikasi (untuk itemset 1 biasanya tidak perlu, tapi untuk konsistensi)
        $itemset1 = self::removeDuplicateArrays($itemset1);
        $itemset2 = self::removeDuplicateArrays($itemset2);
        $itemset3 = self::removeDuplicateArrays($itemset3);
        
        // Mengurutkan itemset berdasarkan kategori produk
        $itemset1 = self::sortItemsetByCategory($itemset1, $produkKategori);
        $itemset2 = self::sortItemsetByCategory($itemset2, $produkKategori);
        $itemset3 = self::sortItemsetByCategory($itemset3, $produkKategori);

        // Menerjemahkan itemset dari kode produk ke nama produk
        $frequentItemsets = [
            'itemsets_1' => self::translateItemsetsToNames($itemset1, $produkNama),
            'itemsets_2' => self::translateItemsetsToNames($itemset2, $produkNama),
            'itemsets_3' => self::translateItemsetsToNames($itemset3, $produkNama),
        ];

        return $frequentItemsets;
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

    // Fungsi untuk menerjemahkan kode produk menjadi nama produk
    private static function translateItemsetsToNames($itemsets, $produkNama)
    {
        $translatedItemsets = [];

        foreach ($itemsets as $itemset) {
            $translatedCombination = [];

            foreach ($itemset as $kodeProduk) {
                // Gunakan nama produk jika tersedia, jika tidak gunakan kode produk
                $translatedCombination[] = $produkNama[$kodeProduk] ?? $kodeProduk;
            }

            // Gabungkan nama produk dengan separator yang konsisten
            $translatedItemsets[] = implode(' - ', $translatedCombination);
        }

        return $translatedItemsets;
    }
}