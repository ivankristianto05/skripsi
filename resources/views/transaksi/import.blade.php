@extends('layout')

@section('content')
<div class="container">
    <h1>Import Transaksi dari Excel</h1>

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form action="{{ route('transaksis.import') }}" method="POST" enctype="multipart/form-data">
        @csrf
        <div class="mb-3">
            <label for="file" class="form-label">Pilih file Excel (.xlsx, .xls, .csv)</label>
            <input type="file" class="form-control" name="file" id="file" required>
        </div>
        <button type="submit" class="btn btn-primary">Import</button>
        <a href="{{ route('transaksis.index') }}" class="btn btn-secondary">Kembali</a>
    </form>
</div>
@endsection
