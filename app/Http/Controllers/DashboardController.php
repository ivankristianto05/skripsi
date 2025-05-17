<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Produk;
use App\Models\Transaksi;

class DashboardController extends Controller
{
    public function index()
    {
        $totalProduk = Produk::count();
        $totalTransaksi = Transaksi::count();

        return view('dashboard', compact('totalProduk', 'totalTransaksi'));

    }
}
