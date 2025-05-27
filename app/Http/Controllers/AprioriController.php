<?php

namespace App\Http\Controllers;

use App\Services\AprioriService;
use App\Models\Produk;
use App\Models\Transaksi; // Diperlukan oleh AprioriService::getBasicStatistics()
use App\Models\Itemset;   // Untuk mengecek hasil pemrosesan Job 1
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache; // <--- TAMBAHKAN BARIS INI
use Illuminate\Support\Str;


class AprioriController extends Controller
{
    public const CACHE_KEY_GLOBAL_ACTIVE_BATCH_ID = 'apriori_global_active_batch_id_v2';
    public const CACHE_KEY_GLOBAL_BATCH_STATUS_PREFIX = 'apriori_global_status_batch_v2_'; // Suffix: {batchId}
    public const CACHE_LOCK_GLOBAL_PROCESSING = 'apriori_global_processing_lock_v2';
    public const LOCK_TIMEOUT_SECONDS = 300; // Lock 5 menit

    // Status yang mungkin untuk proses global (contoh)
    public const STATUS_GLOBAL_NOT_STARTED = 'global_not_started';
    public const STATUS_GLOBAL_JOB1B_DISPATCHED = 'global_job1b_itemset_combination_dispatched';
    public const STATUS_GLOBAL_JOB1B_COMPLETED_JOB2B_DISPATCHED = 'global_job1b_completed_job2b_support_dispatched';
    public const STATUS_GLOBAL_JOB2B_COMPLETED_JOB3B_DISPATCHED = 'global_job2b_completed_job3b_rules_dispatched';
    public const STATUS_GLOBAL_ALL_JOBS_COMPLETED = 'global_all_jobs_completed';
    public const STATUS_GLOBAL_FAILED_PREFIX = 'global_failed_'; // e.g., global_failed_job1b
    public function index()
    {
        $produkByKategori = Produk::select('kode_produk', 'nama_produk', 'kategori_produk')
                                    ->orderBy('kategori_produk')
                                    ->orderBy('nama_produk')
                                    ->get()
                                    ->groupBy('kategori_produk')
                                    ->map(function ($produkGroup) {
                                        return $produkGroup->map(function ($produk) {
                                            return [
                                                'kode_produk' => $produk->kode_produk,
                                                'nama_produk' => $produk->nama_produk,
                                                'kategori_produk' => $produk->kategori_produk
                                            ];
                                        })->values();
                                    });

        $basicStats = AprioriService::getBasicStatistics();
        $produkTembakau = Produk::where('kategori_produk', 'tembakau')->get(); // Jika masih dipakai di view

        return view('apriori.index', compact('produkTembakau', 'produkByKategori', 'basicStats'));
    }

    /**
     * Memicu proses pembuatan kombinasi itemset (Job 1) di background.
     * Dipanggil ketika pengguna submit form parameter.
     */
    public function prosesApriori(Request $request)
    {
        $validatedData = $request->validate([
            'kategori_produk' => 'required|in:tembakau,filter,kertas',
            'nama_produk' => 'required|exists:produks,kode_produk', // Ini akan menjadi produk target
            'min_support' => 'required|numeric|min:0.001|max:1',
            // min_confidence tidak relevan untuk Job 1 saja, tapi kita simpan untuk alur lengkap nanti
            'min_confidence' => 'required|numeric|min:0.01|max:1'
        ], [
            'kategori_produk.required' => 'Kategori produk harus dipilih.',
            'nama_produk.required' => 'Produk target harus dipilih.',
            'nama_produk.exists' => 'Produk target yang dipilih tidak ditemukan.',
            'min_support.required' => 'Minimum support harus diisi (akan digunakan oleh Job 2).',
            'min_confidence.required' => 'Minimum confidence harus diisi (akan digunakan oleh Job 3).',
        ]);

        $kategoriProduk = $validatedData['kategori_produk'];
        $targetProdukKode = $validatedData['nama_produk'];
        $minSupport = (float) $validatedData['min_support']; // Akan digunakan oleh Job 2
        $minConfidence = (float) $validatedData['min_confidence']; // Akan digunakan oleh Job 3

        $produkTerpilih = Produk::where('kode_produk', $targetProdukKode)->firstOrFail();
        if ($produkTerpilih->kategori_produk !== $kategoriProduk) {
            return back()->withErrors([
                'nama_produk' => 'Produk yang dipilih tidak sesuai dengan kategori yang dipilih.'
            ])->withInput();
        }

        try {
            // Dispatch Job 1 untuk membuat kombinasi itemset
            // Parameter $minSupport di sini akan diteruskan oleh Job 1 ke Job 2.
            // Jika Job 1 tidak butuh $minSupport sama sekali, bisa di-set null saat dispatch Job 1,
            // dan Job 2 akan pakai $minSupport dari session atau parameter lain.
            // Untuk konsistensi, kita lewatkan $minSupport yang diinput user.
            $aprioriBatchId = AprioriService::dispatchItemsetCombinationJob($minSupport, $targetProdukKode);

            // Simpan parameter dan status awal ke session
            session([
                'apriori_process_' . $aprioriBatchId => [
                    'min_support' => $minSupport,
                    'min_confidence' => $minConfidence,
                    'target_produk_kode' => $targetProdukKode,
                    'produk_terpilih_nama' => $produkTerpilih->nama_produk,
                    'kategori_produk' => $kategoriProduk,
                    'status_job1' => 'dispatched', // Status untuk Job 1 (kombinasi)
                    'status_job2' => 'pending',    // Akan diupdate oleh/setelah Job 2
                    'status_job3' => 'pending',    // Akan diupdate oleh/setelah Job 3
                    'submitted_at' => now()->toDateTimeString(),
                ]
            ]);

            Log::info("AprioriController: Job 1 (Itemset Combination) dispatched for Batch ID {$aprioriBatchId}. Target: {$targetProdukKode}, Min Support for Job 2: {$minSupport}.");

            return redirect()->route('apriori.hasil.interaktif', ['batchId' => $aprioriBatchId])
            ->with('status_message', "Proses Apriori untuk Batch ID: {$aprioriBatchId} (interaktif) telah dimulai. Hasil akan ditampilkan di halaman ini.");

        } catch (\Exception $e) {
            Log::error("AprioriController: Failed to dispatch Job 1. Error: " . $e->getMessage(), ['exception' => $e]);
            return back()->withErrors([
                'error' => 'Gagal memulai proses analisis: ' . $e->getMessage()
            ])->withInput();
        }
    }

    /**
     * Menampilkan halaman status dan hasil pemrosesan Job 1 (Kombinasi Itemset).
     */
    public function hasilProcessing(Request $request, $batchId)
    {
        $processData = session('apriori_process_' . $batchId);

        if (!$processData) {
            return redirect()->route('apriori.index')->withErrors(['error' => 'Data proses untuk Batch ID tidak ditemukan atau session telah berakhir. Silakan mulai proses baru.']);
        }

        // Cek status Job 1 (Pembuatan Kombinasi Itemset)
        // Kita anggap Job 1 selesai jika ada itemset yang tersimpan dengan batch ID ini.
        $itemsetKombinasi = Itemset::where('apriori_batch_id', $batchId)
                                   ->orderBy('item_count')
                                   ->orderBy('id') // atau created_at
                                   ->get();

        $job1Completed = $itemsetKombinasi->isNotEmpty();
        $itemsetKombinasiCount = $itemsetKombinasi->count();

        if ($job1Completed && $processData['status_job1'] !== 'completed') {
            $processData['status_job1'] = 'completed';
            session(['apriori_process_' . $batchId => $processData]); // Update session
        }

        // Logika untuk Job 2 dan Job 3 akan ditambahkan/diaktifkan nanti.
        // Untuk sekarang, $job2Completed dan $job3Completed akan selalu false atau tidak relevan.
        $job2Completed = false; // Akan di-handle di iterasi berikutnya
        // $frequentItemsets = null; // Tidak ada frequent itemsets sampai Job 2 selesai
        // $rules = null; // Tidak ada rules sampai Job 3 selesai

        // Format itemset mentah untuk ditampilkan (jika Job 1 selesai)
        $rawFormattedItemsets = null;
        if($job1Completed) {
            $produkNama = Produk::pluck('nama_produk', 'kode_produk')->toArray();
            $rawFormattedItemsets = [
                'itemsets_1' => [], 'itemsets_2' => [], 'itemsets_3' => [],
                'target_produk_info' => $processData['produk_terpilih_nama'] . ' ('. $processData['target_produk_kode'] .')',
                'total_kombinasi' => $itemsetKombinasiCount
            ];
            foreach($itemsetKombinasi as $is) {
                $translatedItems = array_map(fn($code) => $produkNama[$code] ?? $code, $is->items);
                // Kolom support_count dan support_value akan NULL pada tahap ini
                $itemEntry = [
                    'itemset_display' => implode(' - ', $translatedItems),
                    'itemset_codes' => $is->items,
                    'item_count' => $is->item_count,
                    'support_count_display' => $is->support_count ?? 'N/A (Belum dihitung)',
                    'support_value_display' => $is->support_value ? (number_format($is->support_value * 100, 2) . '%') : 'N/A (Belum dihitung)',
                ];
                if ($is->item_count == 1) $rawFormattedItemsets['itemsets_1'][] = $itemEntry;
                elseif ($is->item_count == 2) $rawFormattedItemsets['itemsets_2'][] = $itemEntry;
                elseif ($is->item_count == 3) $rawFormattedItemsets['itemsets_3'][] = $itemEntry;
            }
        }


        $basicStats = AprioriService::getBasicStatistics(); // Tetap bisa dipanggil

        return view('apriori.hasil', [
            'batchId' => $batchId,
            'processData' => $processData,
            'job1Completed' => $job1Completed,
            'job2Completed' => $job2Completed, // Akan selalu false untuk sekarang
            'itemsetKombinasiCount' => $itemsetKombinasiCount,
            'rawFormattedItemsets' => $rawFormattedItemsets, // Menampilkan kombinasi mentah
            'frequentItemsets' => null, // Belum ada
            'rules' => null, // Belum ada
            'basicStats' => $basicStats,
            'minSupport' => $processData['min_support'], // Untuk ditampilkan, akan dipakai Job 2
            'minConfidence' => $processData['min_confidence'], // Untuk ditampilkan, akan dipakai Job 3
            'produkTerpilih' => (object) ['nama_produk' => $processData['produk_terpilih_nama'], 'kode_produk' => $processData['target_produk_kode']],
            'namaProduk' => $processData['target_produk_kode'],
            'kategoriProduk' => $processData['kategori_produk'],
        ]);
    }

    public function tampilkanHasilGlobal(Request $request) // Untuk route /apriori/hasil (global)
    {
        $activeGlobalBatchId = Cache::get(self::CACHE_KEY_GLOBAL_ACTIVE_BATCH_ID);
        $statusProsesGlobal = $activeGlobalBatchId ? Cache::get(self::CACHE_KEY_GLOBAL_BATCH_STATUS_PREFIX . $activeGlobalBatchId, self::STATUS_GLOBAL_NOT_STARTED) : self::STATUS_GLOBAL_NOT_STARTED;

        $job1bSelesaiDanAdaData = false;
        $job2bSelesai = false; // Placeholder untuk status Job 2b
        $job3bSelesai = false; // Placeholder untuk status Job 3b
        $itemsetKombinasiCountGlobal = 0;
        $rawFormattedItemsetsGlobal = null;
        $frequentItemsetsGlobal = null; // Akan diisi setelah Job 2b
        $rulesGlobal = null;            // Akan diisi setelah Job 3b

        $globalMinSupport = (float) config('apriori_settings.defaults.global_min_support', 0.01);
        $globalMinConfidence = (float) config('apriori_settings.defaults.global_min_confidence', 0.1);

        // Cek apakah proses global perlu dimulai/dilanjutkan
        // Ini hanya akan memicu Job 1b jika belum pernah ada atau gagal dan tidak ada lock
        if ($statusProsesGlobal === self::STATUS_GLOBAL_NOT_STARTED || Str::startsWith($statusProsesGlobal, self::STATUS_GLOBAL_FAILED_PREFIX)) {
            $lock = Cache::lock(self::CACHE_LOCK_GLOBAL_PROCESSING, self::LOCK_TIMEOUT_SECONDS);

            if ($lock->get()) {
                try {
                    // Periksa ulang status setelah mendapatkan lock, mungkin sudah ada yang memicu
                    $activeGlobalBatchIdCheck = Cache::get(self::CACHE_KEY_GLOBAL_ACTIVE_BATCH_ID);
                    $statusProsesGlobalCheck = $activeGlobalBatchIdCheck ? Cache::get(self::CACHE_KEY_GLOBAL_BATCH_STATUS_PREFIX . $activeGlobalBatchIdCheck, self::STATUS_GLOBAL_NOT_STARTED) : self::STATUS_GLOBAL_NOT_STARTED;

                    if ($statusProsesGlobalCheck === self::STATUS_GLOBAL_NOT_STARTED || Str::startsWith($statusProsesGlobalCheck, self::STATUS_GLOBAL_FAILED_PREFIX)) {
                        Log::info("AprioriController@tampilkanHasilGlobal: Memicu Job 1b (Global). Status saat ini: {$statusProsesGlobalCheck}");
                        $newGlobalBatchId = (string) Str::uuid();

                        AprioriService::dispatchItemsetCombinationJob(
                            $globalMinSupport, // Untuk diteruskan ke Job 2b
                            null,              // Tidak ada target produk
                            $newGlobalBatchId  // Berikan batch ID baru ini
                        );

                        Cache::forever(self::CACHE_KEY_GLOBAL_ACTIVE_BATCH_ID, $newGlobalBatchId);
                        Cache::put(self::CACHE_KEY_GLOBAL_BATCH_STATUS_PREFIX . $newGlobalBatchId, self::STATUS_GLOBAL_JOB1B_DISPATCHED, now()->addDays(7));

                        $activeGlobalBatchId = $newGlobalBatchId;
                        $statusProsesGlobal = self::STATUS_GLOBAL_JOB1B_DISPATCHED;
                        session()->flash('status_message', "Data Apriori global sedang dipersiapkan (Batch ID: {$activeGlobalBatchId}). Pembuatan kombinasi itemset (Job 1b) telah dimulai.");
                    } else {
                        // Proses sudah dimulai oleh request lain saat menunggu lock
                        $activeGlobalBatchId = $activeGlobalBatchIdCheck;
                        $statusProsesGlobal = $statusProsesGlobalCheck;
                    }
                } finally {
                    $lock->release();
                }
            } else {
                session()->flash('status_message', "Proses data Apriori global sedang diinisiasi oleh request lain. Silakan refresh.");
            }
        }

        // Jika sudah ada batch global aktif, coba ambil data
        if ($activeGlobalBatchId) {
            $itemsetKombinasiGlobal = Itemset::where('apriori_batch_id', $activeGlobalBatchId)
                                        ->orderBy('item_count')->orderBy('id')->get();
            $itemsetKombinasiCountGlobal = $itemsetKombinasiGlobal->count();

            if ($itemsetKombinasiCountGlobal > 0) {
                // Asumsikan Job 1b selesai jika ada data. Idealnya, status diupdate oleh Job 1b.
                if($statusProsesGlobal === self::STATUS_GLOBAL_JOB1B_DISPATCHED) {
                     // Log::info("Data Job 1b ditemukan, anggap selesai. Idealnya status diupdate oleh job.");
                }
                $job1bSelesaiDanAdaData = true;

                $produkNama = Produk::pluck('nama_produk', 'kode_produk')->toArray();
                $rawFormattedItemsetsGlobal = [
                    'itemsets_1' => [], 'itemsets_2' => [], 'itemsets_3' => [],
                    'total_kombinasi' => $itemsetKombinasiCountGlobal
                ];
                foreach($itemsetKombinasiGlobal as $is) {
                    $translatedItems = array_map(fn($code) => $produkNama[$code] ?? $code, $is->items);
                    $itemEntry = [
                        'itemset_display' => implode(' - ', $translatedItems),
                        'itemset_codes' => $is->items,
                        'item_count' => $is->item_count,
                        'support_count_display' => $is->support_count ?? 'N/A (Job 2b Belum)',
                        'support_value_display' => $is->support_value ? (number_format($is->support_value * 100, 2) . '%') : 'N/A (Job 2b Belum)',
                    ];
                    if ($is->item_count == 1) $rawFormattedItemsetsGlobal['itemsets_1'][] = $itemEntry;
                    elseif ($is->item_count == 2) $rawFormattedItemsetsGlobal['itemsets_2'][] = $itemEntry;
                    elseif ($is->item_count == 3) $rawFormattedItemsetsGlobal['itemsets_3'][] = $itemEntry;
                }
            }

            // TODO: Tambahkan logika untuk mengambil $frequentItemsetsGlobal setelah Job 2b selesai
            // berdasarkan status $statusProsesGlobal == self::STATUS_GLOBAL_JOB2B_COMPLETED_JOB3B_DISPATCHED
            // atau self::STATUS_GLOBAL_ALL_JOBS_COMPLETED

            // TODO: Tambahkan logika untuk mengambil $rulesGlobal setelah Job 3b selesai
            // berdasarkan status $statusProsesGlobal == self::STATUS_GLOBAL_ALL_JOBS_COMPLETED
        }

        $basicStats = AprioriService::getBasicStatistics();

        return view('apriori.hasil_global', [ // Ganti ke view 'apriori.hasil_global'
            'batchId' => $activeGlobalBatchId,
            'statusProsesGlobal' => $statusProsesGlobal,
            'job1bSelesaiDanAdaData' => $job1bSelesaiDanAdaData,
            'itemsetKombinasiCountGlobal' => $itemsetKombinasiCountGlobal,
            'rawFormattedItemsetsGlobal' => $rawFormattedItemsetsGlobal,
            'frequentItemsetsGlobal' => $frequentItemsetsGlobal,
            'rulesGlobal' => $rulesGlobal,
            'basicStats' => $basicStats,
            'minSupport' => $globalMinSupport,
            'minConfidence' => $globalMinConfidence,
            'isGlobalResultPage' => true,
        ]);
    }



    // === HELPER METHODS (Tidak berubah) ===
    public function getFormattedTransaksi()
    {
        $transaksis = Transaksi::with('produkTransaksis:kode_transaksi,kode_produk')->get(['kode_transaksi']);
        $data = [];
        foreach ($transaksis as $transaksi) {
            $data[$transaksi->kode_transaksi] = $transaksi->produkTransaksis->pluck('kode_produk')->toArray();
        }
        return $data; // Contoh return
    }

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

    public function validateProduk(Request $request)
    {
        $kodeProduk = $request->input('kode_produk');
        $kategori = $request->input('kategori');
        $produk = Produk::where('kode_produk', $kodeProduk)
                       ->where('kategori_produk', $kategori)
                       ->first();
        return response()->json([
            'valid' => (bool)$produk,
            'message' => $produk ? 'Produk valid' : 'Produk tidak sesuai dengan kategori yang dipilih'
        ]);
    }
}