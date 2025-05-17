@extends('layout')

@section('content')
<div class="container mt-4">
    <div class="card shadow-sm">
        <div class="card-header">
            <h3 class="mb-0">Edit Produk</h3>
        </div>
        <div class="card-body">
            <form action="{{ route('produk.update', $produk) }}" method="POST">
                @csrf
                @method('PUT')

                <div class="mb-3">
                    <label class="form-label">Kode Produk</label>
                    <input type="text" name="kode_produk" class="form-control" value="{{ $produk->kode_produk }}" readonly>
                </div>

                <div class="mb-3">
                    <label class="form-label">Nama Produk</label>
                    <input type="text" name="nama_produk" class="form-control" value="{{ $produk->nama_produk }}" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Kategori Produk</label>
                    <select name="kategori_produk" class="form-select" readonly>
                        <option value="">-- Pilih Kategori --</option>
                        <option value="tembakau" {{ $produk->kategori_produk == 'tembakau' ? 'selected' : '' }}>Tembakau</option>
                        <option value="kertas" {{ $produk->kategori_produk == 'kertas' ? 'selected' : '' }}>Kertas</option>
                        <option value="filter" {{ $produk->kategori_produk == 'filter' ? 'selected' : '' }}>Filter</option>
                    </select>
                </div>

                <button type="submit" class="btn btn-primary">Update</button>
            </form>
        </div>
    </div>
</div>
@endsection
