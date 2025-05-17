<?php

namespace App\Imports;

use App\Models\Transaksi;
use App\Models\Produk;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class TransaksiImport implements ToCollection
{
    public function collection(Collection $rows)
    {
        // Lewati header
        $rows->shift();

        foreach ($rows as $row) {
            $kodeTransaksi = trim($row[0]);   // Kolom A: kode_transaksi
            $tanggalRaw = $row[1];            // Kolom B: tanggal_transaksi
            $namaProduk = trim($row[2]);      // Kolom C: nama_produk

            // Format tanggal
            try {
                if (is_numeric($tanggalRaw)) {
                    $tanggal = Carbon::instance(\PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($tanggalRaw));
                } else {
                    $tanggal = Carbon::createFromFormat('d/m/Y', $tanggalRaw);
                }
            } catch (\Exception $e) {
                continue; // Skip jika format tidak valid
            }

            // Cari produk berdasarkan nama_produk
            $produk = Produk::where('nama_produk', $namaProduk)->first();

            if (!$produk) {
                continue; // Skip jika produk tidak ditemukan
            }

            // Buat atau update transaksi
            $transaksi = Transaksi::updateOrCreate(
                ['kode_transaksi' => $kodeTransaksi],
                ['tanggal_transaksi' => $tanggal->format('Y-m-d')]
            );

            // Simpan ke pivot table
            DB::table('produk_transaksis')->updateOrInsert([
                'kode_transaksi' => $kodeTransaksi,
                'kode_produk' => $produk->kode_produk,
            ]);
        }
    }
}
