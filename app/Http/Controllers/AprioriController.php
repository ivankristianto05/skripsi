<?php
namespace App\Http\Controllers;
use App\Services\AprioriService;
use App\Models\Produk;
use App\Models\Transaksi;
use Illuminate\Http\Request;

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

    // Menampilkan form input parameter
    public function index()
    {
        // Ambil daftar produk tembakau untuk dropdown
        $produkTembakau = Produk::where('kategori_produk', 'tembakau')->get();
        
        return view('apriori.index', compact('produkTembakau'));
    }

    // Proses analisis apriori dengan parameter dari form
    public function aturan(Request $request)
    {
        // Validasi input
        $request->validate([
            'nama_tembakau' => 'required|exists:produks,kode_produk',
            'min_support' => 'required|numeric|min:0.01|max:1',
            'min_confidence' => 'required|numeric|min:0.01|max:1'
        ]);

        // Ambil parameter dari request
        $namaTembakau = $request->input('nama_tembakau');
        $minSupport = $request->input('min_support');
        $minConfidence = $request->input('min_confidence');
        
        // Ambil informasi produk tembakau yang dipilih
        $produkTembakau = Produk::where('kode_produk', $namaTembakau)->first();
        
        // Ambil frequent itemsets dengan filter tembakau
        $frequentItemsets = AprioriService::getCustomItemsets($minSupport, $namaTembakau);
        
        // Ambil association rules dengan filter tembakau
        $rules = AprioriService::generateAssociationRules($minSupport, $minConfidence, $namaTembakau);

        // Kirim data ke view
        return view('apriori.aturan', compact(
            'frequentItemsets', 
            'rules', 
            'minSupport', 
            'minConfidence', 
            'produkTembakau',
            'namaTembakau'
        ));
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