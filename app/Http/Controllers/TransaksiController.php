<?php

namespace App\Http\Controllers;

use App\Models\Transaksi;
use App\Models\Produk;
use App\Models\ProdukTransaksi;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\TransaksiImport;
use App\Jobs\ImportTransaksiJob;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Exception; // Tambahkan import ini

class TransaksiController extends Controller
{
    public function index()
    {
        $transaksis = Transaksi::with('produkTransaksis.produk')->paginate(20);
        
        return view('transaksi.index', compact('transaksis'));
    }

    public function create()
    {
        $produks = Produk::all();
        return view('transaksi.create', compact('produks'));
    }
    
    public function store(Request $request)
    {
        $request->validate([
            'tanggal_transaksi' => 'required|date',
            'kode_produk' => 'required|array|min:1',
            'kode_produk.*' => 'required|exists:produks,kode_produk',
        ]);

        // Generate kode transaksi unik
        $lastTransaksi = Transaksi::orderBy('kode_transaksi', 'desc')->first();
        if ($lastTransaksi) {
            $lastNumber = (int) substr($lastTransaksi->kode_transaksi, 4);
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }
        $kode = 'TRS' . str_pad($newNumber, 4, '0', STR_PAD_LEFT);

        // Simpan transaksi
        $transaksi = Transaksi::create([
            'kode_transaksi' => $kode,
            'tanggal_transaksi' => $request->tanggal_transaksi,
        ]);

        // Simpan produk yang dibeli
        foreach ($request->kode_produk as $kodeProduk) {
            ProdukTransaksi::create([
                'kode_transaksi' => $kode,
                'kode_produk' => $kodeProduk,
            ]);
        }

        return redirect()->route('transaksis.index')->with('success', 'Transaksi berhasil disimpan.');
    }
    
    public function show($kode_transaksi)
    {
        $transaksi = Transaksi::with('produks')->findOrFail($kode_transaksi);
        return view('transaksi.show', compact('transaksi'));
    }

    public function edit($kode_transaksi)
    {
        $transaksi = Transaksi::with('produkTransaksis')->findOrFail($kode_transaksi);
        $produks = Produk::all();
        return view('transaksi.edit', compact('transaksi', 'produks'));
    }

    public function update(Request $request, $kode_transaksi)
    {
        $request->validate([
            'tanggal_transaksi' => 'required|date',
            'kode_produk' => 'required|array|min:1',
            'kode_produk.*' => 'required|exists:produks,kode_produk',
        ]);

        $transaksi = Transaksi::findOrFail($kode_transaksi);
        $transaksi->update([
            'tanggal_transaksi' => $request->tanggal_transaksi,
        ]);

        // Hapus produk sebelumnya
        $transaksi->produkTransaksis()->delete();

        // Simpan ulang
        foreach ($request->kode_produk as $kodeProduk) {
            ProdukTransaksi::create([
                'kode_transaksi' => $kode_transaksi,
                'kode_produk' => $kodeProduk,
            ]);
        }

        return redirect()->route('transaksis.index')->with('success', 'Transaksi berhasil diupdate.');
    }

    public function destroy($kode_transaksi)
    {
        $transaksi = Transaksi::findOrFail($kode_transaksi);
        $transaksi->produks()->detach();
        $transaksi->delete();

        return redirect()->route('transaksis.index')->with('success', 'Transaksi berhasil dihapus.');
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:xlsx,csv,xls|max:10240', // Max 10MB
        ]);

        try {
            $uploadedFile = $request->file('file');
            $fileName = 'transaksi_' . time() . '_' . uniqid() . '.' . $uploadedFile->getClientOriginalExtension();
            
            // Method 1: Gunakan move() langsung (lebih reliable di Windows)
            $tempDir = storage_path('app' . DIRECTORY_SEPARATOR . 'temp' . DIRECTORY_SEPARATOR . 'imports');
            
            // Buat direktori jika belum ada
            if (!file_exists($tempDir)) {
                if (!mkdir($tempDir, 0755, true)) {
                    throw new Exception('Gagal membuat direktori: ' . $tempDir);
                }
            }
            
            $fullPath = $tempDir . DIRECTORY_SEPARATOR . $fileName;
            
            Log::info('File upload attempt', [
                'original_name' => $uploadedFile->getClientOriginalName(),
                'file_size' => $uploadedFile->getSize(),
                'temp_dir' => $tempDir,
                'target_path' => $fullPath,
                'temp_dir_exists' => file_exists($tempDir),
                'temp_dir_writable' => is_writable($tempDir),
                'php_temp_file' => $uploadedFile->getPathname()
            ]);

            // Pindahkan file menggunakan move()
            if (!$uploadedFile->move($tempDir, $fileName)) {
                // Fallback: coba dengan copy manual
                if (!copy($uploadedFile->getPathname(), $fullPath)) {
                    throw new Exception('Gagal memindahkan file dari temp ke storage');
                }
            }

            // Verifikasi file berhasil dipindahkan
            if (!file_exists($fullPath)) {
                throw new Exception('File tidak ditemukan setelah dipindahkan: ' . $fullPath);
            }

            // Verifikasi file bisa dibaca dan tidak kosong
            if (!is_readable($fullPath)) {
                throw new Exception('File tidak bisa dibaca: ' . $fullPath);
            }

            $fileSize = filesize($fullPath);
            if ($fileSize === 0) {
                throw new Exception('File kosong (0 bytes): ' . $fullPath);
            }

            Log::info('File successfully uploaded', [
                'path' => $fullPath,
                'size' => $fileSize
            ]);

            // Dispatch job untuk import
            ImportTransaksiJob::dispatch($fullPath, auth()->id());

            return redirect()->route('transaksis.index')
                ->with('success', 'File berhasil diupload! Import sedang diproses di background. Silakan cek kembali dalam beberapa menit.');
                
        } catch (Exception $e) {
            Log::error('Import upload failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'storage_info' => [
                    'storage_path' => storage_path('app'),
                    'storage_exists' => file_exists(storage_path('app')),
                    'storage_writable' => is_writable(storage_path('app')),
                    'temp_dir' => storage_path('app' . DIRECTORY_SEPARATOR . 'temp'),
                    'temp_dir_exists' => file_exists(storage_path('app' . DIRECTORY_SEPARATOR . 'temp')),
                    'disk_free_space' => disk_free_space(storage_path('app'))
                ],
                'file_info' => $request->hasFile('file') ? [
                    'name' => $request->file('file')->getClientOriginalName(),
                    'size' => $request->file('file')->getSize(),
                    'mime' => $request->file('file')->getMimeType(),
                    'temp_path' => $request->file('file')->getPathname(),
                    'temp_exists' => file_exists($request->file('file')->getPathname())
                ] : 'No file uploaded'
            ]);
            
            return redirect()->back()
                ->with('error', 'Gagal mengupload file: ' . $e->getMessage());
        }
    }

    public function importForm()
    {
        return view('transaksi.import');
    }
}