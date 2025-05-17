<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProdukController;
use App\Http\Controllers\TransaksiController;
use App\Helpers\AprioriHelper;
use App\Models\Produk;
use App\Http\Controllers\DashboardController;

Route::get('/transaksi/import', [TransaksiController::class, 'importForm'])->name('transaksis.import.form');
Route::post('/transaksi/import', [TransaksiController::class, 'import'])->name('transaksis.import');

Route::get('/produk/import', [ProdukController::class, 'showForm'])->name('produk.import.form');
Route::post('/produk/import', [ProdukController::class, 'import'])->name('produk.import');

Route::resource('/produk', ProdukController::class);
Route::resource('/transaksis', TransaksiController::class); // Tambahkan ini untuk transaksi
Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
Route::get('/apriori/data', [\App\Http\Controllers\AprioriController::class, 'getFormattedTransaksi']);
Route::get('/apriori/aturan', [\App\Http\Controllers\AprioriController::class, 'aturan'])->name('apriori.aturan');
Route::get('/apriori/rules', [\App\Http\Controllers\AprioriController::class, 'aturan'])->name('apriori.rules');
