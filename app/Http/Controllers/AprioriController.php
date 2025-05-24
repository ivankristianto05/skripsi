<?php
namespace App\Http\Controllers;
set_time_limit(600);
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
        // Mengambil frequent itemsets dan association rules
        $minSupport = 0.1; // 10% minimum support
        $minConfidence = 0.1; // 50% minimum confidence
        
        // Ambil frequent itemsets
        $frequentItemsets = AprioriService::getCustomItemsets($minSupport);
        
        // Ambil association rules
        $rules = AprioriService::generateAssociationRules($minSupport, $minConfidence);

        // Kirim data ke view
        return view('apriori.aturan', compact('frequentItemsets', 'rules', 'minSupport', 'minConfidence'));
    }

    public function showItemsets()
    {
        // Menampilkan kombinasi itemset dari produk yang ada
        $minSupport = 0.1; // 10% minimum support
        $itemsets = AprioriService::getCustomItemsets($minSupport);

        // Menampilkan itemset 1, 2, dan 3
        dd($itemsets); // Menggunakan dd() untuk melihat hasil kombinasi itemset
    }
}