@extends('layout')

@section('content')
<div class="container">
    <h1 class="mb-4">Hasil Analisis Apriori</h1>

    @if(session('status_message'))
        <div class="alert alert-info">
            {{ session('status_message') }}
        </div>
    @endif

    {{-- Card Parameter --}}
    <div class="card mb-4">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0"><i class="fas fa-cog"></i> Parameter yang Digunakan</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <p class="mb-2"><strong>Produk Target:</strong><br>
                    {{ $produkTerpilih->nama_produk }} ({{ $namaProduk }})<br>
                    <small class="text-muted">Kategori: {{ $kategoriProduk }}</small></p>
                </div>
                <div class="col-md-3">
                    <p class="mb-2"><strong>Minimum Support:</strong><br>
                    <span class="badge bg-info">{{ $minSupport * 100 }}%</span></p>
                </div>
                <div class="col-md-3">
                    <p class="mb-2"><strong>Minimum Confidence:</strong><br>
                    <span class="badge bg-success">{{ $minConfidence * 100 }}%</span></p>
                </div>
            </div>
            <div class="row">
                <div class="col-md-8">
                    <p class="mb-0 text-muted"><small>Waktu Submit: {{ \Carbon\Carbon::parse($processData['submitted_at'])->format('d M Y H:i:s') }}</small></p>
                </div>
                <div class="col-md-4 text-end">
                    @if(isset($processData['last_checked']))
                        <p class="mb-0 text-muted"><small>Last Check: {{ \Carbon\Carbon::parse($processData['last_checked'])->format('H:i:s') }}</small></p>
                    @endif
                </div>
            </div>
        </div>
    </div>
    {{-- Auto refresh dengan interval yang lebih panjang dan kondisi yang lebih tepat --}}
    @if (!$job3Completed)
        <div class="text-center mb-4">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="mt-2">Sedang memproses data... 
                <span id="countdown">30</span> detik hingga refresh otomatis.
                <button onclick="window.location.reload()" class="btn btn-sm btn-outline-primary ms-2">
                    <i class="fas fa-sync-alt"></i> Refresh Sekarang
                </button>
            </p>
        </div>
        
        <script>
        let countdown = 30;
        const countdownElement = document.getElementById('countdown');
        
        const timer = setInterval(function() {
            countdown--;
            if (countdownElement) {
                countdownElement.textContent = countdown;
            }
            
            if (countdown <= 0) {
                clearInterval(timer);
                window.location.reload();
            }
        }, 1000);
        
        // Auto refresh setelah 30 detik
        setTimeout(function(){ 
            window.location.reload(1); 
        }, 30000);
        </script>
    @else
        {{-- Tampilkan pesan sukses jika semua job selesai --}}
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i> Semua proses analisis Apriori telah selesai!
        </div>
    @endif

    {{-- Kombinasi Itemset --}}
    @if ($job1Completed && $rawFormattedItemsets)
        <div class="card mb-4">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0"><i class="fas fa-list"></i> Kombinasi Itemset</h5>
            </div>
            <div class="card-body">
                <p>Total Kombinasi: <span class="badge bg-secondary">{{ $rawFormattedItemsets['total_kombinasi'] }}</span></p>
                
                @if(empty($rawFormattedItemsets['itemsets_1']) && empty($rawFormattedItemsets['itemsets_2']) && empty($rawFormattedItemsets['itemsets_3']))
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i> Tidak ada kombinasi itemset yang relevan dengan produk target.
                    </div>
                @else
                    <div class="accordion" id="accordionItemsets">
                        @if(!empty($rawFormattedItemsets['itemsets_1']))
                            <div class="accordion-item">
                                <h2 class="accordion-header" id="heading1">
                                    <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapse1">
                                        <i class="fas fa-cube me-2"></i> 1-Itemset ({{ count($rawFormattedItemsets['itemsets_1']) }} item)
                                    </button>
                                </h2>
                                <div id="collapse1" class="accordion-collapse collapse show" data-bs-parent="#accordionItemsets">
                                    <div class="accordion-body">
                                        <div class="table-responsive">
                                            <table class="table table-sm table-striped">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th>No</th>
                                                        <th>Itemset</th>
                                                        <th>Support</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @foreach($rawFormattedItemsets['itemsets_1'] as $index => $item)
                                                        <tr>
                                                            <td>{{ $index + 1 }}</td>
                                                            <td>{{ $item['itemset_display'] }}</td>
                                                            <td><span class="badge bg-light text-dark">{{ $item['support_value_display'] }}</span></td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endif

                        @if(!empty($rawFormattedItemsets['itemsets_2']))
                            <div class="accordion-item">
                                <h2 class="accordion-header" id="heading2">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse2">
                                        <i class="fas fa-cubes me-2"></i> 2-Itemset ({{ count($rawFormattedItemsets['itemsets_2']) }} kombinasi)
                                    </button>
                                </h2>
                                <div id="collapse2" class="accordion-collapse" data-bs-parent="#accordionItemsets">
                                    <div class="accordion-body">
                                        <div class="table-responsive">
                                            <table class="table table-sm table-striped">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th>No</th>
                                                        <th>Itemset</th>
                                                        <th>Support</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @foreach($rawFormattedItemsets['itemsets_2'] as $index => $item)
                                                        <tr>
                                                            <td>{{ $index + 1 }}</td>
                                                            <td>{{ $item['itemset_display'] }}</td>
                                                            <td><span class="badge bg-light text-dark">{{ $item['support_value_display'] }}</span></td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endif

                        @if(!empty($rawFormattedItemsets['itemsets_3']))
                            <div class="accordion-item">
                                <h2 class="accordion-header" id="heading3">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse3">
                                        <i class="fas fa-layer-group me-2"></i> 3-Itemset ({{ count($rawFormattedItemsets['itemsets_3']) }} kombinasi)
                                    </button>
                                </h2>
                                <div id="collapse3" class="accordion-collapse" data-bs-parent="#accordionItemsets">
                                    <div class="accordion-body">
                                        <div class="table-responsive">
                                            <table class="table table-sm table-striped">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th>No</th>
                                                        <th>Itemset</th>
                                                        <th>Support</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @foreach($rawFormattedItemsets['itemsets_3'] as $index => $item)
                                                        <tr>
                                                            <td>{{ $index + 1 }}</td>
                                                            <td>{{ $item['itemset_display'] }}</td>
                                                            <td><span class="badge bg-light text-dark">{{ $item['support_value_display'] }}</span></td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>
                @endif
            </div>
        </div>
    @endif

    {{-- Aturan Asosiasi --}}
    @if ($job3Completed && $rules !== null)
        <div class="card mb-4">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0"><i class="fas fa-share-alt"></i> Aturan Asosiasi</h5>
            </div>
            <div class="card-body">
                <p>Total Aturan: <span class="badge bg-secondary">{{ count($rules) }}</span> 
                <small class="text-muted">(Min Confidence ≥ {{ $minConfidence * 100 }}%)</small></p>
                
                @if(empty($rules))
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i> Tidak ada aturan asosiasi yang memenuhi threshold minimum confidence atau relevan dengan produk target.
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead class="table-success">
                                <tr>
                                    <th>No</th>
                                    <th>Aturan (IF → THEN)</th>
                                    <th>Confidence</th>
                                    <th>Lift</th>
                                    <th>Support</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($rules as $index => $rule)
                                    <tr>
                                        <td>{{ $index + 1 }}</td>
                                        <td>
                                            <strong>{{ $rule['antecedent_display'] }}</strong>
                                            <span class="text-muted mx-2">→</span>
                                            <strong class="text-primary">{{ $rule['consequent_display'] }}</strong>
                                        </td>
                                        <td>
                                            <span class="badge bg-success">{{ $rule['confidence'] }}</span>
                                        </td>
                                        <td>
                                            @if((float)str_replace(',', '.', $rule['lift']) > 1)
                                                <span class="badge bg-info">{{ $rule['lift'] }}</span>
                                                <small class="text-success d-block">Positif</small>
                                            @elseif((float)str_replace(',', '.', $rule['lift']) == 1)
                                                <span class="badge bg-secondary">{{ $rule['lift'] }}</span>
                                                <small class="text-muted d-block">Netral</small>
                                            @else
                                                <span class="badge bg-warning">{{ $rule['lift'] }}</span>
                                                <small class="text-danger d-block">Negatif</small>
                                            @endif
                                        </td>
                                        <td><span class="badge bg-light text-dark">{{ $rule['support_rule'] }}</span></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    
                    {{-- Interpretasi --}}
                    <div class="alert alert-info mt-3">
                        <h6><i class="fas fa-info-circle"></i> Interpretasi Nilai:</h6>
                        <div class="row">
                            <div class="col-md-6">
                                <ul class="mb-0 small">
                                    <li><strong>Confidence:</strong> Probabilitas munculnya consequent ketika antecedent muncul</li>
                                    <li><strong>Support:</strong> Seberapa sering kombinasi muncul dalam keseluruhan transaksi</li>
                                </ul>
                            </div>
                            <div class="col-md-6">
                                <ul class="mb-0 small">
                                    <li><strong>Lift > 1:</strong> Hubungan positif (saling mendukung)</li>
                                    <li><strong>Lift = 1:</strong> Tidak ada hubungan khusus</li>
                                    <li><strong>Lift < 1:</strong> Hubungan negatif (saling mengurangi)</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    @endif

    {{-- Navigation --}}
    <div class="d-flex justify-content-between align-items-center mt-4">
        <a href="{{ route('apriori.index') }}" class="btn btn-outline-primary">
            <i class="fas fa-arrow-left"></i> Kembali ke Form Input
        </a>
        @if($job3Completed && !empty($rules))
            <button class="btn btn-success" onclick="window.print()">
                <i class="fas fa-print"></i> Print Hasil
            </button>
        @endif
    </div>

</div>

{{-- Print Styles --}}
<style>
@media print {
    .btn, .accordion-button {
        display: none !important;
    }
    .accordion-collapse {
        display: block !important;
    }
    .card {
        border: 1px solid #000 !important;
        box-shadow: none !important;
    }
    .badge {
        border: 1px solid #000 !important;
    }
}
</style>

@endsection