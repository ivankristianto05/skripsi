@extends('layout')

@section('content')
    <h1>Frequent Itemsets</h1>

    <h2>Itemsets 1 (Produk Individu)</h2>
    <table>
        <thead>
            <tr>
                <th>Nama Produk</th>
                <th>Support</th>
            </tr>
        </thead>
        <tbody>
            @foreach($frequentItemsets['itemsets_1'] ?? [] as $item => $support)
                <tr>
                    <td>{{ $item }}</td>  <!-- Nama produk langsung ditampilkan -->
                    <td>{{ $support }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <h2>Itemsets 2 (Pasangan Produk)</h2>
    <table>
        <thead>
            <tr>
                <th>Nama Produk 1</th>
                <th>Nama Produk 2</th>
                <th>Support</th>
            </tr>
        </thead>
        <tbody>
            @foreach($frequentItemsets['itemsets_2'] ?? [] as $pair => $support)
                @php
                    // Pisahkan pasangan produk (array), lalu tampilkan nama produk untuk setiap pasangan
                    $produk = is_array($pair) ? $pair : explode(',', $pair);
                @endphp
                <tr>
                    <td>{{ $produk[0] }}</td>
                    <td>{{ $produk[1] }}</td>
                    <td>{{ $support }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <h2>Itemsets 3 (Tiga Produk)</h2>
    <table>
        <thead>
            <tr>
                <th>Nama Produk 1</th>
                <th>Nama Produk 2</th>
                <th>Nama Produk 3</th>
                <th>Support</th>
            </tr>
        </thead>
        <tbody>
            @foreach($frequentItemsets['itemsets_3'] ?? [] as $key => $support)
                @php
                    // Pastikan key adalah string yang dipisahkan dengan koma dan pecah menjadi array
                    $produk = explode(',', $key);
                @endphp
                <tr>
                    <td>{{ $produk[0] }}</td>
                    <td>{{ $produk[1] }}</td>
                    <td>{{ $produk[2] }}</td>
                    <td>{{ $support }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endsection
