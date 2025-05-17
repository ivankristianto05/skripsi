<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProdukTransaksi extends Model
{
    protected $guarded = [];

    public function produk()
{
    return $this->belongsTo(Produk::class, 'kode_produk', 'kode_produk');
}

public function transaksi()
{
    return $this->belongsTo(Transaksi::class, 'kode_transaksi', 'kode_transaksi');
}

}
