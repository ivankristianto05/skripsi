<?php

namespace App\Http\Controllers;

use App\Models\ProdukTransaksi;
use App\Models\Transaksi;
use App\Models\Produk;
use Illuminate\Http\Request;

class ProdukTransaksiController extends Controller
{
    public function index()
    {
        $produkTransaksis = ProdukTransaksi::with(['produk', 'transaksi'])->get();
        return view('produk_transaksi.index', compact('produkTransaksis'));
    }

    public function create()
    {
        $transaksis = Transaksi::all();
        $produks = Produk::all();
        return view('produk_transaksi.create', compact('transaksis', 'produks'));
    }

    public function store(Request $request)
    {
        // Validasi input produk transaksi
        $request->validate([
            'kode_transaksi' => 'required|exists:transaksis,kode_transaksi',
            'kode_produk' => 'required|array|min:1', // Pastikan kode_produk adalah array
            'kode_produk.*' => 'exists:produks,kode_produk', // Pastikan setiap kode_produk valid
        ]);

        // Simpan setiap produk transaksi
        foreach ($request->kode_produk as $kodeProduk) {
            ProdukTransaksi::create([
                'kode_transaksi' => $request->kode_transaksi,
                'kode_produk' => $kodeProduk,
            ]);
        }

        return redirect()->route('produk-transaksi.index')->with('success', 'Produk berhasil ditambahkan ke transaksi.');
    }

    public function edit(ProdukTransaksi $produkTransaksi)
    {
        $transaksis = Transaksi::all();
        $produks = Produk::all();
        return view('produk_transaksi.edit', compact('produkTransaksi', 'transaksis', 'produks'));
    }

    public function update(Request $request, ProdukTransaksi $produkTransaksi)
    {
        // Validasi input
        $request->validate([
            'kode_transaksi' => 'required|exists:transaksis,kode_transaksi',
            'kode_produk' => 'required|exists:produks,kode_produk',
        ]);

        // Update data produk transaksi
        $produkTransaksi->update([
            'kode_transaksi' => $request->kode_transaksi,
            'kode_produk' => $request->kode_produk,
        ]);

        return redirect()->route('produk-transaksi.index')->with('success', 'Produk transaksi berhasil diupdate');
    }

    public function destroy(ProdukTransaksi $produkTransaksi)
    {
        // Hapus produk transaksi
        $produkTransaksi->delete();
        return redirect()->route('produk-transaksi.index')->with('success', 'Produk transaksi berhasil dihapus');
    }
}
