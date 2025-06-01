<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProdukController;
use App\Http\Controllers\TransaksiController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\AprioriController;
use App\Http\Controllers\AuthController;

// Route untuk authentication (tidak perlu login)
Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
Route::post('/login', [AuthController::class, 'login']);
Route::get('/register', [AuthController::class, 'showRegisterForm'])->name('register');
Route::post('/register', [AuthController::class, 'register']);
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// Route yang memerlukan authentication
Route::middleware(['auth'])->group(function () {
    
    // Route untuk dashboard - bisa diakses user dan admin
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    
    // Route untuk user dan admin - hanya melihat produk
    Route::get('/produk', [ProdukController::class, 'index'])->name('produk.index');
    Route::get('/produk/{produk}', [ProdukController::class, 'show'])->name('produk.show');
    
    // Route untuk melihat hasil global apriori - user dan admin
    Route::get('/apriori/hasil', [AprioriController::class, 'tampilkanHasilGlobal'])->name('apriori.hasil.global');
    
    // Route khusus admin - CRUD penuh
    Route::middleware(['role:admin'])->group(function () {
        
        // Route untuk import transaksi - admin only
        Route::get('/transaksi/import', [TransaksiController::class, 'importForm'])->name('transaksis.import.form');
        Route::post('/transaksi/import', [TransaksiController::class, 'import'])->name('transaksis.import');
        
        // Route untuk import produk - admin only
        Route::get('/produk/import', [ProdukController::class, 'showForm'])->name('produk.import.form');
        Route::post('/produk/import', [ProdukController::class, 'import'])->name('produk.import');
        
        // Route untuk CRUD produk - admin only (create, edit, update, delete)
        Route::get('/produk/create', [ProdukController::class, 'create'])->name('produk.create');
        Route::post('/produk', [ProdukController::class, 'store'])->name('produk.store');
        Route::get('/produk/{produk}/edit', [ProdukController::class, 'edit'])->name('produk.edit');
        Route::put('/produk/{produk}', [ProdukController::class, 'update'])->name('produk.update');
        Route::delete('/produk/{produk}', [ProdukController::class, 'destroy'])->name('produk.destroy');
        
        // Route untuk transaksi - admin only (full CRUD)
        Route::resource('/transaksis', TransaksiController::class);
        
        // Route untuk form input parameter dan proses analisis Apriori - admin only
        Route::get('/apriori', [AprioriController::class, 'index'])->name('apriori.index');
        Route::post('/apriori/proses', [AprioriController::class, 'prosesApriori'])->name('apriori.proses');
        Route::get('/apriori/aturan/hasil/{batchId}', [AprioriController::class, 'hasilProcessing'])->name('apriori.hasil.interaktif');
    });
});