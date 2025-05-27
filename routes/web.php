<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProdukController;
use App\Http\Controllers\TransaksiController;
use App\Helpers\AprioriHelper;
use App\Models\Produk;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\AprioriController;

// Route untuk import transaksi
Route::get('/transaksi/import', [TransaksiController::class, 'importForm'])->name('transaksis.import.form');
Route::post('/transaksi/import', [TransaksiController::class, 'import'])->name('transaksis.import');

// Route untuk import produk
Route::get('/produk/import', [ProdukController::class, 'showForm'])->name('produk.import.form');
Route::post('/produk/import', [ProdukController::class, 'import'])->name('produk.import');

// Route untuk produk dan transaksi (resource controllers)
Route::resource('/produk', ProdukController::class);
Route::resource('/transaksis', TransaksiController::class);

// Route untuk dashboard
Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

// Route untuk menampilkan form input parameter untuk analisis Apriori
Route::get('/apriori', [AprioriController::class, 'index'])->name('apriori.index'); // Form input parameter
Route::post('/apriori/proses', [AprioriController::class, 'prosesApriori'])->name('apriori.proses');

// Route untuk proses analisis Apriori dan menampilkan hasil
Route::get('/apriori/aturan/hasil/{batchId}', [AprioriController::class, 'hasilProcessing'])->name('apriori.hasil.interaktif');

// Route untuk menampilkan kombinasi itemset (untuk testing)
Route::get('/show-itemsets', [AprioriController::class, 'showItemsets']);
Route::get('/apriori/hasil', [AprioriController::class, 'tampilkanHasilGlobal'])->name('apriori.hasil.global');
