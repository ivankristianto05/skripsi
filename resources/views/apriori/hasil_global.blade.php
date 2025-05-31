@extends('layout')

@section('content')
<div class="container mt-4">
    <div class="card">
        <div class="card-header bg-primary text-white">
            <h1 class="mb-0 h4"><i class="fas fa-globe"></i> Hasil Pemrosesan Apriori Global</h1>
        </div>
        <div class="card-body">
            <p class="mb-2"><strong>Batch ID Proses Global:</strong>
                <span class="badge bg-secondary">{{ $batchId ?: 'Belum ada proses aktif' }}</span>
            </p>

            @if(session('status_message'))
                <div class="alert alert-info alert-dismissible fade show" role="alert">
                    {{ session('status_message') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif

            <div class="card mb-3">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-cogs"></i> Parameter Global Digunakan</h5>
                </div>
                <div class="card-body">
                    <ul class="list-unstyled mb-0">
                        <li>Minimum Support: <span class="badge bg-info">{{ $minSupport * 100 }}%</span></li>
                        <li>Minimum Confidence: <span class="badge bg-success">{{ $minConfidence * 100 }}%</span></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    {{-- TAHAP 2: Frequent 1-Itemsets Global (Hasil Job 2B) --}}
    @if ($job2bSelesai && $oneItemsetsFrequentGlobal && !empty($oneItemsetsFrequentGlobal['itemsets_1']))
        <div class="card my-4">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0"><i class="fas fa-cubes"></i> Frequent 1-Itemsets Global (Support &ge; {{ $minSupport * 100 }}%)</h5>
            </div>
            <div class="card-body">
                <p>Total Frequent 1-Itemset: <span class="badge bg-secondary">{{ count($oneItemsetsFrequentGlobal['itemsets_1']) }}</span></p>
                <div class="table-responsive">
                    <table class="table table-sm table-striped table-hover">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 5%;">No</th>
                                <th>Itemset</th>
                                <th style="width: 15%;">Support</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($oneItemsetsFrequentGlobal['itemsets_1'] as $index => $item)
                                <tr>
                                    <td>{{ $index + 1 }}</td>
                                    <td>{{ $item['itemset_display'] }}</td>
                                    <td><span class="badge bg-light-info text-info-emphasis">{{ $item['support_value_display'] }}</span></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @elseif ($job2bSelesai && (!$oneItemsetsFrequentGlobal || empty($oneItemsetsFrequentGlobal['itemsets_1'])))
        <div class="alert alert-warning my-4" role="alert">
           <i class="fas fa-info-circle"></i> Tahap 2 (Perhitungan Support) selesai, namun tidak ada 1-Itemset yang memenuhi minimum support global ({{ $minSupport * 100 }}%).
        </div>
    @elseif ($job1bSelesaiDanAdaData && $statusProsesGlobal === \App\Http\Controllers\AprioriController::STATUS_GLOBAL_JOB1B_COMPLETED_JOB2B_DISPATCHED)
        <div class="alert alert-info my-4" role="alert">
            <div class="spinner-border spinner-border-sm me-2" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            Tahap 2 (Perhitungan Support untuk Frequent Itemsets) sedang diproses...
        </div>
    @endif

    {{-- TAHAP 3: Aturan Asosiasi Global dari 2 & 3 Itemset (Hasil Job 3B) --}}
    @if ($job3bSelesai && $rulesFromTwoAndThreeItemsetsGlobal !== null)
        <div class="card my-4">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0"><i class="fas fa-share-alt"></i> Aturan Asosiasi Global (dari 2 & 3 Itemset, Confidence &ge; {{ $minConfidence * 100 }}%)</h5>
            </div>
            <div class="card-body">
                @if(empty($rulesFromTwoAndThreeItemsetsGlobal))
                    <div class="alert alert-warning mb-0">
                        <i class="fas fa-exclamation-triangle"></i> Tidak ada aturan asosiasi yang terbentuk dari 2 atau 3 itemset yang memenuhi threshold minimum confidence global, atau tidak ada frequent itemset yang cukup untuk membentuk aturan tersebut.
                    </div>
                @else
                    <p>Total Aturan Ditemukan: <span class="badge bg-secondary">{{ count($rulesFromTwoAndThreeItemsetsGlobal) }}</span></p>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover table-sm">
                            <thead class="table-success">
                                <tr>
                                    <th style="width: 5%;">No</th>
                                    <th>Aturan (IF <i class="fas fa-arrow-right"></i> THEN)</th>
                                    <th style="width: 12%;">Confidence</th>
                                    <th style="width: 10%;">Lift</th>
                                    <th style="width: 12%;">Support Aturan</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($rulesFromTwoAndThreeItemsetsGlobal as $index => $rule)
                                    <tr>
                                        <td>{{ $index + 1 }}</td>
                                        <td>
                                            <span class="fw-bold">{{ $rule['antecedent_display'] }}</span>
                                            <span class="text-muted mx-1">&rarr;</span>
                                            <span class="fw-bold text-primary">{{ $rule['consequent_display'] }}</span>
                                        </td>
                                        <td>
                                            <span class="badge bg-success-subtle text-success-emphasis">{{ $rule['confidence'] }}</span>
                                        </td>
                                        <td>
                                            @php $liftVal = (float)str_replace(',', '.', $rule['lift']); @endphp
                                            @if($liftVal > 1)
                                                <span class="badge bg-info-subtle text-info-emphasis" title="Hubungan Positif">{{ $rule['lift'] }}</span>
                                            @elseif($liftVal == 1)
                                                <span class="badge bg-secondary-subtle text-secondary-emphasis" title="Tidak Ada Hubungan Khusus">{{ $rule['lift'] }}</span>
                                            @else
                                                <span class="badge bg-warning-subtle text-warning-emphasis" title="Hubungan Negatif">{{ $rule['lift'] }}</span>
                                            @endif
                                        </td>
                                        <td><span class="badge bg-light-info text-info-emphasis">{{ $rule['support_rule'] }}</span></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="alert alert-secondary mt-3 p-2 small">
                        <strong>Interpretasi Nilai:</strong>
                        <ul class="mb-0 ps-3">
                            <li><strong>Confidence:</strong> Probabilitas munculnya consequent (THEN) jika antecedent (IF) muncul.</li>
                            <li><strong>Support Aturan:</strong> Seberapa sering keseluruhan item dalam aturan muncul bersamaan dalam transaksi.</li>
                            <li><strong>Lift:</strong> Kekuatan hubungan. Lift > 1 (Positif), Lift = 1 (Netral), Lift < 1 (Negatif).</li>
                        </ul>
                    </div>
                @endif
            </div>
        </div>
     @elseif ($job2bSelesai && $job1bSelesaiDanAdaData && ($statusProsesGlobal === \App\Http\Controllers\AprioriController::STATUS_GLOBAL_JOB2B_COMPLETED_JOB3B_DISPATCHED || $statusProsesGlobal === \App\Http\Controllers\AprioriController::STATUS_GLOBAL_JOB1B_COMPLETED_JOB2B_DISPATCHED) && !$job3bSelesai )
        {{-- Kondisi ini menangkap saat job 2 selesai dan job 3 sedang dispatch --}}
        <div class="alert alert-info my-4" role="alert">
            <div class="spinner-border spinner-border-sm me-2" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            Tahap 3 (Pembuatan Aturan Asosiasi Global) sedang diproses...
        </div>
    @endif


    @if ($statusProsesGlobal === \App\Http\Controllers\AprioriController::STATUS_GLOBAL_ALL_JOBS_COMPLETED && !$oneItemsetsFrequentGlobal && !$rulesFromTwoAndThreeItemsetsGlobal)
        <div class="alert alert-light border mt-4">
           <i class="fas fa-check-circle text-success"></i> Semua proses global telah selesai, namun tidak ditemukan frequent 1-itemset atau aturan asosiasi yang relevan berdasarkan parameter yang diberikan.
        </div>
    @endif

    <div class="my-4 text-center">
        <a href="{{ route('apriori.index') }}" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left"></i> Kembali ke Form Input Interaktif
        </a>
    </div>
</div>
@endsection