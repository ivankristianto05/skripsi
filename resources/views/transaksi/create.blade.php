@extends('layout')

@section('content')
<div class="container mt-4">
    <div class="card shadow-sm">
        <div class="card-header">
            <h3 class="mb-0">Tambah Transaksi</h3>
        </div>
        <div class="card-body">
            <form action="{{ route('transaksis.store') }}" method="POST">
                @csrf

                <div class="mb-3">
                    <label for="tanggal_transaksi" class="form-label">Tanggal Transaksi</label>
                    <input type="date" name="tanggal_transaksi" class="form-control" required>
                </div>

                <div id="produk-wrapper">
                    <div class="mb-3 produk-item">
                        <label class="form-label">Nama Produk</label>
                        <div class="d-flex">
                            <select name="kode_produk[]" class="form-select me-2" required>
                                <option value="">Pilih Produk</option>
                                @foreach ($produks as $produk)
                                    <option value="{{ $produk->kode_produk }}">{{ $produk->nama_produk }}</option>
                                @endforeach
                            </select>
                            <button type="button" class="btn btn-outline-danger btn-sm" onclick="hapusProduk(this)">×</button>
                        </div>
                    </div>
                </div>
                <div class="d-flex justify-content-start gap-2">
                <button type="button" class="btn btn-secondary mb-3" onclick="tambahProduk()">+ Produk</button>
                <button type="submit" class="btn btn-primary mb-3">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Template tersembunyi --}}
<div id="produk-template" class="d-none">
    <div class="mb-3 produk-item">
        <label class="form-label">Nama Produk</label>
        <div class="d-flex">
            <select name="kode_produk[]" class="form-select me-2" required>
                <option value="">Pilih Produk</option>
                @foreach ($produks as $produk)
                    <option value="{{ $produk->kode_produk }}">{{ $produk->nama_produk }}</option>
                @endforeach
            </select>
            <button type="button" class="btn btn-outline-danger btn-sm" onclick="hapusProduk(this)">×</button>
        </div>
    </div>
</div>

<script>
    function tambahProduk() {
        const wrapper = document.getElementById('produk-wrapper');
        const template = document.getElementById('produk-template').innerHTML;
        wrapper.insertAdjacentHTML('beforeend', template);
    }

    function hapusProduk(button) {
        const item = button.closest('.produk-item');
        if (item) {
            item.remove();
        }
    }
</script>
@endsection
