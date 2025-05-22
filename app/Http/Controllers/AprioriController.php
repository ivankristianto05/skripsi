<?php
namespace App\Http\Controllers;

use App\Services\AprioriService;
use App\Models\Produk;
use App\Models\Transaksi;

class AprioriController extends Controller
{
    public function getFormattedTransaksi()
    {
        $transaksis = Transaksi::with('produkTransaksis')->get();

        $data = [];

        foreach ($transaksis as $transaksi) {
            $kodeTransaksi = $transaksi->kode_transaksi;
            $produkList = $transaksi->produkTransaksis->pluck('kode_produk')->toArray();

            $data[$kodeTransaksi] = $produkList;
        }

        // Tampilkan atau kirim ke view
        //dd($data);
    }

    public function aturan()
{
    // Mengambil aturan asosiasi (rules)
    $minSupport = 0; // Sesuaikan dengan kebutuhan Anda
    $minConfidence = 0; // Sesuaikan dengan kebutuhan Anda
    $rules = AprioriService::generateRules($minSupport, $minConfidence);

    // Kirim data aturan (rules) ke view 'apriori.aturan'
    return view('apriori.rules', compact('rules'));
}

public function showItemsets()
{
    // Menampilkan kombinasi itemset dari produk yang ada
    $minSupport = 0; // Sesuaikan dengan kebutuhan Anda
    $itemsets = AprioriService::getCustomItemsets($minSupport);

    // Menampilkan itemset 1, 2, dan 3
    dd($itemsets); // Menggunakan dd() untuk melihat hasil kombinasi itemset
}
}
