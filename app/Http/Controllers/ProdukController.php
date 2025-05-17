<?php

namespace App\Http\Controllers;

use App\Models\Produk;
use Illuminate\Http\Request;
use App\Imports\ProdukImport;
use Maatwebsite\Excel\Facades\Excel;

class ProdukController extends Controller
{
    public function index(Request $request)
{
    $query = Produk::query();

    if ($request->filled('search')) {
        $query->where('nama_produk', 'like', '%' . $request->search . '%');
    }

    if ($request->filled('kategori')) {
        $query->where('kategori_produk', $request->kategori);
    }

    $produks = $query->paginate(10); // ganti dengan ->paginate(10) jika kamu pakai pagination

    return view('produk.index', compact('produks'));

    $totalProduk = Produk::count();

    return view('dashboard  ', compact('totalProduk'));
}


    public function create()
    {
        return view('produk.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'nama_produk' => 'required',
            'kategori_produk' => 'required|in:tembakau,kertas,filter',
        ]);

        $kategori = $request->kategori_produk;

        // Tentukan prefix berdasarkan kategori
        $prefix = match ($kategori) {
            'tembakau' => 'T',
            'kertas' => 'K',
            'filter' => 'F',
        };

        // Hitung jumlah produk dengan kategori sama
        $count = Produk::where('kategori_produk', $kategori)->count() + 1;
        $kode_produk = $prefix . str_pad($count, 3, '0', STR_PAD_LEFT);

        Produk::create([
            'kode_produk' => $kode_produk,
            'nama_produk' => $request->nama_produk,
            'kategori_produk' => $kategori,
        ]);

        return redirect()->route('produk.index')->with('success', 'Produk berhasil ditambahkan!');
    }

    public function edit(Produk $produk)
    {
        return view('produk.edit', compact('produk'));
    }

    public function update(Request $request, Produk $produk)
    {
        $request->validate([
            'nama_produk' => 'required',
            'kategori_produk' => 'required|in:tembakau,kertas,filter',
        ]);

        // Tidak update kode_produk supaya tetap konsisten
        $produk->update([
            'nama_produk' => $request->nama_produk,
        ]);

        return redirect()->route('produk.index')->with('success', 'Produk berhasil diupdate!');
    }

    public function destroy(Produk $produk)
    {
        $produk->delete();
        return redirect()->route('produk.index')->with('success', 'Produk berhasil dihapus!');
    }

    public function showForm()
    {
        return view('produk.import');
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv',
        ]);

        Excel::import(new ProdukImport, $request->file('file'));

        return redirect()->route('produk.index')->with('success', 'Data produk berhasil diimpor!');
    }
}
