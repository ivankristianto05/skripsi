@extends('layout')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <h1 class="mb-4">Analisis Apriori - Parameter Input</h1>
            
            <div class="card">
                <div class="card-header">
                    <h3 class="mb-0">Setting Parameter Analisis</h3>
                    <small class="text-muted">Masukkan parameter untuk analisis association rules</small>
                </div>
                <div class="card-body">
                    <form action="{{ route('apriori.aturan') }}" method="POST">
                        @csrf
                        
                        <!-- Pilih Nama Tembakau -->
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <label for="nama_tembakau" class="form-label">
                                    <i class="fas fa-leaf"></i> Pilih Produk Tembakau *
                                </label>
                                <select class="form-select @error('nama_tembakau') is-invalid @enderror" 
                                        id="nama_tembakau" name="nama_tembakau" required>
                                    <option value="">-- Pilih Produk Tembakau --</option>
                                    @foreach($produkTembakau as $produk)
                                        <option value="{{ $produk->kode_produk }}" {{ old('nama_tembakau') == $produk->kode_produk ? 'selected' : '' }}>
                                            {{ $produk->nama_produk }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('nama_tembakau')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <small class="form-text text-muted">
                                    Pilih produk tembakau sebagai fokus analisis
                                </small>
                            </div>
                        </div>

                        <!-- Parameter Support dan Confidence -->
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <label for="min_support" class="form-label">
                                    <i class="fas fa-chart-bar"></i> Minimum Support *
                                </label>
                                <div class="input-group">
                                    <input type="number" 
                                           class="form-control @error('min_support') is-invalid @enderror" 
                                           id="min_support" 
                                           name="min_support" 
                                           value="{{ old('min_support', '0.1') }}" 
                                           min="0.01" 
                                           max="1" 
                                           step="0.01" 
                                           required>
                                    <span class="input-group-text">%</span>
                                </div>
                                @error('min_support')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <small class="form-text text-muted">
                                    Nilai minimum support (0.01 - 1.00). Contoh: 0.1 = 10%
                                </small>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="min_confidence" class="form-label">
                                    <i class="fas fa-percentage"></i> Minimum Confidence *
                                </label>
                                <div class="input-group">
                                    <input type="number" 
                                           class="form-control @error('min_confidence') is-invalid @enderror" 
                                           id="min_confidence" 
                                           name="min_confidence" 
                                           value="{{ old('min_confidence', '0.5') }}" 
                                           min="0.01" 
                                           max="1" 
                                           step="0.01" 
                                           required>
                                    <span class="input-group-text">%</span>
                                </div>
                                @error('min_confidence')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <small class="form-text text-muted">
                                    Nilai minimum confidence (0.01 - 1.00). Contoh: 0.5 = 50%
                                </small>
                            </div>
                        </div>

                        <!-- Info Box -->
                        <div class="alert alert-info">
                            <h5><i class="fas fa-info-circle"></i> Informasi Parameter</h5>
                            <ul class="mb-0">
                                <li><strong>Support:</strong> Seberapa sering kombinasi item muncul dalam dataset</li>
                                <li><strong>Confidence:</strong> Seberapa kuat hubungan antara antecedent dan consequent</li>
                                <li><strong>Nilai Rekomendasi:</strong> Support ≥ 0.1 (10%), Confidence ≥ 0.5 (50%)</li>
                            </ul>
                        </div>

                        <!-- Tombol Submit -->
                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="fas fa-calculator"></i> Mulai Analisis Apriori
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Informasi Tambahan -->
            <div class="row mt-4">
                <div class="col-md-4">
                    <div class="card border-primary">
                        <div class="card-body text-center">
                            <i class="fas fa-database fa-2x text-primary mb-2"></i>
                            <h5>Support</h5>
                            <p class="small text-muted">Mengukur seberapa sering item muncul bersamaan</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card border-success">
                        <div class="card-body text-center">
                            <i class="fas fa-link fa-2x text-success mb-2"></i>
                            <h5>Confidence</h5>
                            <p class="small text-muted">Mengukur kekuatan hubungan antar item</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card border-warning">
                        <div class="card-body text-center">
                            <i class="fas fa-search fa-2x text-warning mb-2"></i>
                            <h5>Analysis</h5>
                            <p class="small text-muted">Menemukan pola pembelian produk</p>
                        </div>
                    </div>
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

.form-label {
    font-weight: 600;
    color: #495057;
}

.btn-primary {
    background: linear-gradient(45deg, #007bff, #0056b3);
    border: none;
    box-shadow: 0 2px 4px rgba(0, 123, 255, 0.3);
}

.btn-primary:hover {
    background: linear-gradient(45deg, #0056b3, #004085);
    transform: translateY(-1px);
    box-shadow: 0 4px 8px rgba(0, 123, 255, 0.4);
}

.alert-info {
    background: linear-gradient(135deg, #d1ecf1, #bee5eb);
    border-color: #bee5eb;
}

.card-header {
    background: linear-gradient(135deg, #f8f9fa, #e9ecef);
    border-bottom: 1px solid #dee2e6;
}

.input-group-text {
    background-color: #e9ecef;
    border-color: #ced4da;
}

.form-select:focus,
.form-control:focus {
    border-color: #80bdff;
    box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Format percentage display
    const supportInput = document.getElementById('min_support');
    const confidenceInput = document.getElementById('min_confidence');
    
    function updatePercentage(input) {
        const value = parseFloat(input.value);
        if (!isNaN(value)) {
            const percentage = Math.round(value * 100);
            input.nextElementSibling.textContent = percentage + '%';
        }
    }
    
    supportInput.addEventListener('input', function() {
        updatePercentage(this);
    });
    
    confidenceInput.addEventListener('input', function() {
        updatePercentage(this);
    });
    
    // Initial update
    updatePercentage(supportInput);
    updatePercentage(confidenceInput);
});
</script>
@endsection