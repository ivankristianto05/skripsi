@extends('layout')

@section('content')
<div class="container">
    <h1>Daftar Transaksi</h1>

    <div class="d-flex gap-2 mb-3">
        <a href="{{ route('transaksis.create') }}" class="btn btn-primary">
            <i class="bi bi-plus-circle"></i> Tambah Transaksi
        </a>
        <a href="{{ route('transaksis.import.form') }}" class="btn btn-success">
            <i class="bi bi-upload"></i> Import File Excel
        </a>
    </div>

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <table class="table table-bordered">
        <thead>
            <tr>
                <th>Kode Transaksi</th>
                <th>Tanggal</th>
                <th>Produk Dibeli</th>
                <th>Aksi</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($transaksis as $transaksi)
                <tr>
                    <td>{{ $transaksi->kode_transaksi }}</td>
                    <td>{{ $transaksi->tanggal_transaksi }}</td>
                    <td>
                        <ul>
                        @foreach ($transaksi->produkTransaksis as $produkTransaksi)
                            <li>{{ $produkTransaksi->produk->nama_produk }}</li>
                        @endforeach
                        </ul>
                    </td>
                    <td>
                        <a href="{{ route('transaksis.edit', $transaksi->kode_transaksi) }}" class="btn btn-sm btn-warning">Edit</a>
                        <form action="{{ route('transaksis.destroy', $transaksi->kode_transaksi) }}" method="POST" style="display:inline-block;">
                            @csrf
                            @method('DELETE')
                            <button class="btn btn-sm btn-danger" onclick="return confirm('Yakin hapus?')">Hapus</button>
                        </form>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <!-- Add pagination controls -->
    <div class="d-flex justify-content-center mt-3">
        {{ $transaksis->links('pagination::bootstrap-4') }} <!-- Pagination links -->
    </div>
</div>
@endsection
