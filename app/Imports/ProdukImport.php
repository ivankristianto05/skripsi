<?php

namespace App\Imports;

use App\Models\Produk;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;

class ProdukImport implements ToCollection
{
    public function collection(Collection $rows)
    {
        $rows->shift(); // Skip header

        foreach ($rows as $row) {
            $kode = trim($row[0]);
            $nama = trim($row[1]);
            $kategori = trim($row[2]);

            if (empty($nama) || !in_array($kategori, ['tembakau', 'kertas', 'filter'])) {
                continue;
            }
            
            // Generate kode_produk jika kosong
            if (empty($kode)) {
                $prefix = match ($kategori) {
                    'tembakau' => 'T',
                    'kertas' => 'K',
                    'filter' => 'F',
                    default => 'X',
                };

                $count = Produk::where('kategori_produk', $kategori)->count() + 1;
                $kode = $prefix . str_pad($count, 3, '0', STR_PAD_LEFT);
            }

            Produk::updateOrCreate(
                ['kode_produk' => $kode],
                [
                    'nama_produk' => $nama,
                    'kategori_produk' => $kategori,
                ]
            );
        }
    }
}
