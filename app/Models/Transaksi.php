<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Transaksi extends Model
{
    protected $primaryKey = 'kode_transaksi'; // <- tambahkan ini
    public $incrementing = false; // karena bukan integer & bukan auto-increment
    protected $keyType = 'string'; // karena kode_transaksi adalah string

    protected $guarded = [];
    public function produks()
    {
        return $this->belongsToMany(Produk::class, 'produk_transaksis', 'kode_transaksi', 'kode_produk', 'kode_transaksi', 'kode_produk')
                    ->withTimestamps();
    }
    public function produkTransaksis()
{
    return $this->hasMany(ProdukTransaksi::class, 'kode_transaksi', 'kode_transaksi');
}

}
