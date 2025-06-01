@extends('layout')

@section('content')
    <h1>Daftar Produk</h1>

    {{-- Tombol Tambah dan Import hanya untuk Admin --}}
    @auth
        @if(auth()->user()->role === 'admin')
            <a href="{{ route('produk.create') }}" class="btn btn-primary mb-3">
                <i class="bi bi-plus-circle"></i> Tambah Produk
            </a>
            <a href="{{ route('produk.import.form') }}" class="btn btn-success mb-3 ms-2">
                <i class="bi bi-upload"></i> Import Produk
            </a>
        @endif
    @endauth

    {{-- Notifikasi sukses --}}
    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <!-- Search and Filter Form -->
    <form method="GET" action="{{ route('produk.index') }}" class="row mb-4">
        <div class="col-md-4">
            <input type="text" name="search" class="form-control" placeholder="Cari nama produk..." value="{{ request('search') }}">
        </div>
        <div class="col-md-4">
            <select name="kategori" class="form-control">
                <option value="">-- Semua Kategori --</option>
                @php
                    $allCategories = \App\Models\Produk::select('kategori_produk')->distinct()->pluck('kategori_produk');
                @endphp
                @foreach ($allCategories as $kategori)
                    <option value="{{ $kategori }}" {{ request('kategori') == $kategori ? 'selected' : '' }}>
                        {{ $kategori }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="col-md-4">
            <button class="btn btn-primary" type="submit">Cari</button>
            <a href="{{ route('produk.index') }}" class="btn btn-secondary">Reset</a>
        </div>
    </form>

    <!-- Table -->
    <table class="table table-bordered">
        <thead>
            <tr>
                <th>Kode</th>
                <th>Nama</th>
                <th>Kategori</th>
                @auth
                    @if(auth()->user()->role === 'admin')
                        <th>Aksi</th>
                    @endif
                @endauth
            </tr>
        </thead>
        <tbody>
            @forelse ($produks as $produk)
                <tr>
                    <td>{{ $produk->kode_produk }}</td>
                    <td>{{ $produk->nama_produk }}</td>
                    <td>{{ $produk->kategori_produk }}</td>
                    @auth
                        @if(auth()->user()->role === 'admin')
                            <td>
                                <a href="{{ route('produk.edit', $produk) }}" class="btn btn-sm btn-warning">Edit</a>
                                <form action="{{ route('produk.destroy', $produk) }}" method="POST" style="display:inline-block;">
                                    @csrf @method('DELETE')
                                    <button onclick="return confirm('Yakin hapus?')" class="btn btn-sm btn-danger">Hapus</button>
                                </form>
                            </td>
                        @endif
                    @endauth
                </tr>
            @empty
                <tr>
                    <td colspan="{{ auth()->check() && auth()->user()->role === 'admin' ? 4 : 3 }}" class="text-center">
                        Produk tidak ditemukan.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <!-- Pagination -->
    <div class="d-flex justify-content-center mt-3">
        {{ $produks->links('pagination::bootstrap-4') }}
    </div>
@endsection
