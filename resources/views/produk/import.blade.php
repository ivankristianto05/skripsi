@extends('layout')

@section('content')
<div class="container">
    <h1>Import Data Produk</h1>

    <form action="{{ route('produk.import') }}" method="POST" enctype="multipart/form-data">
        @csrf

        <div class="mb-3">
            <label for="file" class="form-label">Pilih File Excel</label>
            <input type="file" class="form-control" id="file" name="file" required>
        </div>

        <button type="submit" class="btn btn-success">Import</button>
        <a href="{{ route('produk.index') }}" class="btn btn-secondary">Kembali</a>
    </form>
</div>
@endsection
