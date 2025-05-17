@extends('layout')

@section('content')
    <h1 class="mb-4">Apriori Rules</h1>

    <!-- Search Bar -->
    <div class="mb-3">
        <input type="text" id="searchInput" class="form-control" placeholder="Cari produk...">
    </div>

    <table class="table table-bordered mt-3">
        <thead>
            <tr>
                <th>Aturan Asosiasi</th>
                <th>
                    Support
                    <span class="info-icon" data-tooltip="support" onclick="toggleTooltip(event)">ℹ️</span>
                </th>
                <th>
                    Confidence
                    <span class="info-icon" data-tooltip="confidence" onclick="toggleTooltip(event)">ℹ️</span>
                </th>
                <th>
                    Lift
                    <span class="info-icon" data-tooltip="lift" onclick="toggleTooltip(event)">ℹ️</span>
                </th>
            </tr>
        </thead>
        <tbody id="rulesTableBody">
            @foreach($rules as $rule)
                <tr class="rule-row">
                    <td>
                        {{-- Menampilkan aturan asosiasi --}}
                        @if(count($rule['consequent_names']) > 1)
                            {{-- Format untuk 3-itemset --}}
                            <span class="rule-text">jika</span>
                            <span class="rule-main">{{ implode(', ', $rule['antecedent_names']) }}</span>
                            <span class="rule-text">maka</span>
                            <span class="rule-main">{{ implode(' dan ', $rule['consequent_names']) }}</span>
                        @else
                            {{-- Format untuk 2-itemset --}}
                            <span class="rule-text">jika</span>
                            <span class="rule-main">{{ implode(', ', $rule['antecedent_names']) }}</span>
                            <span class="rule-text">maka</span>
                            <span class="rule-main">{{ implode(', ', $rule['consequent_names']) }}</span>
                        @endif
                    </td>
                    <td>{{ round($rule['support'], 2) }}</td>
                    <td>{{ round($rule['confidence'], 2) }}</td>
                    <td>{{ round($rule['lift'], 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div id="tooltip" class="tooltip-box"></div>

    <style>
        /* Styling untuk rule utama */
        .rule-text {
            font-size: 14px;
            color: #6c757d;
        }

        .rule-main {
            font-size: 16px;
            font-weight: bold;
            color: #212529;
        }

        .table th {
            background-color: #f8f9fa;
            text-align: center;
        }

        .table td {
            text-align: left;
            vertical-align: middle;
        }

        .table thead th {
            font-weight: bold;
        }

        .info-icon {
            cursor: pointer;
            margin-left: 5px;
            color: #007bff;
        }

        .tooltip-box {
            display: none;
            position: absolute;
            background-color: #f8f9fa;
            border: 1px solid #ccc;
            padding: 8px;
            border-radius: 5px;
            width: 250px;
            z-index: 1000;
            font-size: 14px;
        }

        .tooltip-box p {
            margin: 0;
        }

    </style>

    <script>
        const tooltips = {
            support: "<strong>Support:</strong> Seberapa sering kombinasi produk muncul di semua transaksi.",
            confidence: "<strong>Confidence:</strong> Kemungkinan benarnya sebuah aturan asosiasi",
            lift: "<strong>Lift:</strong> Seberapa kuat hubungan antara dua barang dibandingkan dengan jika mereka muncul secara acak.",
        };

        function toggleTooltip(event) {
            event.stopPropagation();
            const tooltip = document.getElementById('tooltip');
            const icon = event.target;
            const type = icon.dataset.tooltip;
            const rect = icon.getBoundingClientRect();

            if (tooltip.dataset.visible === type) {
                tooltip.style.display = 'none';
                tooltip.dataset.visible = '';
                return;
            }

            tooltip.innerHTML = tooltips[type];
            tooltip.dataset.visible = type;

            let left;
            if (type === "lift") {
                left = rect.left + window.scrollX - 270;
            } else if (type === "confidence") {
                left = rect.left + window.scrollX - 250; // arahkan tooltip ke kiri
            } else {
                left = rect.left + window.scrollX + 20; // default ke kanan
            }

            tooltip.style.left = `${left}px`;
            tooltip.style.top = `${rect.top + window.scrollY + 20}px`;
            tooltip.style.display = 'block';
        }

        // Fitur pencarian produk
        document.getElementById('searchInput').addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const rows = document.querySelectorAll('.rule-row');

            rows.forEach(row => {
                const ruleText = row.querySelector('td').innerText.toLowerCase();
                if (ruleText.includes(searchTerm)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
    </script>
@endsection
