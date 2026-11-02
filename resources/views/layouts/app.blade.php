<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Sistem Pemantauan Pegawai Dinas Pekerjaan Umum Provinsi Sulawesi Selatan">
    <title>@yield('title', 'Dashboard') — SIMPEG PU Sulsel</title>

    <!-- Favicon -->
    <link rel="icon" type="image/png" href="{{ asset('images/logo.png') }}">
    <!-- Bootstrap 5 CSS (Local) -->
    <link href="{{ asset('vendor/bootstrap/css/bootstrap.min.css') }}" rel="stylesheet">
    <!-- Bootstrap Icons (Local) -->
    <link href="{{ asset('vendor/bootstrap-icons/bootstrap-icons.min.css') }}" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="{{ asset('css/app.css') }}" rel="stylesheet">
</head>
<body>
    <!-- Top Progress Bar (Instant Navigation Feedback) -->
    <div id="pageProgressBar" class="page-progress-bar"></div>
    <!-- Sidebar Overlay (mobile) -->
    <div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-brand">
            <div class="sidebar-brand-wrapper">
                <div class="sidebar-brand-logo">
                    <img src="{{ asset('images/logo.png') }}" alt="Logo Pemprov Sulsel" class="sidebar-logo">
                </div>
                <div class="sidebar-brand-text">
                    <h5>SIMPEG PU</h5>
                    <small>Dinas PU Prov. Sulsel</small>
                </div>
            </div>
        </div>

        <nav class="sidebar-nav">
            <div class="sidebar-nav-label">Menu Utama</div>

            <div class="nav-item">
                <a href="{{ route('dashboard') }}" class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                    <i class="bi bi-grid-1x2-fill"></i>
                    <span>Dashboard</span>
                </a>
            </div>

            <div class="nav-item">
                <a href="{{ route('pegawai.index') }}" class="nav-link {{ request()->routeIs('pegawai.index') || request()->routeIs('pegawai.show') ? 'active' : '' }}">
                    <i class="bi bi-people-fill"></i>
                    <span>Data Pegawai</span>
                </a>
            </div>

            <div class="nav-item">
                <a href="{{ route('pegawai.create') }}" class="nav-link {{ request()->routeIs('pegawai.create') ? 'active' : '' }}">
                    <i class="bi bi-person-plus-fill"></i>
                    <span>Input Pegawai</span>
                </a>
            </div>

            <div class="nav-item">
                <a href="{{ route('pegawai.import') }}" class="nav-link {{ request()->routeIs('pegawai.import*') ? 'active' : '' }}">
                    <i class="bi bi-file-earmark-arrow-up-fill"></i>
                    <span>Import Excel</span>
                </a>
            </div>

            <div class="nav-item">
                <a href="{{ route('uptd.index') }}" class="nav-link {{ request()->routeIs('uptd.*') ? 'active' : '' }}">
                    <i class="bi bi-diagram-3-fill"></i>
                    <span>Unit Kerja (UPTD)</span>
                </a>
            </div>

            <div class="nav-item">
                <a href="{{ route('kgb.index') }}" class="nav-link {{ request()->routeIs('kgb.*') ? 'active' : '' }}">
                    <i class="bi bi-cash-stack"></i>
                    <span>Kenaikan Gaji (KGB)</span>
                </a>
            </div>

            <div class="sidebar-nav-label">Filter Cepat</div>

            <div class="nav-item">
                <a href="{{ route('pegawai.index', ['status_kepegawaian' => 'PNS']) }}" class="nav-link">
                    <i class="bi bi-person-badge-fill"></i>
                    <span>Pegawai PNS</span>
                </a>
            </div>

            <div class="nav-item">
                <a href="{{ route('pegawai.index', ['status_kepegawaian' => 'PPPK']) }}" class="nav-link">
                    <i class="bi bi-person-workspace"></i>
                    <span>Pegawai PPPK</span>
                </a>
            </div>

            <div class="nav-item">
                <a href="{{ route('pegawai.index', ['status_aktif' => 'Pensiun']) }}" class="nav-link">
                    <i class="bi bi-hourglass-split"></i>
                    <span>Pegawai Pensiun</span>
                </a>
            </div>
        </nav>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
        <!-- Top Bar -->
        <header class="topbar">
            <div class="d-flex align-items-center gap-3">
                <button class="sidebar-toggle" onclick="toggleSidebar()">
                    <i class="bi bi-list"></i>
                </button>
                <div class="topbar-title">
                    <h4>@yield('page-title', 'Dashboard')</h4>
                    <p>@yield('page-subtitle', 'Sistem Informasi Manajemen Pegawai')</p>
                </div>
            </div>
            <div class="topbar-right">
                <div class="topbar-date">
                    <i class="bi bi-calendar3"></i>
                    <span>{{ now()->translatedFormat('l, d F Y') }}</span>
                </div>
            </div>
        </header>

        <!-- Content -->
        <div class="content-area">
            @yield('content')
        </div>

        <!-- Footer -->
        <footer class="footer">
            <div class="d-flex align-items-center justify-content-center gap-2">
                <img src="{{ asset('images/logo.png') }}" alt="Logo Sulsel" style="height: 20px; width: auto; object-fit: contain;">
                <span>&copy; {{ date('Y') }} Dinas Pekerjaan Umum Provinsi Sulawesi Selatan — Sistem Pemantauan Pegawai</span>
            </div>
        </footer>
    </main>

    <!-- Bootstrap JS (Local) -->
    <script src="{{ asset('vendor/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
    <!-- Chart.js (Local) -->
    <script src="{{ asset('vendor/chartjs/chart.umd.min.js') }}"></script>

    <script>
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('show');
            document.getElementById('sidebarOverlay').classList.toggle('show');
        }

        // Instant visual feedback on link click & double-click cancellation protection
        document.addEventListener('click', function(e) {
            const link = e.target.closest('a');
            if (!link) return;
            const href = link.getAttribute('href');
            if (href && !href.startsWith('#') && !href.startsWith('javascript:') && !link.hasAttribute('download') && link.target !== '_blank') {
                const bar = document.getElementById('pageProgressBar');
                if (bar) bar.classList.add('loading');
                link.style.opacity = '0.65';
                link.style.pointerEvents = 'none'; // Prevent double clicking which cancels the request
            }
        });
    </script>

    @stack('scripts')
</body>
</html>
