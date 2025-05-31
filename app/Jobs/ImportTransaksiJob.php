<?php

namespace App\Jobs;

use App\Models\Transaksi;
use App\Models\Produk;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Concerns\ToCollection;
use Carbon\Carbon;
use Exception;

class ImportTransaksiJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $filePath;
    protected $userId;
    
    // Timeout job dalam detik (30 menit)
    public $timeout = 1800;
    
    // Maximum attempts jika job gagal
    public $tries = 3;

    /**
     * Create a new job instance.
     */
    public function __construct($filePath, $userId = null)
    {
        $this->filePath = $filePath;
        $this->userId = $userId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            Log::info('Starting import transaksi job', ['file' => $this->filePath]);
            
            // Pastikan file ada sebelum import
            if (!file_exists($this->filePath)) {
                throw new Exception('File not found: ' . $this->filePath);
            }
            
            // Pastikan file bisa dibaca
            if (!is_readable($this->filePath)) {
                throw new Exception('File is not readable: ' . $this->filePath);
            }
            
            Log::info('File exists and is readable, starting import process');
            
            // Import menggunakan custom import class
            Excel::import(new TransaksiImportJob(), $this->filePath);
            
            Log::info('Import transaksi job completed successfully');
            
            // Hapus file setelah import selesai
            if (file_exists($this->filePath)) {
                unlink($this->filePath);
                Log::info('Temporary file deleted', ['file' => $this->filePath]);
            }
            
        } catch (Exception $e) {
            Log::error('Import transaksi job failed', [
                'error' => $e->getMessage(),
                'file' => $this->filePath,
                'file_exists' => file_exists($this->filePath),
                'is_readable' => file_exists($this->filePath) ? is_readable($this->filePath) : false
            ]);
            
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(Exception $exception): void
    {
        Log::error('Import transaksi job failed permanently', [
            'error' => $exception->getMessage(),
            'file' => $this->filePath
        ]);
        
        // Hapus file jika job gagal permanent
        if (file_exists($this->filePath)) {
            unlink($this->filePath);
        }
    }
}

/**
 * Custom Import Class untuk Job
 */
class TransaksiImportJob implements ToCollection
{
    protected $successCount = 0;
    protected $errorCount = 0;
    protected $errors = [];

    public function collection(Collection $rows)
    {
        // Lewati header
        $rows->shift();
        
        Log::info('Processing ' . $rows->count() . ' rows');

        DB::beginTransaction();
        
        try {
            foreach ($rows as $index => $row) {
                $this->processRow($row, $index + 2); // +2 karena mulai dari baris 2 (setelah header)
            }
            
            DB::commit();
            
            Log::info('Import completed', [
                'success' => $this->successCount,
                'errors' => $this->errorCount,
                'error_details' => $this->errors
            ]);
            
        } catch (Exception $e) {
            DB::rollback();
            Log::error('Import transaction failed', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    protected function processRow($row, $rowNumber)
    {
        try {
            // Validasi kolom yang diperlukan
            if (empty(trim($row[0] ?? '')) || empty(trim($row[2] ?? ''))) {
                $this->logError($rowNumber, 'Kode transaksi atau nama produk kosong');
                return;
            }

            $kodeTransaksi = trim($row[0]);   // Kolom A: kode_transaksi
            $tanggalRaw = $row[1];            // Kolom B: tanggal_transaksi
            $namaProduk = trim($row[2]);      // Kolom C: nama_produk

            // Format tanggal
            $tanggal = $this->parseTanggal($tanggalRaw, $rowNumber);
            if (!$tanggal) {
                return; // Error sudah di-log di function parseTanggal
            }

            // Cari produk berdasarkan nama_produk
            $produk = $this->findProduk($namaProduk, $rowNumber);
            if (!$produk) {
                return; // Error sudah di-log di function findProduk
            }

            // Buat atau update transaksi
            $transaksi = Transaksi::updateOrCreate(
                ['kode_transaksi' => $kodeTransaksi],
                ['tanggal_transaksi' => $tanggal->format('Y-m-d')]
            );

            // Simpan ke pivot table dengan pengecekan duplikasi
            $existingRelation = DB::table('produk_transaksis')
                ->where('kode_transaksi', $kodeTransaksi)
                ->where('kode_produk', $produk->kode_produk)
                ->exists();

            if (!$existingRelation) {
                DB::table('produk_transaksis')->insert([
                    'kode_transaksi' => $kodeTransaksi,
                    'kode_produk' => $produk->kode_produk,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $this->successCount++;
            
        } catch (Exception $e) {
            $this->logError($rowNumber, 'Error processing row: ' . $e->getMessage());
        }
    }

    protected function parseTanggal($tanggalRaw, $rowNumber)
    {
        try {
            if (empty($tanggalRaw)) {
                $this->logError($rowNumber, 'Tanggal kosong');
                return null;
            }

            if (is_numeric($tanggalRaw)) {
                return Carbon::instance(\PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($tanggalRaw));
            } else {
                // Coba berbagai format tanggal
                $formats = ['d/m/Y', 'Y-m-d', 'd-m-Y', 'Y/m/d'];
                
                foreach ($formats as $format) {
                    try {
                        return Carbon::createFromFormat($format, $tanggalRaw);
                    } catch (Exception $e) {
                        continue;
                    }
                }
                
                // Jika semua format gagal, coba parse otomatis
                return Carbon::parse($tanggalRaw);
            }
        } catch (Exception $e) {
            $this->logError($rowNumber, 'Format tanggal tidak valid: ' . $tanggalRaw);
            return null;
        }
    }

    protected function findProduk($namaProduk, $rowNumber)
    {
        $produk = Produk::where('nama_produk', $namaProduk)->first();
        
        if (!$produk) {
            // Coba cari dengan case insensitive
            $produk = Produk::whereRaw('LOWER(nama_produk) = ?', [strtolower($namaProduk)])->first();
        }
        
        if (!$produk) {
            $this->logError($rowNumber, 'Produk tidak ditemukan: ' . $namaProduk);
            return null;
        }
        
        return $produk;
    }

    protected function logError($rowNumber, $message)
    {
        $this->errorCount++;
        $this->errors[] = "Baris {$rowNumber}: {$message}";
        Log::warning("Import error on row {$rowNumber}: {$message}");
    }
}