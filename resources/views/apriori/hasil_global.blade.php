@extends('layout')

@section('content')
<div class="container">
    <h1>Hasil Pemrosesan Apriori Global</h1>
    <p><strong>Batch ID Proses Global:</strong> {{ $batchId ?: 'Belum ada proses aktif' }}</p>

    @if(session('status_message'))
        <div class="alert alert-info">
            {{ session('status_message') }}
        </div>
    @endif

    <h2>Parameter Global yang Digunakan (Default atau dari Konfigurasi):</h2>
    <ul>
        <li>Minimum Support (untuk Job 2b): {{ $minSupport * 100 }}%</li>
        <li>Minimum Confidence (untuk Job 3b): {{ $minConfidence * 100 }}%</li>
    </ul>

    <h2>Status Proses Global Saat Ini:</h2>
    <p>
        <strong>Status:</strong>
        @if ($statusProsesGlobal === \App\Http\Controllers\AprioriController::STATUS_GLOBAL_ALL_JOBS_COMPLETED)
            <span class="badge bg-success">Semua Job Selesai</span>
        @elseif ($statusProsesGlobal === \App\Http\Controllers\AprioriController::STATUS_GLOBAL_JOB1B_COMPLETED_JOB2B_DISPATCHED || $statusProsesGlobal === \App\Http\Controllers\AprioriController::STATUS_GLOBAL_JOB2B_COMPLETED_JOB3B_DISPATCHED)
            <span class="badge bg-primary">Job Lanjutan Sedang Berjalan</span> (Job 1b Selesai)
        @elseif ($statusProsesGlobal === \App\Http\Controllers\AprioriController::STATUS_GLOBAL_JOB1B_DISPATCHED)
            <span class="badge bg-info text-dark">Job 1b (Kombinasi Itemset) Sedang Berjalan</span>
            <div class="spinner-border spinner-border-sm" role="status"><span class="visually-hidden">Loading...</span></div>
        @elseif (Str::startsWith($statusProsesGlobal, \App\Http\Controllers\AprioriController::STATUS_GLOBAL_FAILED_PREFIX))
            <span class="badge bg-danger">Proses Gagal</span> (Detail: {{ $statusProsesGlobal }})
        @elseif ($statusProsesGlobal === \App\Http\Controllers\AprioriController::STATUS_GLOBAL_NOT_STARTED && $batchId)
             <span class="badge bg-warning text-dark">Inisiasi Proses</span> (Refresh untuk memulai)
        @else
            <span class="badge bg-secondary">{{ $statusProsesGlobal ?: 'Belum Dimulai' }}</span>
        @endif
    </p>

    @if (!in_array($statusProsesGlobal, [\App\Http\Controllers\AprioriController::STATUS_GLOBAL_ALL_JOBS_COMPLETED, \App\Http\Controllers\AprioriController::STATUS_GLOBAL_NOT_STARTED]) && !Str::startsWith($statusProsesGlobal, \App\Http\Controllers\AprioriController::STATUS_GLOBAL_FAILED_PREFIX))
        <p class="mt-2">Halaman ini dapat di-refresh untuk melihat update status. Implementasi auto-refresh dengan JavaScript bisa ditambahkan.</p>
        <script>
            // setTimeout(function(){ window.location.reload(1); }, 30000); // Refresh setiap 30 detik
        </script>
    @endif

    <hr>

    @if ($job1bSelesaiDanAdaData && $rawFormattedItemsetsGlobal)
        <h2>Kombinasi Itemset yang Dihasilkan (Job 1b):</h2>
        <p>Total Kombinasi: {{ $rawFormattedItemsetsGlobal['total_kombinasi'] }}</p>
        @if(empty($rawFormattedItemsetsGlobal['itemsets_1']) && empty($rawFormattedItemsetsGlobal['itemsets_2']) && empty($rawFormattedItemsetsGlobal['itemsets_3']))
            <p>Tidak ada kombinasi itemset yang dihasilkan untuk batch global ini.</p>
        @else
            @if(!empty($rawFormattedItemsetsGlobal['itemsets_1']))
                <h3>1-Itemset:</h3>
                <ul>
                    @foreach($rawFormattedItemsetsGlobal['itemsets_1'] as $item)
                        <li>{{ $item['itemset_display'] }} (Support: {{ $item['support_value_display'] }})</li>
                    @endforeach
                </ul>
            @endif
            @if(!empty($rawFormattedItemsetsGlobal['itemsets_2']))
                <h3>2-Itemset:</h3>
                <ul>
                    @foreach($rawFormattedItemsetsGlobal['itemsets_2'] as $item)
                        <li>{{ $item['itemset_display'] }} (Support: {{ $item['support_value_display'] }})</li>
                    @endforeach
                </ul>
            @endif
            @if(!empty($rawFormattedItemsetsGlobal['itemsets_3']))
                <h3>3-Itemset:</h3>
                <ul>
                    @foreach($rawFormattedItemsetsGlobal['itemsets_3'] as $item)
                        <li>{{ $item['itemset_display'] }} (Support: {{ $item['support_value_display'] }})</li>
                    @endforeach
                </ul>
            @endif
        @endif
    @elseif ($statusProsesGlobal === \App\Http\Controllers\AprioriController::STATUS_GLOBAL_JOB1B_DISPATCHED)
        <p>Kombinasi itemset global sedang dibuat...</p>
    @elseif ($statusProsesGlobal === \App\Http\Controllers\AprioriController::STATUS_GLOBAL_NOT_STARTED && !$batchId)
        <p>Proses pembuatan data Apriori global belum pernah dijalankan. Halaman ini akan mencoba memicunya.</p>
    @endif

    {{-- Placeholder untuk Frequent Itemsets Global (Hasil Job 2b) --}}
    @if ($frequentItemsetsGlobal)
        <hr>
        <h2>Frequent Itemsets Global (Hasil Job 2b):</h2>
        {{-- Logika untuk menampilkan frequent itemsets global --}}
    @elseif ($job1bSelesaiDanAdaData && $statusProsesGlobal === \App\Http\Controllers\AprioriController::STATUS_GLOBAL_JOB1B_COMPLETED_JOB2B_DISPATCHED)
        <hr>
        <p>Frequent itemsets global sedang dihitung (Job 2b sedang berjalan)...</p>
         <div class="spinner-border spinner-border-sm" role="status"><span class="visually-hidden">Loading...</span></div>
    @endif

    {{-- Placeholder untuk Aturan Asosiasi Global (Hasil Job 3b) --}}
    @if ($rulesGlobal)
        <hr>
        <h2>Aturan Asosiasi Global (Hasil Job 3b):</h2>
        {{-- Logika untuk menampilkan aturan asosiasi global --}}
    @elseif ($job1bSelesaiDanAdaData && $statusProsesGlobal === \App\Http\Controllers\AprioriController::STATUS_GLOBAL_JOB2B_COMPLETED_JOB3B_DISPATCHED)
        <hr>
        <p>Aturan asosiasi global sedang dibuat (Job 3b sedang berjalan)...</p>
        <div class="spinner-border spinner-border-sm" role="status"><span class="visually-hidden">Loading...</span></div>
    @endif

    <div class="mt-4">
        <a href="{{ route('apriori.index') }}" class="btn btn-secondary">Kembali ke Form Input Interaktif</a>
    </div>
</div>
@endsection