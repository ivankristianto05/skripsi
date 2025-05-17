@extends('layout')

@section('content')
    <div class="container mt-4">
        <div class="card shadow-sm">
            <div class="card-header">
                <h3 class="mb-0">Tambah Produk</h3>
            </div>
            <div class="card-body">
                <form action="{{ route('produk.store') }}" method="POST">
                    @csrf

                    <div class="mb-3">
                        <label for="nama_produk" class="form-label">Nama Produk</label>
                        <input type="text" id="nama_produk" name="nama_produk" class="form-control" required>
                    </div>

                    <div class="mb-3">
                        <label for="kategori_produk" class="form-label">Kategori Produk</label>
                        <select id="kategori_produk" name="kategori_produk" class="form-select" required>
                            <option value="">-- Pilih Kategori --</option>
                            <option value="tembakau">Tembakau</option>
                            <option value="kertas">Kertas</option>
                            <option value="filter">Filter</option>
                        </select>
                    </div>

                    <button type="submit" class="btn btn-primary">Simpan</button>
                </form>
            </div>
        </div>
    </div>
@endsection
