@extends('layout')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <h1 class="mb-4 text-center">Analisis Apriori</h1>
            
            <div class="card main-card">
                <div class="card-header text-center">
                    <h3 class="mb-1">Parameter Analisis</h3>
                    <small class="text-muted">Tentukan parameter untuk menemukan pola pembelian produk</small>
                </div>
                <div class="card-body">
                    <form action="{{ route('apriori.aturan') }}" method="POST">
                        @csrf
                        
                        <div class="row g-5">
                            <!-- Kategori dan Produk -->
                            <div class="col-md-6">
                                <label for="kategori_produk" class="form-label">
                                    <i class="fas fa-tags text-primary"></i> Kategori Produk
                                </label>
                                <select class="form-select @error('kategori_produk') is-invalid @enderror" 
                                        id="kategori_produk" name="kategori_produk" required>
                                    <option value="">Pilih Kategori</option>
                                    <option value="tembakau" {{ old('kategori_produk') == 'tembakau' ? 'selected' : '' }}>
                                        Tembakau
                                    </option>
                                    <option value="filter" {{ old('kategori_produk') == 'filter' ? 'selected' : '' }}>
                                        Filter
                                    </option>
                                    <option value="kertas" {{ old('kategori_produk') == 'kertas' ? 'selected' : '' }}>
                                        Kertas
                                    </option>
                                </select>
                                @error('kategori_produk')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-6">
                                <label for="nama_produk" class="form-label">
                                    <i class="fas fa-box text-primary"></i> Produk
                                </label>
                                <select class="form-select @error('nama_produk') is-invalid @enderror" 
                                        id="nama_produk" name="nama_produk" required disabled>
                                    <option value="">Pilih kategori dulu</option>
                                </select>
                                @error('nama_produk')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                
                                <div id="loading-produk" class="d-none mt-2">
                                    <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
                                    <span class="ms-2 text-muted">Memuat produk...</span>
                                </div>
                            </div>

                            <!-- Support dan Confidence -->
                            <div class="col-md-6">
                                <label for="min_support" class="form-label">
                                    <i class="fas fa-chart-bar text-success"></i> Minimum Support
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
                                    <input type="number" 
                                           class="form-control" 
                                           id="support_percent" 
                                           min="1" 
                                           max="100" 
                                           step="1" 
                                           value="10"
                                           placeholder="%">
                                    <span class="input-group-text">%</span>
                                </div>
                                <small class="form-text text-muted">Masukkan nilai desimal (0.01-1.00) atau persen (1-100%)</small>
                                @error('min_support')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            
                            <div class="col-md-6">
                                <label for="min_confidence" class="form-label">
                                    <i class="fas fa-percentage text-info"></i> Minimum Confidence
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
                                    <input type="number" 
                                           class="form-control" 
                                           id="confidence_percent" 
                                           min="1" 
                                           max="100" 
                                           step="1" 
                                           value="50"
                                           placeholder="%">
                                    <span class="input-group-text">%</span>
                                </div>
                                <small class="form-text text-muted">Masukkan nilai desimal (0.01-1.00) atau persen (1-100%)</small>
                                @error('min_confidence')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <!-- Info Parameter -->
                        <div class="parameter-info mt-4">
                            <div class="row text-center g-3">
                                <div class="col-md-4">
                                    <div class="info-item">
                                        <i class="fas fa-chart-bar text-success"></i>
                                        <h6>Support</h6>
                                        <p>Frekuensi kemunculan item</p>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="info-item">
                                        <i class="fas fa-link text-info"></i>
                                        <h6>Confidence</h6>
                                        <p>Kekuatan hubungan item</p>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="info-item">
                                        <i class="fas fa-lightbulb text-warning"></i>
                                        <h6>Rekomendasi</h6>
                                        <p>Support ≥ 10%, Confidence ≥ 50%</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Urutan Prioritas -->
                        <div class="priority-order mt-3">
                            <div class="text-center">
                                <small class="text-muted">Urutan Prioritas:</small>
                                <div id="category-order" class="fw-bold text-primary">
                                    Pilih kategori untuk melihat urutan
                                </div>
                            </div>
                        </div>

                        <!-- Submit Button -->
                        <div class="text-center mt-4">
                            <button type="submit" class="btn btn-primary btn-lg px-5" id="submit-btn" disabled>
                                <i class="fas fa-play me-2"></i>Mulai Analisis
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.main-card {
    border: none;
    border-radius: 15px;
    box-shadow: 0 8px 30px rgba(0, 0, 0, 0.1);
    overflow: hidden;
}

.card-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border: none;
    padding: 2rem 1.5rem;
}

.card-body {
    padding: 3rem;
    background: #fafafa;
}

.form-label {
    font-weight: 600;
    color: #495057;
    margin-bottom: 0.5rem;
}

.form-select,
.form-control {
    border-radius: 10px;
    border: 1px solid #ced4da;
    padding: 1rem 1.25rem;
    font-size: 1rem;
    transition: all 0.3s ease;
}

.form-select:focus,
.form-control:focus {
    border-color: #667eea;
    box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
    transform: translateY(-1px);
}

.input-group-text {
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
    border: 1px solid #ced4da;
    border-left: none;
    border-radius: 0 10px 10px 0;
    font-weight: 600;
}

.btn-primary {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border: none;
    border-radius: 25px;
    padding: 0.75rem 2rem;
    font-weight: 600;
    box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
    transition: all 0.3s ease;
}

.btn-primary:hover:not(:disabled) {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
}

.btn-primary:disabled {
    background: #6c757d;
    box-shadow: none;
    cursor: not-allowed;
    transform: none;
}

.parameter-info {
    background: white;
    border-radius: 15px;
    padding: 1.5rem;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
}

.info-item {
    padding: 1rem;
    border-radius: 10px;
    transition: transform 0.3s ease;
}

.info-item:hover {
    transform: translateY(-3px);
    background: rgba(102, 126, 234, 0.05);
}

.info-item i {
    font-size: 1.5rem;
    margin-bottom: 0.5rem;
}

.info-item h6 {
    margin-bottom: 0.25rem;
    color: #495057;
}

.info-item p {
    margin: 0;
    font-size: 0.85rem;
    color: #6c757d;
}

.priority-order {
    background: rgba(102, 126, 234, 0.1);
    border-radius: 10px;
    padding: 1rem;
}

#category-order {
    font-size: 0.9rem;
    margin-top: 0.25rem;
}

.spinner-border-sm {
    width: 1rem;
    height: 1rem;
}

/* Animation */
.fade-in {
    animation: fadeIn 0.5s ease-out;
}

@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Responsive */
@media (max-width: 768px) {
    .card-header {
        padding: 1.5rem 1rem;
    }
    
    .card-body {
        padding: 1.5rem 1rem;
    }
    
    .parameter-info {
        padding: 1rem;
    }
    
    .btn-primary {
        width: 100%;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const kategoriSelect = document.getElementById('kategori_produk');
    const produkSelect = document.getElementById('nama_produk');
    const loadingIndicator = document.getElementById('loading-produk');
    const submitBtn = document.getElementById('submit-btn');
    const categoryOrderDiv = document.getElementById('category-order');
    
    // Data produk berdasarkan kategori
    const produkData = @json($produkByKategori ?? []);
    
    // Category order mapping
    const categoryOrders = {
        'tembakau': 'Tembakau → Filter → Kertas',
        'filter': 'Filter → Tembakau → Kertas',
        'kertas': 'Kertas → Tembakau → Filter'
    };
    
    // Update percentage display and sync values
    function syncValues(sourceInput, targetInput, isPercent = false) {
        if (isPercent) {
            // Convert percent to decimal
            const percentValue = parseFloat(sourceInput.value);
            if (!isNaN(percentValue)) {
                targetInput.value = (percentValue / 100).toFixed(2);
            }
        } else {
            // Convert decimal to percent
            const decimalValue = parseFloat(sourceInput.value);
            if (!isNaN(decimalValue)) {
                targetInput.value = Math.round(decimalValue * 100);
            }
        }
    }
    
    // Validate form
    function validateForm() {
        const kategori = kategoriSelect.value;
        const produk = produkSelect.value;
        const support = document.getElementById('min_support').value;
        const confidence = document.getElementById('min_confidence').value;
        
        submitBtn.disabled = !(kategori && produk && support && confidence);
    }
    
    // Update category order display
    function updateCategoryOrder(kategori) {
        if (kategori && categoryOrders[kategori]) {
            categoryOrderDiv.innerHTML = categoryOrders[kategori];
            categoryOrderDiv.classList.add('fade-in');
            
            setTimeout(() => {
                categoryOrderDiv.classList.remove('fade-in');
            }, 500);
        } else {
            categoryOrderDiv.innerHTML = 'Pilih kategori untuk melihat urutan';
        }
    }
    
    // Handle category change
    kategoriSelect.addEventListener('change', function() {
        const selectedKategori = this.value;
        
        // Show loading
        loadingIndicator.classList.remove('d-none');
        produkSelect.disabled = true;
        produkSelect.innerHTML = '<option value="">Memuat produk...</option>';
        
        // Update category order
        updateCategoryOrder(selectedKategori);
        
        setTimeout(() => {
            if (selectedKategori && produkData[selectedKategori]) {
                produkSelect.innerHTML = '<option value="">Pilih Produk</option>';
                
                produkData[selectedKategori].forEach(produk => {
                    const option = document.createElement('option');
                    option.value = produk.kode_produk;
                    option.textContent = produk.nama_produk;
                    option.selected = '{{ old("nama_produk") }}' === produk.kode_produk;
                    produkSelect.appendChild(option);
                });
                
                produkSelect.disabled = false;
            } else {
                produkSelect.innerHTML = '<option value="">Pilih kategori dulu</option>';
                produkSelect.disabled = true;
            }
            
            loadingIndicator.classList.add('d-none');
            validateForm();
        }, 300);
    });
    
    // Event listeners
    produkSelect.addEventListener('change', validateForm);
    
    // Support value sync
    document.getElementById('min_support').addEventListener('input', function() {
        syncValues(this, document.getElementById('support_percent'), false);
        validateForm();
    });
    
    document.getElementById('support_percent').addEventListener('input', function() {
        syncValues(this, document.getElementById('min_support'), true);
        validateForm();
    });
    
    // Confidence value sync
    document.getElementById('min_confidence').addEventListener('input', function() {
        syncValues(this, document.getElementById('confidence_percent'), false);
        validateForm();
    });
    
    document.getElementById('confidence_percent').addEventListener('input', function() {
        syncValues(this, document.getElementById('min_confidence'), true);
        validateForm();
    });
    
    // Initial setup
    syncValues(document.getElementById('min_support'), document.getElementById('support_percent'), false);
    syncValues(document.getElementById('min_confidence'), document.getElementById('confidence_percent'), false);
    
    if (kategoriSelect.value) {
        kategoriSelect.dispatchEvent(new Event('change'));
    }
    
    validateForm();
});
</script>
@endsection