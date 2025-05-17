<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Produk extends Model
{
    protected $primaryKey = 'kode_produk';
    public $incrementing = false; // karena kode_produk bukan angka auto-increment
    protected $keyType = 'string'; // kode_produk adalah string
    protected $guarded = [];

    public function transaksis()
    {
        return $this->belongsToMany(Transaksi::class, 'produk_transaksis', 'kode_produk', 'kode_transaksi', 'kode_produk', 'kode_transaksi')
                    ->withTimestamps();
    }
    

}
