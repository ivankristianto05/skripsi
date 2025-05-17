<?php

namespace App\Http\Controllers;

use App\Models\Transaksi;
use App\Models\Produk;
use App\Models\ProdukTransaksi;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\TransaksiImport;
class TransaksiController extends Controller
{
    public function index()
    {
        $transaksis = Transaksi::with('produkTransaksis.produk')->paginate(20);
        
        return view('transaksi.index', compact('transaksis'));
    }

    public function create()
    {
        $produks = Produk::all();
        return view('transaksi.create', compact('produks'));
    }
    public function store(Request $request)
{
    $request->validate([
        'tanggal_transaksi' => 'required|date',
        'kode_produk' => 'required|array|min:1',
        'kode_produk.*' => 'required|exists:produks,kode_produk',
    ]);

    // Generate kode transaksi unik
    $lastTransaksi = Transaksi::orderBy('kode_transaksi', 'desc')->first();
    if ($lastTransaksi) {
        $lastNumber = (int) substr($lastTransaksi->kode_transaksi, 4);
        $newNumber = $lastNumber + 1;
    } else {
        $newNumber = 1;
    }
    $kode = 'TRS' . str_pad($newNumber, 4, '0', STR_PAD_LEFT);

    // Simpan transaksi
    $transaksi = Transaksi::create([
        'kode_transaksi' => $kode,
        'tanggal_transaksi' => $request->tanggal_transaksi,
    ]);

    // Simpan produk yang dibeli
    foreach ($request->kode_produk as $kodeProduk) {
        ProdukTransaksi::create([
            'kode_transaksi' => $kode,
            'kode_produk' => $kodeProduk,
        ]);
    }

    return redirect()->route('transaksis.index')->with('success', 'Transaksi berhasil disimpan.');
}
    

    public function show($kode_transaksi)
    {
        $transaksi = Transaksi::with('produks')->findOrFail($kode_transaksi);
        return view('transaksi.show', compact('transaksi'));
    }

    public function edit($kode_transaksi)
    {
        $transaksi = Transaksi::with('produkTransaksis')->findOrFail($kode_transaksi);
        $produks = Produk::all();
        return view('transaksi.edit', compact('transaksi', 'produks'));
    }

    public function update(Request $request, $kode_transaksi)
{
    $request->validate([
        'tanggal_transaksi' => 'required|date',
        'kode_produk' => 'required|array|min:1',
        'kode_produk.*' => 'required|exists:produks,kode_produk',
    ]);

    $transaksi = Transaksi::findOrFail($kode_transaksi);
    $transaksi->update([
        'tanggal_transaksi' => $request->tanggal_transaksi,
    ]);

    // Hapus produk sebelumnya
    $transaksi->produkTransaksis()->delete();

    // Simpan ulang
    foreach ($request->kode_produk as $kodeProduk) {
        ProdukTransaksi::create([
            'kode_transaksi' => $kode_transaksi,
            'kode_produk' => $kodeProduk,
        ]);
    }

    return redirect()->route('transaksis.index')->with('success', 'Transaksi berhasil diupdate.');
}


    public function destroy($kode_transaksi)
    {
        $transaksi = Transaksi::findOrFail($kode_transaksi);
        $transaksi->produks()->detach();
        $transaksi->delete();

        return redirect()->route('transaksis.index')->with('success', 'Transaksi berhasil dihapus.');
    }

    public function import(Request $request)
{
    $request->validate([
        'file' => 'required|mimes:xlsx,csv,xls',
    ]);

    Excel::import(new TransaksiImport, $request->file('file'));

    return redirect()->route('transaksis.index')->with('success', 'Data berhasil diimpor!');
}
public function importForm()
{
    return view('transaksi.import');
}


}
