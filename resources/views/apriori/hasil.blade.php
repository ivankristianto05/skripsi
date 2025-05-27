@extends('layout')

@section('content')
<div class="container">
    <h1>Status Pemrosesan Apriori (Batch ID: {{ $batchId }})</h1>

    @if(session('status_message'))
        <div class="alert alert-info">
            {{ session('status_message') }}
        </div>
    @endif

    <h2>Parameter yang Digunakan:</h2>
    <ul>
        <li>Produk Target: {{ $produkTerpilih->nama_produk }} ({{ $namaProduk }}) - Kategori: {{ $kategoriProduk }}</li>
        <li>Minimum Support (untuk Job 2): {{ $minSupport * 100 }}%</li>
        <li>Minimum Confidence (untuk Job 3): {{ $minConfidence * 100 }}%</li>
        <li>Waktu Submit: {{ \Carbon\Carbon::parse($processData['submitted_at'])->format('d M Y H:i:s') }}</li>
    </ul>

    <h2>Status Proses Job 1 (Pembuatan Kombinasi Itemset):</h2>
    <p>
        @if ($job1Completed)
            <span class="badge bg-success">Selesai</span> - {{ $itemsetKombinasiCount }} kombinasi itemset telah dibuat dan disimpan.
            <br><small>Langkah selanjutnya adalah kalkulasi support (Job 2).</small>
        @elseif ($processData['status_job1'] === 'dispatched')
            <span class="badge bg-info text-dark">Memproses Kombinasi Itemset</span> - Job pembentukan kombinasi sedang berjalan atau menunggu di antrian.
             <div class="spinner-border spinner-border-sm" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
        @else
            <span class="badge bg-secondary">Menunggu</span>
        @endif
    </p>

    @if (!$job1Completed && $processData['status_job1'] === 'dispatched')
        <p class="mt-3">Halaman ini mungkin perlu di-refresh untuk melihat update status. Atau Anda bisa mengimplementasikan auto-refresh dengan JavaScript.</p>
        {{-- <script> setTimeout(function(){ window.location.reload(1); }, 15000); </script> --}}
    @endif

    <hr>

    @if ($job1Completed && $rawFormattedItemsets)
        <h2>Kombinasi Itemset yang Dihasilkan (Job 1):</h2>
        <p>Total Kombinasi: {{ $rawFormattedItemsets['total_kombinasi'] }}</p>
        @if(empty($rawFormattedItemsets['itemsets_1']) && empty($rawFormattedItemsets['itemsets_2']) && empty($rawFormattedItemsets['itemsets_3']))
            <p>Tidak ada kombinasi itemset yang dihasilkan.</p>
        @else
            @if(!empty($rawFormattedItemsets['itemsets_1']))
                <h3>1-Itemset:</h3>
                <ul>
                    @foreach($rawFormattedItemsets['itemsets_1'] as $item)
                        <li>{{ $item['itemset_display'] }} (Support: {{ $item['support_value_display'] }})</li>
                    @endforeach
                </ul>
            @endif
            @if(!empty($rawFormattedItemsets['itemsets_2']))
                <h3>2-Itemset:</h3>
                <ul>
                    @foreach($rawFormattedItemsets['itemsets_2'] as $item)
                        <li>{{ $item['itemset_display'] }} (Support: {{ $item['support_value_display'] }})</li>
                    @endforeach
                </ul>
            @endif
            @if(!empty($rawFormattedItemsets['itemsets_3']))
                <h3>3-Itemset:</h3>
                <ul>
                    @foreach($rawFormattedItemsets['itemsets_3'] as $item)
                        <li>{{ $item['itemset_display'] }} (Support: {{ $item['support_value_display'] }})</li>
                    @endforeach
                </ul>
            @endif
        @endif
    @endif

    <div class="mt-4">
        <a href="{{ route('apriori.index') }}" class="btn btn-primary">Kembali ke Form Input</a>
    </div>

</div>
@endsection