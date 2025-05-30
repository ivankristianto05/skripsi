@extends('layout')

@section('content')
    <h1>Dashboard</h1>
    <div class="row">
        <!-- Card for Total Produk -->
        <div class="col-md-4 mb-3">
            <div class="card text-white bg-primary h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5>Total Produk</h5>
                    <i class="bi bi-box"></i> <!-- Icon for Product -->
                </div>
                <div class="card-body d-flex flex-column justify-content-between">
                    <!-- Displaying the dynamic number of products -->
                    <h3 class="card-title">{{ $totalProduk }} Produk</h3>
                    <p class="card-text">Jumlah produk yang tersedia di sistem Anda.</p>
                    <a href="{{ route('produk.index') }}" class="btn btn-light mt-auto">Lihat Produk</a>
                </div>
            </div>
        </div>

        <!-- Card for Total Transaksi -->
        <div class="col-md-4 mb-3">
            <div class="card text-white bg-success h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5>Total Transaksi</h5>
                    <i class="bi bi-cart"></i> <!-- Icon for Transaction -->
                </div>
                <div class="card-body d-flex flex-column justify-content-between">
                    <h3 class="card-title">{{ $totalTransaksi }} Transaksi</h3>
                    <p class="card-text">Jumlah transaksi yang telah dilakukan.</p>
                    <a href="{{ route('transaksis.index') }}" class="btn btn-light mt-auto">Lihat Transaksi</a>
                </div>
            </div>
        </div>

        <!-- Card for Recommendations -->
        <div class="col-md-4 mb-3">
            <div class="card text-white bg-warning h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5>Rekomendasi</h5>
                    <i class="bi bi-diagram-3"></i> <!-- Icon for Recommendation -->
                </div>
                <div class="card-body d-flex flex-column justify-content-between">
                    <h3 class="card-title">Paket A</h3>
                    <p class="card-text">Rekomendasi paket tembakau berdasarkan data transaksi.</p>
                    <a href="{{ route('apriori.index') }}" class="btn btn-light mt-auto">Lihat Rekomendasi</a>
                </div>
            </div>
        </div>
    </div>
@endsection
