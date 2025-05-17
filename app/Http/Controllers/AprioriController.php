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
        dd($data);
    }

    // public function index()
    // {
    //     $rules = AprioriService::generateRules(0, 0); // Min support: 2, Min confidence: 0.5
    //     return view('rekomendasi.rules', compact('rules'));
    // }

    public function aturan()
{
    // Mengambil aturan asosiasi (rules)
    $minSupport = 0; // Sesuaikan dengan kebutuhan Anda
    $minConfidence = 0; // Sesuaikan dengan kebutuhan Anda
    $rules = AprioriService::generateRules($minSupport, $minConfidence);

    // Kirim data aturan (rules) ke view 'apriori.aturan'
    return view('apriori.rules', compact('rules'));
}

    
}
