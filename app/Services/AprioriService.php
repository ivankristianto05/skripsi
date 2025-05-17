<?php
namespace App\Services;

use App\Models\Produk;
use App\Models\ProdukTransaksi;

class AprioriService
{
    public static function getFrequentItemsets($minSupport = 1, $filterByKategori = true)
    {
        // Ambil daftar kategori produk (kode_produk => kategori)
        $produkKategori = Produk::pluck('kategori_produk', 'kode_produk')->toArray();

        // Ambil nama produk berdasarkan kode_produk
        $produkNama = Produk::pluck('nama_produk', 'kode_produk')->toArray();

        // Ambil transaksi dan kelompokkan berdasarkan kode_transaksi
        $transaksiData = ProdukTransaksi::all()
            ->groupBy('kode_transaksi') // Kelompokkan berdasarkan kode_transaksi
            ->map(function ($items) use ($produkKategori, $produkNama, $filterByKategori) {
                $produkDalamTransaksi = [];

                foreach ($items as $item) {
                    $kode = $item->kode_produk;
                    $namaProduk = $produkNama[$kode] ?? null; // Menggunakan nama produk

                    // Jika filter kategori aktif, hanya ambil satu produk per kategori
                    if ($filterByKategori) {
                        $kategori = $produkKategori[$kode] ?? null;
                        if ($kategori && !isset($produkDalamTransaksi[$kategori])) {
                            $produkDalamTransaksi[$kategori] = $namaProduk;
                        }
                    } else {
                        $produkDalamTransaksi[$namaProduk] = $namaProduk;
                    }
                }

                return array_values($produkDalamTransaksi); // Kembalikan array produk unik
            })->filter(function ($itemset) {
                return count($itemset) > 1; // Transaksi dengan minimal 2 produk
            });

        if ($transaksiData->isEmpty()) {
            return [
                'itemsets_1' => [],
                'itemsets_2' => [],
                'itemsets_3' => []  // Menambahkan 3-itemset ke dalam array hasil
            ];
        }

        // Hitung 1-itemset (Frequent 1-itemsets)
        $itemCount = [];
        foreach ($transaksiData as $produkList) {
            foreach ($produkList as $produk) {
                $itemCount[$produk] = ($itemCount[$produk] ?? 0) + 1;
            }
        }

        $frequent1 = array_filter($itemCount, fn($count) => $count >= $minSupport);

        // Hitung 2-itemset (Frequent 2-itemsets)
        $pairCount = [];
        foreach ($transaksiData as $produkList) {
            $produkList = array_unique($produkList); // Menghindari duplikasi produk dalam transaksi

            // Hitung kombinasi pasangan produk (2-itemset)
            for ($i = 0; $i < count($produkList); $i++) {
                for ($j = $i + 1; $j < count($produkList); $j++) {
                    // Buat pasangan produk sesuai urutan transaksi tanpa sorting
                    $pair = [$produkList[$i], $produkList[$j]]; 
                    $key = implode(',', $pair); 
                    $pairCount[$key] = ($pairCount[$key] ?? 0) + 1;
                }
            }
        }
        $frequent2 = array_filter($pairCount, fn($count) => $count >= $minSupport);
        //dd($frequent2);
        // Hitung 3-itemset (Frequent 3-itemsets)
        $tripleCount = [];
        foreach ($transaksiData as $produkList) {
            $produkList = array_unique($produkList);  // Menghindari duplikasi produk dalam transaksi

            // Hitung kombinasi tiga produk (3-itemset)
            for ($i = 0; $i < count($produkList); $i++) {
                for ($j = $i + 1; $j < count($produkList); $j++) {
                    for ($k = $j + 1; $k < count($produkList); $k++) {
                        // Gunakan nama produk, bukan kode produk
                        $triple = [$produkList[$i], $produkList[$j], $produkList[$k]];
                        $key = implode(',', $triple);
                        $tripleCount[$key] = ($tripleCount[$key] ?? 0) + 1;
                    }
                }
            }
        }

        $frequent3 = array_filter($tripleCount, fn($count) => $count >= $minSupport);

        // Mengganti kode produk dengan nama produk untuk setiap itemset
        $frequentItemsets = [
            'itemsets_1' => self::translateProductCodesToNames($frequent1),
            'itemsets_2' => self::translateProductCodesToNames($frequent2),
            'itemsets_3' => self::translateProductCodesToNames($frequent3),
        ];

        return $frequentItemsets;
    }

    // Fungsi untuk mengganti kode produk menjadi nama produk
    private static function translateProductCodesToNames($itemsets)
    {
        // Ambil nama produk berdasarkan kode_produk
        $produkNama = Produk::pluck('nama_produk', 'kode_produk')->toArray();

        // Jika item adalah pasangan atau tiga produk, ubah setiap kode menjadi nama produk
        return array_map(function ($item) use ($produkNama) {
            if (is_array($item)) {
                return array_map(function ($kode) use ($produkNama) {
                    return $produkNama[$kode] ?? $kode;
                }, $item);
            }
            // Untuk itemset 1 (produk tunggal), langsung ganti kode menjadi nama produk
            return $produkNama[$item] ?? $item;
        }, $itemsets);
    }

public static function generateRules($minSupport = 0, $minConfidence = 0)
{
    // Ambil frequent itemsets
    $frequentItemsets = self::getFrequentItemsets($minSupport);

    // Ambil data transaksi untuk total transaksi
    $transaksiData = ProdukTransaksi::all()->groupBy('kode_transaksi')->map(fn($items) => $items->pluck('kode_produk')->unique()->values()->toArray());
    $totalTransaksi = count($transaksiData);

    // Hitung support untuk setiap item
    $supportLookup = [];
    foreach ($frequentItemsets['itemsets_1'] ?? [] as $item => $count) {
        $supportLookup[$item] = $count / $totalTransaksi;
    }

    // Ambil nama produk berdasarkan kode_produk
    $produkNama = Produk::pluck('nama_produk', 'kode_produk')->toArray();

    $rules = [];

    // Hitung association rules dari frequent 2-itemsets
    foreach ($frequentItemsets['itemsets_2'] ?? [] as $pairKey => $count) {
        [$a, $b] = explode(',', $pairKey);
        $supportAB = $count / $totalTransaksi;

        $confidenceAtoB = $supportAB / ($supportLookup[$a] ?? 1);
        $liftAtoB = $confidenceAtoB / ($supportLookup[$b] ?? 1);

        // Hanya tambahkan aturan satu arah: jika a maka b
        if ($confidenceAtoB >= $minConfidence) {
            $rules[] = [
                'antecedent' => [$a],
                'consequent' => [$b],
                'antecedent_names' => [$produkNama[$a] ?? $a],
                'consequent_names' => [$produkNama[$b] ?? $b],
                'support' => $supportAB,
                'confidence' => $confidenceAtoB,
                'lift' => $liftAtoB,
            ];
        }
    }

    // Hitung association rules dari frequent 3-itemsets
    foreach ($frequentItemsets['itemsets_3'] ?? [] as $key => $count) {
        $items = explode(',', $key);
        $supportABC = $count / $totalTransaksi;

        $confidenceAtoBC = $supportABC / ($supportLookup[$items[0]] ?? 1);
        $confidenceBtoAC = $supportABC / ($supportLookup[$items[1]] ?? 1);
        $confidenceCtoAB = $supportABC / ($supportLookup[$items[2]] ?? 1);

        $liftAtoBC = $confidenceAtoBC / ($supportLookup[$items[1]] ?? 1);
        $liftBtoAC = $confidenceBtoAC / ($supportLookup[$items[0]] ?? 1);
        $liftCtoAB = $confidenceCtoAB / ($supportLookup[$items[0]] ?? 1);

        // Hanya tambahkan aturan satu arah: jika a maka b dan c
        if ($confidenceAtoBC >= $minConfidence) {
            $rules[] = [
                'antecedent' => [$items[0]],
                'consequent' => [$items[1], $items[2]],
                'antecedent_names' => [$produkNama[$items[0]] ?? $items[0]],
                'consequent_names' => [$produkNama[$items[1]] ?? $items[1], $produkNama[$items[2]] ?? $items[2]],
                'support' => $supportABC,
                'confidence' => $confidenceAtoBC,
                'lift' => $liftAtoBC,
            ];
        }
    }

    return $rules;
}


}
