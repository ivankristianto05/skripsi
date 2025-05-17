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
        transition: width 0.5s ease; /* Transition for sidebar width */
        overflow-x: hidden;
        box-sizing: border-box;
        position: fixed;
        top: 0;
        left: 0;
        bottom: 0;
        z-index: 999;
        padding-top: 20px;
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
        margin-left: 70px; /* Margin when sidebar is collapsed */
    }

    .sidebar a span,
    .sidebar .toggle-btn span {
        transition: opacity 0.5s ease; /* Transition for text opacity */
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
        transition: margin-left 0.5s ease; /* Transition for content margin */
        margin-left: 250px; /* Make room for the sidebar */
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
        justify-content: center; /* Center the text */
        align-items: center;
        padding: 0 20px;
        margin-left: 250px; /* Align with the content */
        transition: margin-left 0.5s ease; /* Transition for header margin */
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
        transition: width 0.5s ease; /* Transition for logo width */
    }

    .sidebar .logo-container img.logo-expanded {
        width: 180px; /* Larger logo when sidebar is open */
    }

    .sidebar .logo-container img.logo-collapsed {
        width: 50px; /* Smaller logo when sidebar is collapsed */
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
    }

    .sidebar .menu-list a {
        width: 100%;
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
    </style>
</head>
<body>
    <div class="sidebar" id="sidebar">
        <div class="logo-container">
            <img src="{{ asset('images/logonobg.png') }}" alt="Logo" class="logo"> <!-- Logo with default size -->
            <!-- <span>Flaming Tobacco</span> -->
        </div>
        <div class="menu-list">
            <a href="{{ route('dashboard') }}" title="Dashboard" class="{{ request()->routeIs('dashboard') ? 'active' : '' }}">
                <i class="bi bi-house-door"></i> <span>Dashboard</span>
            </a>
            <a href="{{ route('produk.index') }}" title="Produk" class="{{ request()->routeIs('produk.*') ? 'active' : '' }}">
                <i class="bi bi-box"></i> <span>Produk</span>
            </a>
            <a href="{{ route('transaksis.index') }}" title="Transaksi" class="{{ request()->routeIs('transaksis.*') ? 'active' : '' }}">
                <i class="bi bi-cart"></i> <span>Transaksi</span>
            </a>
            <a href="{{ route('apriori.rules') }}" title="Rekomendasi" class="{{ request()->routeIs('apriori.rules') ? 'active' : '' }}">
                <i class="bi bi-diagram-3"></i> <span>Rekomendasi</span>
            </a>
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
                localStorage.setItem('sidebarCollapsed', 'true'); // Save the state
            } else {
                logo.classList.remove('logo-collapsed');
                logo.classList.add('logo-expanded');
                localStorage.setItem('sidebarCollapsed', 'false'); // Save the state
            }
        }
    </script>
</body>
</html>
