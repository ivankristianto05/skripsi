<?php

namespace App\Http\Controllers;

use App\Services\AprioriService;
use App\Models\Produk;
use App\Models\Transaksi;
use App\Models\Itemset;   
use App\Models\AssociationRule; 
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;


class AprioriController extends Controller
{
    public const CACHE_KEY_GLOBAL_ACTIVE_BATCH_ID = 'apriori_global_active_batch_id_v2';
    public const CACHE_KEY_GLOBAL_BATCH_STATUS_PREFIX = 'apriori_global_status_batch_v2_';
    public const CACHE_LOCK_GLOBAL_PROCESSING = 'apriori_global_processing_lock_v2';
    public const LOCK_TIMEOUT_SECONDS = 300; // Lock 5 menit

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
    public function prosesApriori(Request $request) // Untuk form input (interaktif)
    {
        $validatedData = $request->validate([
            'kategori_produk' => 'required|in:tembakau,filter,kertas',
            'nama_produk' => 'required|exists:produks,kode_produk',
            'min_support' => 'required|numeric|min:0.001|max:1',
            'min_confidence' => 'required|numeric|min:0.01|max:1' // Validasi min_confidence
        ], [ /* ... pesan validasi Anda ... */ ]);

        $kategoriProduk = $validatedData['kategori_produk'];
        $targetProdukKode = $validatedData['nama_produk'];
        $minSupport = (float) $validatedData['min_support'];
        $minConfidence = (float) $validatedData['min_confidence']; // Ambil min_confidence

        $produkTerpilih = Produk::where('kode_produk', $targetProdukKode)->firstOrFail();
        if ($produkTerpilih->kategori_produk !== $kategoriProduk) {
             return back()->withErrors(['nama_produk' => 'Produk yang dipilih tidak sesuai dengan kategori yang dipilih.'])->withInput();
        }

        try {
            // Teruskan minConfidence ke service dispatcher
            $aprioriBatchId = AprioriService::dispatchItemsetCombinationJob(
                $minSupport,
                $targetProdukKode,
                null, // existingBatchId = null untuk interaktif
                $minConfidence // Teruskan minConfidence
            );

            // Simpan parameter dan status awal ke session
            session([
                'apriori_process_' . $aprioriBatchId => [
                    'min_support' => $minSupport,
                    'min_confidence' => $minConfidence, // Simpan di session
                    'target_produk_kode' => $targetProdukKode,
                    'produk_terpilih_nama' => $produkTerpilih->nama_produk,
                    'kategori_produk' => $kategoriProduk,
                    'status_current' => self::STATUS_GLOBAL_JOB1B_DISPATCHED, // Gunakan konstanta yang sama untuk status awal
                    'submitted_at' => now()->toDateTimeString(),
                ]
            ]);

            Log::info("AprioriController: Job 1a (Interaktif) dispatched. Batch ID {$aprioriBatchId}. Target: {$targetProdukKode}, MinSupport: {$minSupport}, MinConfidence: {$minConfidence}.");

            return redirect()->route('apriori.hasil', ['batchId' => $aprioriBatchId])
                             ->with('status_message', "Proses Apriori untuk Batch ID: {$aprioriBatchId} (interaktif) telah dimulai.");

        } catch (\Exception $e) {
            Log::error("AprioriController: Failed to dispatch Job 1a. Error: " . $e->getMessage(), ['exception' => $e]);
            return back()->withErrors(['error' => 'Gagal memulai proses analisis interaktif: ' . $e->getMessage()])->withInput();
        }
    }

    public function hasilProcessing(Request $request, $batchId) // Untuk hasil interaktif
    {
        $processData = session('apriori_process_' . $batchId);
        if (!$processData) {
            return redirect()->route('apriori.index')->withErrors(['error' => 'Data proses interaktif untuk Batch ID tidak ditemukan atau session berakhir.']);
        }

        $itemsetKombinasi = Itemset::where('apriori_batch_id', $batchId)->orderBy('item_count')->orderBy('id')->get();
        $job1Completed = $itemsetKombinasi->isNotEmpty();
        $itemsetKombinasiCount = $itemsetKombinasi->count();
        
        $job2Completed = false;
        $frequentItemsets = null;
        if ($job1Completed) {
            $itemsetDenganSupportCount = Itemset::where('apriori_batch_id', $batchId)->whereNotNull('support_value')->count();
            if ($itemsetKombinasiCount > 0 && $itemsetDenganSupportCount >= $itemsetKombinasiCount) {
                $job2Completed = true;
                $frequentItemsetModels = Itemset::where('apriori_batch_id', $batchId)
                                            ->where('support_value', '>=', $processData['min_support'])
                                            ->orderBy('item_count')->orderBy('support_value', 'desc')->get();
                $frequentItemsets = $this->formatItemsetsForView($frequentItemsetModels); // Gunakan helper
            }
        }

        $job3Completed = false;
        $rules = null;
        if ($job2Completed) {
            $rulesCollection = AssociationRule::where('apriori_batch_id', $batchId)
                                        ->orderBy('confidence', 'desc')->orderBy('lift', 'desc')->get();
            if($rulesCollection->isNotEmpty()){
                $job3Completed = true;
                $rules = $this->formatRulesForView($rulesCollection);
            }
        }
        
        // Update status di session untuk refleksi di view
        $processData['status_current'] = $job3Completed ? self::STATUS_GLOBAL_ALL_JOBS_COMPLETED : 
                                       ($job2Completed ? self::STATUS_GLOBAL_JOB2B_COMPLETED_JOB3B_DISPATCHED :
                                       ($job1Completed ? self::STATUS_GLOBAL_JOB1B_COMPLETED_JOB2B_DISPATCHED :
                                       ($processData['status_current'] ?? self::STATUS_GLOBAL_JOB1B_DISPATCHED)));
        session(['apriori_process_' . $batchId => $processData]);


        $rawFormattedItemsets = $job1Completed ? $this->formatItemsetsForView($itemsetKombinasi, true) : null;
        
        $basicStats = AprioriService::getBasicStatistics();

        return view('apriori.hasil', [ // Ganti ke view 'apriori.hasil_interaktif'
            'batchId' => $batchId, 'processData' => $processData,
            'job1Completed' => $job1Completed, 'job2Completed' => $job2Completed, 'job3Completed' => $job3Completed,
            'itemsetKombinasiCount' => $itemsetKombinasiCount,
            'rawFormattedItemsets' => $rawFormattedItemsets, // Semua kombinasi dari Job 1
            'frequentItemsets' => $frequentItemsets,       // Frequent itemsets dari Job 2
            'rules' => $rules,                              // Aturan dari Job 3
            'basicStats' => $basicStats, 'minSupport' => $processData['min_support'],
            'minConfidence' => $processData['min_confidence'],
            'produkTerpilih' => (object) ['nama_produk' => $processData['produk_terpilih_nama'], 'kode_produk' => $processData['target_produk_kode']],
            'namaProduk' => $processData['target_produk_kode'], 'kategoriProduk' => $processData['kategori_produk'],
        ]);
    }


    public function tampilkanHasilGlobal(Request $request)
    {
        $activeGlobalBatchId = Cache::get(self::CACHE_KEY_GLOBAL_ACTIVE_BATCH_ID);
        $statusProsesGlobal = $activeGlobalBatchId ? Cache::get(self::CACHE_KEY_GLOBAL_BATCH_STATUS_PREFIX . $activeGlobalBatchId, self::STATUS_GLOBAL_NOT_STARTED) : self::STATUS_GLOBAL_NOT_STARTED;

        $globalMinSupport = (float) config('apriori_settings.defaults.global_min_support', 0.01);
        $globalMinConfidence = (float) config('apriori_settings.defaults.global_min_confidence', 0.1);

        if ($statusProsesGlobal === self::STATUS_GLOBAL_NOT_STARTED || Str::startsWith($statusProsesGlobal, self::STATUS_GLOBAL_FAILED_PREFIX)) {
            $lock = Cache::lock(self::CACHE_LOCK_GLOBAL_PROCESSING, self::LOCK_TIMEOUT_SECONDS);
            if ($lock->get()) {
                try {
                    $activeGlobalBatchIdCheck = Cache::get(self::CACHE_KEY_GLOBAL_ACTIVE_BATCH_ID);
                    $statusProsesGlobalCheck = $activeGlobalBatchIdCheck ? Cache::get(self::CACHE_KEY_GLOBAL_BATCH_STATUS_PREFIX . $activeGlobalBatchIdCheck, self::STATUS_GLOBAL_NOT_STARTED) : self::STATUS_GLOBAL_NOT_STARTED;

                    if ($statusProsesGlobalCheck === self::STATUS_GLOBAL_NOT_STARTED || Str::startsWith($statusProsesGlobalCheck, self::STATUS_GLOBAL_FAILED_PREFIX)) {
                        Log::info("AprioriController@tampilkanHasilGlobal: Memicu Job 1b (Global). Status saat ini: {$statusProsesGlobalCheck}");
                        $newGlobalBatchId = (string) Str::uuid();
                        AprioriService::dispatchItemsetCombinationJob($globalMinSupport, null, $newGlobalBatchId, $globalMinConfidence); // Teruskan minConfidence
                        Cache::forever(self::CACHE_KEY_GLOBAL_ACTIVE_BATCH_ID, $newGlobalBatchId);
                        Cache::put(self::CACHE_KEY_GLOBAL_BATCH_STATUS_PREFIX . $newGlobalBatchId, self::STATUS_GLOBAL_JOB1B_DISPATCHED, now()->addDays(7));
                        $activeGlobalBatchId = $newGlobalBatchId;
                        $statusProsesGlobal = self::STATUS_GLOBAL_JOB1B_DISPATCHED;
                        session()->flash('status_message', "Data Apriori global sedang dipersiapkan (Batch ID: {$activeGlobalBatchId}). Pembuatan kombinasi itemset (Job 1b) telah dimulai.");
                    } else {
                        $activeGlobalBatchId = $activeGlobalBatchIdCheck; $statusProsesGlobal = $statusProsesGlobalCheck;
                    }
                } finally { $lock->release(); }
            } else { session()->flash('status_message', "Proses data Apriori global sedang diinisiasi oleh request lain. Silakan refresh."); }
        }

        $job1bSelesaiDanAdaData = false; $itemsetKombinasiCountGlobal = 0; $rawFormattedItemsetsGlobal = null;
        $job2bSelesai = false; $frequentItemsetsGlobal = null;
        $job3bSelesai = false; $rulesGlobal = null;

        if ($activeGlobalBatchId) {
            $itemsetKombinasiGlobal = Itemset::where('apriori_batch_id', $activeGlobalBatchId)->orderBy('item_count')->orderBy('id')->get();
            $itemsetKombinasiCountGlobal = $itemsetKombinasiGlobal->count();
            if ($itemsetKombinasiCountGlobal > 0) $job1bSelesaiDanAdaData = true;
            if($job1bSelesaiDanAdaData) $rawFormattedItemsetsGlobal = $this->formatItemsetsForView($itemsetKombinasiGlobal, true);


            if ($statusProsesGlobal === self::STATUS_GLOBAL_JOB1B_COMPLETED_JOB2B_DISPATCHED ||
                $statusProsesGlobal === self::STATUS_GLOBAL_JOB2B_COMPLETED_JOB3B_DISPATCHED ||
                $statusProsesGlobal === self::STATUS_GLOBAL_ALL_JOBS_COMPLETED) {
                $itemsetDenganSupportCount = Itemset::where('apriori_batch_id', $activeGlobalBatchId)->whereNotNull('support_value')->count();
                if ($itemsetKombinasiCountGlobal > 0 && $itemsetDenganSupportCount >= $itemsetKombinasiCountGlobal) {
                    $job2bSelesai = true;
                    $frequentItemsetModelsGlobal = Itemset::where('apriori_batch_id', $activeGlobalBatchId)
                                                        ->where('support_value', '>=', $globalMinSupport)
                                                        ->orderBy('item_count')->orderBy('support_value', 'desc')->get();
                    $frequentItemsetsGlobal = $this->formatItemsetsForView($frequentItemsetModelsGlobal);
                }
            }

            if ($statusProsesGlobal === self::STATUS_GLOBAL_JOB2B_COMPLETED_JOB3B_DISPATCHED ||
                $statusProsesGlobal === self::STATUS_GLOBAL_ALL_JOBS_COMPLETED) {
                $rulesCollectionGlobal = AssociationRule::where('apriori_batch_id', $activeGlobalBatchId)
                                                    ->orderBy('confidence', 'desc')->orderBy('lift', 'desc')->get();
                if($rulesCollectionGlobal->isNotEmpty()){
                    $job3bSelesai = true;
                    $rulesGlobal = $this->formatRulesForView($rulesCollectionGlobal);
                }
                if ($statusProsesGlobal === self::STATUS_GLOBAL_ALL_JOBS_COMPLETED) $job3bSelesai = true;
            }
        }
        $basicStats = AprioriService::getBasicStatistics();
        return view('apriori.hasil_global', [
            'batchId' => $activeGlobalBatchId, 'statusProsesGlobal' => $statusProsesGlobal,
            'job1bSelesaiDanAdaData' => $job1bSelesaiDanAdaData, 'job2bSelesai' => $job2bSelesai, 'job3bSelesai' => $job3bSelesai,
            'itemsetKombinasiCountGlobal' => $itemsetKombinasiCountGlobal,
            'rawFormattedItemsetsGlobal' => $rawFormattedItemsetsGlobal,
            'frequentItemsetsGlobal' => $frequentItemsetsGlobal,
            'rulesGlobal' => $rulesGlobal,
            'basicStats' => $basicStats, 'minSupport' => $globalMinSupport, 'minConfidence' => $globalMinConfidence,
            'isGlobalResultPage' => true,
        ]);
    }
    private function formatItemsetsForView($itemsetCollection, $isRaw = false)
    {
        if ($itemsetCollection->isEmpty()) {
            return null;
        }
        $produkNama = Produk::pluck('nama_produk', 'kode_produk')->toArray();
        $formatted = ['itemsets_1' => [], 'itemsets_2' => [], 'itemsets_3' => [], 'total_kombinasi' => $itemsetCollection->count()];

        foreach($itemsetCollection as $is) {
            $translatedItems = array_map(fn($code) => $produkNama[$code] ?? $code, $is->items);
            $itemEntry = [
                'itemset_display' => implode(' - ', $translatedItems),
                'itemset_codes' => $is->items,
                'item_count' => $is->item_count,
                'support_count_display' => $is->support_count ?? ($isRaw ? 'N/A (Job 1)' : 'N/A'),
                'support_value_display' => $is->support_value ? (number_format($is->support_value * 100, 2) . '%') : ($isRaw ? 'N/A (Job 1)' : 'N/A'),
            ];
            if ($is->item_count == 1) $formatted['itemsets_1'][] = $itemEntry;
            elseif ($is->item_count == 2) $formatted['itemsets_2'][] = $itemEntry;
            elseif ($is->item_count == 3) $formatted['itemsets_3'][] = $itemEntry;
        }
        return $formatted;
    }

    /**
     * Helper untuk memformat aturan asosiasi untuk view.
     */
    private function formatRulesForView($rulesCollection)
    {
        if ($rulesCollection->isEmpty()) {
            return [];
        }
        $produkNama = Produk::pluck('nama_produk', 'kode_produk')->toArray();
        $formattedRules = [];
        foreach ($rulesCollection as $rule) {
            $antecedentItems = array_map(fn($code) => $produkNama[$code] ?? $code, $rule->antecedent);
            $consequentItems = array_map(fn($code) => $produkNama[$code] ?? $code, $rule->consequent);
            $formattedRules[] = [
                'antecedent_display' => implode(', ', $antecedentItems),
                'consequent_display' => implode(', ', $consequentItems),
                'confidence' => round($rule->confidence * 100, 2) . '%',
                'lift' => round($rule->lift, 2),
                'support_rule' => round($rule->support_value_rule * 100, 2) . '%',
            ];
        }
        return $formattedRules;
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