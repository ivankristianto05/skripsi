<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">

    <style>
    body {
        min-height: 100vh;
        display: flex;
        margin: 0;
    }

    .sidebar {
        width: 250px;
        background: #212529;
        transition: width 0.5s ease;
        overflow-x: hidden;
        box-sizing: border-box;
        position: fixed;
        top: 0;
        left: 0;
        bottom: 0;
        z-index: 999;
        padding-top: 20px;
        display: flex;
        flex-direction: column;
    }

    .sidebar a,
    .sidebar .toggle-btn {
        color: #ffffff;
        text-decoration: none;
        display: flex;
        align-items: center;
        padding: 15px;
        width: 100%;
        text-align: left;
        background: transparent;
        border: none;
        font-size: 16px;
    }

    .sidebar a i,
    .sidebar .toggle-btn i {
        font-size: 18px;
        margin-right: 10px;
        min-width: 24px;
        text-align: center;
    }

    .sidebar.collapsed + .content {
        margin-left: 70px;
    }

    .sidebar a span,
    .sidebar .toggle-btn span {
        transition: opacity 0.5s ease;
        white-space: nowrap;
    }

    .sidebar a:hover,
    .sidebar a.active {
        background: #343a40;
        color: #fff;
    }

    .sidebar.collapsed {
        width: 70px;
    }

    .sidebar.collapsed a span,
    .sidebar.collapsed .toggle-btn span {
        opacity: 0;
        width: 0;
        overflow: hidden;
        display: inline-block;
    }

    .content {
        flex: 1;
        padding: 20px;
        transition: margin-left 0.5s ease;
        margin-left: 250px;
    }

    .header {
        background-color: #212529;
        color: #ffffff;
        border-bottom: 1px solid #343a40;
        height: 60px;
        position: sticky;
        top: 0;
        z-index: 1000;
        display: flex;
        justify-content: center;
        align-items: center;
        padding: 0 20px;
        margin-left: 250px;
        transition: margin-left 0.5s ease;
    }

    .header h5 {
        margin: 0;
    }

    .sidebar .logo-container {
        display: flex;
        flex-direction: column;
        align-items: center;
        margin-bottom: 20px;
        transition: all 0.5s ease;
    }

    .sidebar .logo-container img {
        width: 40px;
        height: auto;
        transition: width 0.5s ease;
    }

    .sidebar .logo-container img.logo-expanded {
        width: 180px;
    }

    .sidebar .logo-container img.logo-collapsed {
        width: 50px;
    }

    .sidebar .logo-container span {
        font-size: 18px;
        font-weight: bold;
        color: white;
        white-space: nowrap;
        transition: opacity 0.5s ease;
        margin-top: 10px;
    }

    .sidebar.collapsed .logo-container span {
        opacity: 0;
    }

    .sidebar .menu-list {
        display: flex;
        flex-direction: column;
        align-items: flex-start;
        margin-top: 20px;
        flex-grow: 1;
    }

    .sidebar .menu-list a {
        width: 100%;
    }

    /* Styling untuk bagian logout */
    .sidebar .logout-section {
        margin-top: auto;
        padding: 15px 0;
        border-top: 1px solid #343a40;
    }

    .sidebar .logout-btn {
        color: #dc3545;
        text-decoration: none;
        display: flex;
        align-items: center;
        padding: 15px;
        width: 100%;
        text-align: left;
        background: transparent;
        border: none;
        font-size: 16px;
        transition: all 0.3s ease;
    }

    .sidebar .logout-btn:hover {
        background: #dc3545;
        color: #fff;
    }

    .sidebar .logout-btn i {
        font-size: 18px;
        margin-right: 10px;
        min-width: 24px;
        text-align: center;
    }

    .sidebar .logout-btn span {
        transition: opacity 0.5s ease;
        white-space: nowrap;
    }

    .sidebar.collapsed .logout-btn span {
        opacity: 0;
        width: 0;
        overflow: hidden;
        display: inline-block;
    }

    .header .toggle-btn {
        background: transparent;
        border: none;
        color: white;
        font-size: 24px;
        margin-left: 0;
        position: absolute;
        left: 20px;
    }

    /* User info section */
    .sidebar .user-info {
        padding: 10px 15px;
        margin-bottom: 10px;
    }

    .sidebar .user-info .user-name {
        color: #ffffff;
        font-size: 14px;
        font-weight: 500;
        white-space: nowrap;
        transition: opacity 0.5s ease;
    }

    .sidebar .user-info .user-role {
        color: #6c757d;
        font-size: 12px;
        white-space: nowrap;
        transition: opacity 0.5s ease;
    }

    .sidebar.collapsed .user-info .user-name,
    .sidebar.collapsed .user-info .user-role {
        opacity: 0;
        width: 0;
        overflow: hidden;
        display: inline-block;
    }
    </style>
</head>
<body>
    <div class="sidebar" id="sidebar">
        <div class="logo-container">
            <img src="{{ asset('images/logonobg.png') }}" alt="Logo" class="logo">
            <!-- <span>Flaming Tobacco</span> -->
        </div>
        
        <div class="menu-list">
    <a href="{{ route('dashboard') }}" title="Dashboard" class="{{ request()->routeIs('dashboard') ? 'active' : '' }}">
        <i class="bi bi-house-door"></i> <span>Dashboard</span>
    </a>
    <a href="{{ route('produk.index') }}" title="Produk" class="{{ request()->routeIs('produk.*') ? 'active' : '' }}">
        <i class="bi bi-box"></i> <span>Produk</span>
    </a>

    @if(auth()->user()->role === 'admin')
        <a href="{{ route('transaksis.index') }}" title="Transaksi" class="{{ request()->routeIs('transaksis.*') ? 'active' : '' }}">
            <i class="bi bi-cart"></i> <span>Transaksi</span>
        </a>
        <a href="{{ route('apriori.index') }}" title="Pengujian" class="{{ request()->routeIs('apriori.index') ? 'active' : '' }}">
            <i class="bi bi-diagram-3"></i> <span>Pengujian</span>
        </a>
    @endif

    <a href="{{ route('apriori.hasil.global') }}" title="Hasil Global Apriori" class="{{ request()->routeIs('apriori.hasil.global') ? 'active' : '' }}">
        <i class="bi bi-globe"></i> <span>Rekomendasi</span>
    </a>
</div>


        <!-- User Info Section (Optional) -->
        <div class="user-info">
            <div class="user-name">{{ auth()->user()->name ?? 'User' }}</div>
            <div class="user-role">{{ auth()->user()->role ?? 'Administrator' }}</div>
        </div>

        <!-- Logout Section -->
        <div class="logout-section">
            <form method="POST" action="{{ route('logout') }}" style="width: 100%;">
                @csrf
                <button type="submit" class="logout-btn" title="Logout" onclick="return confirm('Apakah Anda yakin ingin logout?')">
                    <i class="bi bi-box-arrow-right"></i>
                    <span>Logout</span>
                </button>
            </form>
        </div>
    </div>

    <div class="flex-grow-1 d-flex flex-column w-100">
        <header class="header">
            <button class="toggle-btn" onclick="toggleSidebar()">
                <i class="bi bi-list"></i>
            </button>
            <h5 class="text-white m-0">Sistem Rekomendasi Paket Tembakau, Filter, Kertas</h5>
        </header>

        <div class="content" id="main-content">
            @yield('content')
        </div>
    </div>

    <script>
        // Check sidebar state from localStorage on page load
        window.onload = function() {
            const sidebar = document.getElementById('sidebar');
            const logo = document.querySelector('.logo-container img');
            const isCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';

            if (isCollapsed) {
                sidebar.classList.add('collapsed');
                logo.classList.add('logo-collapsed');
            } else {
                logo.classList.add('logo-expanded');
            }
        };

        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const content = document.querySelector('.content');
            const header = document.querySelector('.header');
            const logo = document.querySelector('.logo-container img');

            sidebar.classList.toggle('collapsed');
            const sidebarWidth = sidebar.classList.contains('collapsed') ? '70px' : '250px';
            content.style.marginLeft = sidebarWidth;
            header.style.marginLeft = sidebarWidth;

            // Toggle logo class based on sidebar state
            if (sidebar.classList.contains('collapsed')) {
                logo.classList.remove('logo-expanded');
                logo.classList.add('logo-collapsed');
                localStorage.setItem('sidebarCollapsed', 'true');
            } else {
                logo.classList.remove('logo-collapsed');
                logo.classList.add('logo-expanded');
                localStorage.setItem('sidebarCollapsed', 'false');
            }
        }
    </script>
</body>
</html>