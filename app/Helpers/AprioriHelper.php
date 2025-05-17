<?php

namespace App\Helpers;

use App\Models\Transaksi;
use App\Models\Produk;

class AprioriHelper
{
    public static function generateFrequentItemsets($minSupport = 0.2)
    {
        $transaksis = Transaksi::with('produkTransaksis.produk')->get();
        $totalTransaksi = $transaksis->count();
        $baskets = [];

        // Bangun basket dan simpan kategori per produk
        foreach ($transaksis as $transaksi) {
            $items = [];

            foreach ($transaksi->produkTransaksis as $pt) {
                $items[] = [
                    'kode_produk' => $pt->produk->kode_produk,
                    'kategori_produk' => $pt->produk->kategori_produk
                ];
            }

            $baskets[] = $items;
        }

        $frequentItemsets = [];
        $itemCounts = [];

        // Step 1: 1-itemset
        foreach ($baskets as $basket) {
            foreach ($basket as $item) {
                $key = $item['kode_produk'];
                $itemCounts[$key] = ($itemCounts[$key] ?? 0) + 1;
            }
        }

        $frequentItems = [];
        foreach ($itemCounts as $item => $count) {
            $support = $count / $totalTransaksi;
            if ($support >= $minSupport) {
                $key = $item; // single item string
                $frequentItems[$key] = $support;
            }
        }

        // Simpan frequent 1-itemset
        foreach ($frequentItems as $item => $support) {
            $frequentItemsets[$item] = $support;
        }

        $currentLSet = array_keys($frequentItems);
        $k = 2;

        while (!empty($currentLSet)) {
            $candidates = self::generateCandidates($currentLSet, $k);
            $candidateCounts = [];

            foreach ($baskets as $basket) {
                $produkDalamBasket = array_column($basket, 'kode_produk');
                $kategoriDalamBasket = array_column($basket, 'kategori_produk');

                foreach ($candidates as $candidate) {
                    $kategoriCandidate = [];

                    foreach ($candidate as $kode) {
                        $produk = Produk::where('kode_produk', $kode)->first();
                        if ($produk) {
                            $kategoriCandidate[] = $produk->kategori_produk;
                        }
                    }

                    // Filter: jangan lanjut jika ada kategori sama
                    if (count($kategoriCandidate) !== count(array_unique($kategoriCandidate))) {
                        continue;
                    }

                    // Cek apakah kandidat subset dari basket
                    if (count(array_intersect($candidate, $produkDalamBasket)) === count($candidate)) {
                        $key = implode(',', $candidate);
                        $candidateCounts[$key] = ($candidateCounts[$key] ?? 0) + 1;
                    }
                }
            }

            $nextLSet = [];
            foreach ($candidateCounts as $key => $count) {
                $support = $count / $totalTransaksi;
                if ($support >= $minSupport) {
                    $frequentItemsets[$key] = $support;
                    $nextLSet[] = explode(',', $key);
                }
            }

            $currentLSet = $nextLSet;
            $k++;
        }

        return $frequentItemsets;
    }

    private static function generateCandidates($itemsets, $k)
    {
        $candidates = [];

        for ($i = 0; $i < count($itemsets); $i++) {
            for ($j = $i + 1; $j < count($itemsets); $j++) {
                $a = is_array($itemsets[$i]) ? $itemsets[$i] : explode(',', $itemsets[$i]);
                $b = is_array($itemsets[$j]) ? $itemsets[$j] : explode(',', $itemsets[$j]);

                $merged = array_unique(array_merge($a, $b));

                if (count($merged) === $k) {
                    sort($merged);
                    $candidates[] = $merged;
                }
            }
        }

        // Hilangkan duplikat
        $candidates = array_map("unserialize", array_unique(array_map("serialize", $candidates)));

        return $candidates;
    }
    public static function generateAssociationRules($frequentItemsets, $minConfidence = 0.5)
{
    $rules = [];

    foreach ($frequentItemsets as $itemset => $supportAB) {
        $items = explode(',', $itemset);

        if (count($items) < 2) continue;

        $subsets = self::getNonEmptyProperSubsets($items);

        foreach ($subsets as $antecedent) {
            $consequent = array_diff($items, $antecedent);

            if (empty($consequent)) continue;

            sort($antecedent);
            sort($consequent);

            $antecedentKey = implode(',', $antecedent);
            $consequentKey = implode(',', $consequent);
            $fullKey = implode(',', $items);

            if (!isset($frequentItemsets[$antecedentKey]) || !isset($frequentItemsets[$consequentKey])) {
                continue;
            }

            $supportA = $frequentItemsets[$antecedentKey];
            $supportB = $frequentItemsets[$consequentKey];

            $confidence = $supportAB / $supportA;
            $lift = $confidence / $supportB;

            if ($confidence >= $minConfidence) {
                $rules[] = [
                    'antecedent' => $antecedent,
                    'consequent' => $consequent,
                    'support' => round($supportAB, 4),
                    'confidence' => round($confidence, 4),
                    'lift' => round($lift, 4),
                ];
            }
        }
    }

    return $rules;
}

private static function getNonEmptyProperSubsets($items)
{
    $results = [];
    $total = pow(2, count($items));

    for ($i = 1; $i < $total - 1; $i++) {
        $subset = [];

        for ($j = 0; $j < count($items); $j++) {
            if ($i & (1 << $j)) {
                $subset[] = $items[$j];
            }
        }

        $results[] = $subset;
    }

    return $results;
}

}
