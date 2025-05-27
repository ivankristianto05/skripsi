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
        // Ambil daftar produk tembakau untuk dropdown (untuk kompatibilitas)
        $produkTembakau = Produk::where('kategori_produk', 'tembakau')->get();
        
        // Ambil semua produk dan kelompokkan berdasarkan kategori untuk dropdown dinamis
        $produkByKategori = Produk::select('kode_produk', 'nama_produk', 'kategori_produk')
                                 ->orderBy('kategori_produk')
                                 ->orderBy('nama_produk')
                                 ->get()
                                 ->groupBy('kategori_produk')
                                 ->map(function ($produkGroup) {
                                     // Konversi ke array untuk JavaScript
                                     return $produkGroup->map(function ($produk) {
                                         return [
                                             'kode_produk' => $produk->kode_produk,
                                             'nama_produk' => $produk->nama_produk,
                                             'kategori_produk' => $produk->kategori_produk
                                         ];
                                     })->values();
                                 });

        // Ambil statistik dasar untuk informasi tambahan
        $basicStats = AprioriService::getBasicStatistics();
        
        return view('apriori.index', compact('produkTembakau', 'produkByKategori', 'basicStats'));
    }

    // Proses analisis apriori dengan parameter dari form
    public function aturan(Request $request)
    {
        // Validasi input yang sudah disesuaikan dengan form baru
        $request->validate([
            'kategori_produk' => 'required|in:tembakau,filter,kertas',
            'nama_produk' => 'required|exists:produks,kode_produk',
            'min_support' => 'required|numeric|min:0.01|max:1',
            'min_confidence' => 'required|numeric|min:0.01|max:1'
        ], [
            'kategori_produk.required' => 'Kategori produk harus dipilih.',
            'kategori_produk.in' => 'Kategori produk tidak valid.',
            'nama_produk.required' => 'Produk harus dipilih.',
            'nama_produk.exists' => 'Produk yang dipilih tidak ditemukan.',
            'min_support.required' => 'Minimum support harus diisi.',
            'min_support.numeric' => 'Minimum support harus berupa angka.',
            'min_support.min' => 'Minimum support minimal 0.01 (1%).',
            'min_support.max' => 'Minimum support maksimal 1 (100%).',
            'min_confidence.required' => 'Minimum confidence harus diisi.',
            'min_confidence.numeric' => 'Minimum confidence harus berupa angka.',
            'min_confidence.min' => 'Minimum confidence minimal 0.01 (1%).',
            'min_confidence.max' => 'Minimum confidence maksimal 1 (100%).',
        ]);

        // Ambil parameter dari request
        $kategoriProduk = $request->input('kategori_produk');
        $namaProduk = $request->input('nama_produk'); // Ubah dari nama_tembakau ke nama_produk
        $minSupport = $request->input('min_support');
        $minConfidence = $request->input('min_confidence');
        
        // Validasi bahwa produk yang dipilih sesuai dengan kategori
        $produkTerpilih = Produk::where('kode_produk', $namaProduk)->first();
        
        if (!$produkTerpilih) {
            return back()->withErrors([
                'nama_produk' => 'Produk yang dipilih tidak ditemukan.'
            ])->withInput();
        }

        if ($produkTerpilih->kategori_produk !== $kategoriProduk) {
            return back()->withErrors([
                'nama_produk' => 'Produk yang dipilih tidak sesuai dengan kategori yang dipilih.'
            ])->withInput();
        }

        try {
            // Ambil frequent itemsets dengan filter produk yang dipilih
            $frequentItemsets = AprioriService::getCustomItemsets($minSupport, $namaProduk);
            
            // Ambil association rules dengan filter produk yang dipilih
            $rules = AprioriService::generateAssociationRules($minSupport, $minConfidence, $namaProduk);

            // Ambil statistik dasar
            $basicStats = AprioriService::getBasicStatistics();

            // Kirim data ke view dengan parameter yang lebih lengkap
            return view('apriori.aturan', compact(
                'frequentItemsets', 
                'rules', 
                'basicStats',
                'minSupport', 
                'minConfidence', 
                'produkTerpilih', // Ganti dari produkTembakau ke produkTerpilih
                'namaProduk', // Ganti dari namaTembakau ke namaProduk
                'kategoriProduk' // Tambahan parameter kategori
            ));

        } catch (\Exception $e) {
            return back()->withErrors([
                'error' => 'Terjadi kesalahan dalam proses analisis: ' . $e->getMessage()
            ])->withInput();
        }
    }

    public function showItemsets()
    {
        // Menampilkan kombinasi itemset dari produk yang ada
        $minSupport = 0.1; // 10% minimum support
        $itemsets = AprioriService::getCustomItemsets($minSupport);

        // Menampilkan itemset 1, 2, dan 3
        dd($itemsets); // Menggunakan dd() untuk melihat hasil kombinasi itemset
    }

    // Method tambahan untuk AJAX request produk berdasarkan kategori (opsional)
    public function getProdukByKategori(Request $request)
    {
        $kategori = $request->input('kategori');
        
        if (!in_array($kategori, ['tembakau', 'filter', 'kertas'])) {
            return response()->json(['error' => 'Kategori tidak valid'], 400);
        }

        $produk = Produk::where('kategori_produk', $kategori)
                       ->select('kode_produk', 'nama_produk')
                       ->orderBy('nama_produk')
                       ->get();

        return response()->json($produk);
    }

    // Method untuk mendapatkan statistik kategori (opsional)
    public function getKategoriStats()
    {
        $stats = [
            'produk_per_kategori' => Produk::selectRaw('kategori_produk, COUNT(*) as count')
                                          ->groupBy('kategori_produk')
                                          ->pluck('count', 'kategori_produk')
                                          ->toArray(),
            'total_produk' => Produk::count(),
            'total_transaksi' => Transaksi::count()
        ];

        return response()->json($stats);
    }

    // Method untuk validasi real-time (opsional)
    public function validateProduk(Request $request)
    {
        $kodeProduk = $request->input('kode_produk');
        $kategori = $request->input('kategori');

        $produk = Produk::where('kode_produk', $kodeProduk)
                       ->where('kategori_produk', $kategori)
                       ->first();

        return response()->json([
            'valid' => $produk ? true : false,
            'message' => $produk ? 'Produk valid' : 'Produk tidak sesuai dengan kategori yang dipilih'
        ]);
    }
}