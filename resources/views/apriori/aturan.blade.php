@extends('layout')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <h1 class="mb-4">Analisis Apriori - Frequent Itemsets & Association Rules</h1>
            
            <!-- Info Summary -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card bg-primary text-white">
                        <div class="card-body">
                            <h5>Total Transaksi</h5>
                            <h3>{{ $frequentItemsets['total_transactions'] ?? 0 }}</h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-success text-white">
                        <div class="card-body">
                            <h5>Min Support</h5>
                            <h3>{{ ($minSupport * 100) }}%</h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-info text-white">
                        <div class="card-body">
                            <h5>Min Confidence</h5>
                            <h3>{{ ($minConfidence * 100) }}%</h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-warning text-white">
                        <div class="card-body">
                            <h5>Rules Generated</h5>
                            <h3>{{ count($rules) }}</h3>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Frequent Itemsets Section -->
            <div class="card mb-4">
                <div class="card-header">
                    <h2 class="mb-0">Frequent Itemsets</h2>
                </div>
                <div class="card-body">
                    <!-- Itemsets 1 -->
                    <h3>1-Itemsets (Produk Individu)</h3>
                    @if(!empty($frequentItemsets['itemsets_1']))
                    <div class="table-responsive mb-4">
                        <table class="table table-striped table-hover">
                            <thead class="table-dark">
                                <tr>
                                    <th>No</th>
                                    <th>Nama Produk</th>
                                    <th>Support Count</th>
                                    <th>Support (%)</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($frequentItemsets['itemsets_1'] as $index => $item)
                                <tr>
                                    <td>{{ $index + 1 }}</td>
                                    <td>{{ $item['itemset'] }}</td>
                                    <td>{{ $item['support_count'] }}</td>
                                    <td>{{ ($item['support_percentage'] * 100) }}%</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @else
                    <p class="text-muted">Tidak ada 1-itemset yang memenuhi minimum support.</p>
                    @endif

                    <!-- Itemsets 2 -->
                    <h3>2-Itemsets (Pasangan Produk)</h3>
                    @if(!empty($frequentItemsets['itemsets_2']))
                    <div class="table-responsive mb-4">
                        <table class="table table-striped table-hover">
                            <thead class="table-dark">
                                <tr>
                                    <th>No</th>
                                    <th>Kombinasi Produk</th>
                                    <th>Support Count</th>
                                    <th>Support (%)</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($frequentItemsets['itemsets_2'] as $index => $item)
                                <tr>
                                    <td>{{ $index + 1 }}</td>
                                    <td>{{ $item['itemset'] }}</td>
                                    <td>{{ $item['support_count'] }}</td>
                                    <td>{{ ($item['support_percentage'] * 100) }}%</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @else
                    <p class="text-muted">Tidak ada 2-itemset yang memenuhi minimum support.</p>
                    @endif

                    <!-- Itemsets 3 -->
                    <h3>3-Itemsets (Tiga Produk)</h3>
                    @if(!empty($frequentItemsets['itemsets_3']))
                    <div class="table-responsive mb-4">
                        <table class="table table-striped table-hover">
                            <thead class="table-dark">
                                <tr>
                                    <th>No</th>
                                    <th>Kombinasi Produk</th>
                                    <th>Support Count</th>
                                    <th>Support (%)</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($frequentItemsets['itemsets_3'] as $index => $item)
                                <tr>
                                    <td>{{ $index + 1 }}</td>
                                    <td>{{ $item['itemset'] }}</td>
                                    <td>{{ $item['support_count'] }}</td>
                                    <td>{{ ($item['support_percentage'] * 100) }}%</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @else
                    <p class="text-muted">Tidak ada 3-itemset yang memenuhi minimum support.</p>
                    @endif
                </div>
            </div>

            <!-- Association Rules Section -->
            <div class="card">
                <div class="card-header">
                    <h2 class="mb-0">Association Rules</h2>
                </div>
                <div class="card-body">
                    @if(!empty($rules))
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead class="table-dark">
                                <tr>
                                    <th>No</th>
                                    <th>Antecedent (Jika)</th>
                                    <th>Consequent (Maka)</th>
                                    <th>Antecedent Support</th>
                                    <th>Union Support</th>
                                    <th>Confidence (%)</th>
                                    <th>Interpretasi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($rules as $index => $rule)
                                <tr>
                                    <td>{{ $index + 1 }}</td>
                                    <td>
                                        <span class="badge bg-primary">
                                            {{ implode(' + ', $rule['antecedent']) }}
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-success">
                                            {{ implode(' + ', $rule['consequent']) }}
                                        </span>
                                    </td>
                                    <td>{{ $rule['antecedent_support'] }}</td>
                                    <td>{{ $rule['union_support'] }}</td>
                                    <td>
                                        <span class="badge bg-{{ $rule['confidence'] >= 0.7 ? 'success' : ($rule['confidence'] >= 0.5 ? 'warning' : 'secondary') }}">
                                            {{ ($rule['confidence'] * 100) }}%
                                        </span>
                                    </td>
                                    <td class="small">
                                        Jika pelanggan membeli <strong>{{ implode(' dan ', $rule['antecedent']) }}</strong>, 
                                        maka ada kemungkinan <strong>{{ ($rule['confidence'] * 100) }}%</strong> 
                                        untuk membeli <strong>{{ implode(' dan ', $rule['consequent']) }}</strong>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @else
                    <div class="alert alert-info">
                        <h5>Tidak Ada Rules yang Ditemukan</h5>
                        <p>Tidak ada association rules yang memenuhi kriteria minimum support ({{ ($minSupport * 100) }}%) dan minimum confidence ({{ ($minConfidence * 100) }}%).</p>
                        <p>Coba turunkan nilai minimum support atau minimum confidence untuk mendapatkan lebih banyak rules.</p>
                    </div>
                    @endif
                </div>
            </div>

        </div>
    </div>
</div>

<style>
.card {
    box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
    border: 1px solid rgba(0, 0, 0, 0.125);
}

.table-responsive {
    max-height: 500px;
    overflow-y: auto;
}

.badge {
    font-size: 0.75em;
}

.card-header {
    background-color: #f8f9fa;
    border-bottom: 1px solid rgba(0, 0, 0, 0.125);
}
</style>
@endsection