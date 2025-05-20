<?php
namespace App\Services;

use App\Models\Produk;
use App\Models\ProdukTransaksi;

class AprioriService
{
    public static function getFrequentItemsets($minSupport = 0, $filterByKategori = true)
{
    // Ambil daftar kategori produk (kode_produk => kategori)
    $produkKategori = Produk::pluck('kategori_produk', 'kode_produk')->toArray();

    // Ambil nama produk berdasarkan kode_produk
    $produkNama = Produk::pluck('nama_produk', 'kode_produk')->toArray();

    // Ambil transaksi dan kelompokkan berdasarkan kode_transaksi
    $transaksiData = ProdukTransaksi::all()
        ->groupBy('kode_transaksi') // Kelompokkan berdasarkan kode_transaksi
        ->map(function ($items) use ($produkKategori, $produkNama) {
            $produkDalamTransaksi = [];

            foreach ($items as $item) {
                $kode = $item->kode_produk;
                $namaProduk = $produkNama[$kode] ?? null; // Menggunakan nama produk

                $produkDalamTransaksi[$namaProduk] = $namaProduk;
            }

            return array_values($produkDalamTransaksi); // Kembalikan array produk unik
        });

    if ($transaksiData->isEmpty()) {
        return [
            'itemsets_1' => [],
            'itemsets_2' => [],
            'itemsets_3' => []
        ];
    }

    // Hitung 1-itemset (Frequent 1-itemsets)
    $itemCount = [];
    foreach ($transaksiData as $produkList) {
        foreach ($produkList as $produk) {
            $itemCount[$produk] = ($itemCount[$produk] ?? 0) + 1;
        }
    }

    // Filter berdasarkan support minimum
    $frequent1 = array_filter($itemCount, fn($count) => $count >= $minSupport);
    //dd($frequent1);
    // Hitung 2-itemset (Frequent 2-itemsets) dengan pembenaran urutan
    $pairCount = [];
    foreach ($transaksiData as $produkList) {
        $produkList = array_unique($produkList); // Menghindari duplikasi produk dalam transaksi

        // Hitung kombinasi pasangan produk (2-itemset)
        for ($i = 0; $i < count($produkList); $i++) {
            for ($j = $i + 1; $j < count($produkList); $j++) {
                // Buat pasangan produk sesuai urutan kategori (pembenaran urutan)
                if ($produkList[$i] !== $produkList[$j]) {  // Tidak ada produk yang sama
                    $pair = [$produkList[$i], $produkList[$j]];

                    // Konversi nama produk menjadi kode produk untuk mendapatkan kategori
                    $kodeProduk1 = array_search($produkList[$i], $produkNama);
                    $kodeProduk2 = array_search($produkList[$j], $produkNama);

                    // Menggunakan kode produk untuk pembenaran urutan kategori
                    $pair = self::sortItemsetByCategory([$kodeProduk1, $kodeProduk2], $produkKategori);

                    $key = implode(',', $pair);
                    $pairCount[$key] = ($pairCount[$key] ?? 0) + 1;
                }
            }
        }
    }

    // Merging duplicate combinations, now passing $produkKategori
    $frequent2 = self::mergeDuplicateItemsets($pairCount, $minSupport, $produkKategori);

    // Hitung 3-itemset (Frequent 3-itemsets) dengan pembenaran urutan
    $tripleCount = [];
    foreach ($transaksiData as $produkList) {
        $produkList = array_unique($produkList);  // Menghindari duplikasi produk dalam transaksi

        // Hitung kombinasi tiga produk (3-itemset)
        for ($i = 0; $i < count($produkList); $i++) {
            for ($j = $i + 1; $j < count($produkList); $j++) {
                for ($k = $j + 1; $k < count($produkList); $k++) {
                    // Gunakan nama produk, bukan kode produk
                    if ($produkList[$i] !== $produkList[$j] && $produkList[$i] !== $produkList[$k] && $produkList[$j] !== $produkList[$k]) {
                        $triple = [$produkList[$i], $produkList[$j], $produkList[$k]];

                        // Konversi nama produk menjadi kode produk untuk mendapatkan kategori
                        $kodeProduk1 = array_search($produkList[$i], $produkNama);
                        $kodeProduk2 = array_search($produkList[$j], $produkNama);
                        $kodeProduk3 = array_search($produkList[$k], $produkNama);

                        // Menggunakan kode produk untuk pembenaran urutan kategori
                        $triple = self::sortItemsetByCategory([$kodeProduk1, $kodeProduk2, $kodeProduk3], $produkKategori);

                        $key = implode(',', $triple);
                        $tripleCount[$key] = ($tripleCount[$key] ?? 0) + 1;
                    }
                }
            }
        }
    }

    // Merging duplicate combinations for 3-itemsets, now passing $produkKategori
    $frequent3 = self::mergeDuplicateItemsets($tripleCount, $minSupport, $produkKategori);
    //dd($frequent3);
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

    // Fungsi untuk membenarkan urutan itemset berdasarkan kategori produk
private static function sortItemsetByCategory($itemset, $produkKategori)
{
    // Tentukan urutan kategori yang diinginkan (misalnya tembakau > filter > kertas)
    $kategoriUrutan = ['tembakau', 'filter', 'kertas'];

    // Ambil kategori produk untuk setiap produk dalam itemset
    $category1 = $produkKategori[$itemset[0]] ?? null;
    $category2 = $produkKategori[$itemset[1]] ?? null;
    // Jika kategori produk ada, bandingkan berdasarkan urutan kategori yang sudah ditentukan
    if ($category1 && $category2) {
        // Dapatkan posisi kategori dalam urutan yang diinginkan
        $index1 = array_search($category1, $kategoriUrutan);
        $index2 = array_search($category2, $kategoriUrutan);

        // Jika kategori pertama memiliki indeks lebih besar daripada kategori kedua, tukar posisi itemset
        if ($index1 > $index2) {
            return [$itemset[1], $itemset[0]]; // Membalik urutan itemset
        }
    }

    return $itemset; // Jika urutan sudah benar, tidak perlu diubah
}


    // Fungsi untuk menggabungkan duplikasi itemset 2 dan 3 produk
    private static function mergeDuplicateItemsets($itemsets, $minSupport, $produkKategori)
{
    $mergedItemsets = [];
    foreach ($itemsets as $key => $count) {
        $pair = explode(',', $key);

        // Urutkan pasangan itemset agar tidak terbalik
        sort($pair);  // Ini memastikan urutan pasangan produk tidak terbalik, namun kita masih perlu memperbaiki urutan kategori produk

        // Memperbaiki urutan berdasarkan kategori produk
        $pair = self::sortItemsetByCategory($pair, $produkKategori); // Memastikan urutan sesuai kategori setelah penggabungan

        // Gabungkan kembali pasangan yang sudah ada
        $key = implode(',', $pair);

        if (!isset($mergedItemsets[$key])) {
            $mergedItemsets[$key] = 0;
        }

        $mergedItemsets[$key] += $count;
    }
    //dd($mergedItemsets);
    // Filter berdasarkan minimum support
    return array_filter($mergedItemsets, fn($count) => $count >= $minSupport);
}

public static function generateRules($minSupport = 0, $minConfidence = 0)
{
    // Ambil frequent itemsets
    $frequentItemsets = self::getFrequentItemsets($minSupport);

    // Ambil data transaksi untuk total transaksi
    $transaksiData = ProdukTransaksi::all()->groupBy('kode_transaksi')->map(fn($items) => $items->pluck('kode_produk')->unique()->values()->toArray());
    $totalTransaksi = count($transaksiData);

    // Ambil nama produk berdasarkan kode_produk
    $produkNama = Produk::pluck('nama_produk', 'kode_produk')->toArray();

    // Hitung support untuk setiap item menggunakan nama produk sebagai key
    $supportLookup = [];
    foreach ($frequentItemsets['itemsets_1'] ?? [] as $item => $count) {
        // Menggunakan nama produk berdasarkan kode produk
        $namaProduk = $produkNama[$item] ?? $item;  
        $supportLookup[$namaProduk] = $count / $totalTransaksi;
    }

    // Menyimpan aturan yang dihasilkan
    $rules = [];

    // Hitung association rules dari frequent 2-itemsets
    foreach ($frequentItemsets['itemsets_2'] ?? [] as $pairKey => $count) {
        [$a, $b] = explode(',', $pairKey);  // Membagi pasangan itemset menjadi A dan B
        $supportAB = $count / $totalTransaksi;

        // Terjemahkan kode produk menjadi nama produk
        $namaProdukA = $produkNama[$a] ?? $a;  // Mengambil nama produk untuk A
        $namaProdukB = $produkNama[$b] ?? $b;  // Mengambil nama produk untuk B

        // Periksa apakah nama produk A dan B ada di supportLookup
        $supportA = $supportLookup[$namaProdukA] ?? 0;
        $supportB = $supportLookup[$namaProdukB] ?? 0;

        // Confidence (A => B) = Support(A ∩ B) / Support(A)
        $confidenceAtoB = ($supportA > 0) ? $supportAB / $supportA : 0;
        
        // Lift (A => B) = Confidence(A => B) / Support(B)
        $liftAtoB = ($supportB > 0) ? $confidenceAtoB / $supportB : 0;

        // Hanya tambahkan aturan satu arah jika confidence lebih besar dari minimum
        if ($confidenceAtoB >= $minConfidence) {
            $rules[] = [
                'antecedent' => self::translateProductCodesToNames([$a]),  // Menggunakan nama produk
                'consequent' => self::translateProductCodesToNames([$b]),  // Menggunakan nama produk
                'antecedent_names' => self::translateProductCodesToNames([$a]),  // Menggunakan nama produk
                'consequent_names' => self::translateProductCodesToNames([$b]),  // Menggunakan nama produk
                'support' => $supportAB,
                'confidence' => $confidenceAtoB,
                'lift' => $liftAtoB,
            ];
        }
    }

    // Hitung association rules dari frequent 3-itemsets
    foreach ($frequentItemsets['itemsets_3'] ?? [] as $key => $count) {
        $items = explode(',', $key);  // Memecah key itemset menjadi 3 produk
        $supportABC = $count / $totalTransaksi;

        // Hanya proses jika itemset memiliki 3 elemen
        if (count($items) == 3) {
            // Terjemahkan kode produk menjadi nama produk
            $namaProdukA = $produkNama[$items[0]] ?? $items[0];  // Mengambil nama produk untuk A
            $namaProdukB = $produkNama[$items[1]] ?? $items[1];  // Mengambil nama produk untuk B
            $namaProdukC = $produkNama[$items[2]] ?? $items[2];  // Mengambil nama produk untuk C

            // Dapatkan support untuk setiap produk dalam itemset
            $supportA = $supportLookup[$namaProdukA] ?? 0;
            $supportB = $supportLookup[$namaProdukB] ?? 0;
            $supportC = $supportLookup[$namaProdukC] ?? 0;

            // Confidence untuk 3-itemset (A => B, C) = Support(A ∩ B ∩ C) / Support(A)
            $confidenceAtoBC = ($supportA > 0) ? $supportABC / $supportA : 0;
            $confidenceBtoAC = ($supportB > 0) ? $supportABC / $supportB : 0;
            $confidenceCtoAB = ($supportC > 0) ? $supportABC / $supportC : 0;

            // Lift untuk 3-itemset
            $liftAtoBC = ($supportB > 0) ? $confidenceAtoBC / $supportB : 0;
            $liftBtoAC = ($supportA > 0) ? $confidenceBtoAC / $supportA : 0;
            $liftCtoAB = ($supportA > 0) ? $confidenceCtoAB / $supportA : 0;

            // Hanya tambahkan aturan satu arah jika confidence lebih besar dari minimum
            if ($confidenceAtoBC >= $minConfidence) {
                $rules[] = [
                    'antecedent' => self::translateProductCodesToNames([$items[0]]),  // Menggunakan nama produk
                    'consequent' => self::translateProductCodesToNames([$items[1], $items[2]]),  // Menggunakan nama produk
                    'antecedent_names' => self::translateProductCodesToNames([$items[0]]),  // Menggunakan nama produk
                    'consequent_names' => self::translateProductCodesToNames([$items[1], $items[2]]),  // Menggunakan nama produk
                    'support' => $supportABC,
                    'confidence' => $confidenceAtoBC,
                    'lift' => $liftAtoBC,
                ];
            }
        }
    }

    return $rules;
}

}
