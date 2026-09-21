@extends('layouts.app')

@section('title', 'Dashboard')
@section('page-title', 'Dashboard')
@section('page-subtitle', 'Ringkasan data pegawai Dinas PU Prov. Sulawesi Selatan')

@section('content')
<!-- Stat Cards -->
<div class="row g-3 mb-4">
    <div class="col-xl col-md-4 col-6">
        <div class="stat-card card-total animate-in">
            <div class="stat-card-icon">
                <i class="bi bi-people-fill"></i>
            </div>
            <div class="stat-card-value">{{ number_format($totalPegawai) }}</div>
            <div class="stat-card-label">Total Pegawai</div>
        </div>
    </div>
    <div class="col-xl col-md-4 col-6">
        <div class="stat-card card-pns animate-in">
            <div class="stat-card-icon">
                <i class="bi bi-person-badge-fill"></i>
            </div>
            <div class="stat-card-value">{{ number_format($totalPns) }}</div>
            <div class="stat-card-label">PNS</div>
        </div>
    </div>
    <div class="col-xl col-md-4 col-6">
        <div class="stat-card card-pppk animate-in">
            <div class="stat-card-icon">
                <i class="bi bi-person-workspace"></i>
            </div>
            <div class="stat-card-value">{{ number_format($totalPppk) }}</div>
            <div class="stat-card-label">PPPK</div>
        </div>
    </div>
    <div class="col-xl col-md-6 col-6">
        <div class="stat-card card-aktif animate-in">
            <div class="stat-card-icon">
                <i class="bi bi-check-circle-fill"></i>
            </div>
            <div class="stat-card-value">{{ number_format($totalAktif) }}</div>
            <div class="stat-card-label">Aktif</div>
        </div>
    </div>
    <div class="col-xl col-md-6 col-6">
        <div class="stat-card card-pensiun animate-in">
            <div class="stat-card-icon">
                <i class="bi bi-hourglass-split"></i>
            </div>
            <div class="stat-card-value">{{ number_format($totalPensiun) }}</div>
            <div class="stat-card-label">Pensiun</div>
        </div>
    </div>
</div>

<!-- Charts Row -->
<div class="row g-3 mb-4">
    <!-- PNS vs PPPK Pie Chart -->
    <div class="col-lg-4">
        <div class="card-custom animate-in">
            <div class="card-custom-header">
                <h6><i class="bi bi-pie-chart-fill me-2 text-primary"></i>Komposisi Pegawai</h6>
            </div>
            <div class="card-custom-body">
                <div class="chart-container" style="height: 240px;">
                    <canvas id="chartKomposisi"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- UPTD Bar Chart -->
    <div class="col-lg-4">
        <div class="card-custom animate-in">
            <div class="card-custom-header">
                <h6><i class="bi bi-bar-chart-fill me-2 text-primary"></i>Pegawai per UPTD</h6>
            </div>
            <div class="card-custom-body">
                <div class="chart-container" style="height: 240px;">
                    <canvas id="chartUptd"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Status Doughnut Chart -->
    <div class="col-lg-4">
        <div class="card-custom animate-in">
            <div class="card-custom-header">
                <h6><i class="bi bi-activity me-2 text-primary"></i>Status Kepegawaian</h6>
            </div>
            <div class="card-custom-body">
                <div class="chart-container" style="height: 240px;">
                    <canvas id="chartStatus"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Bottom Row -->
<div class="row g-3">
    <!-- Top Kabupaten -->
    <div class="col-lg-5">
        <div class="card-custom animate-in">
            <div class="card-custom-header">
                <h6><i class="bi bi-geo-alt-fill me-2 text-primary"></i>Distribusi Kab/Kota</h6>
            </div>
            <div class="card-custom-body p-0">
                <table class="table-custom">
                    <thead>
                        <tr>
                            <th>Kabupaten/Kota</th>
                            <th class="text-end">Jumlah</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($kabupatenData as $kab)
                        <tr>
                            <td>
                                <a href="{{ route('pegawai.index', ['kabupaten' => $kab->kabupaten_kota]) }}" class="text-primary fw-medium">
                                    {{ $kab->kabupaten_kota }}
                                </a>
                            </td>
                            <td class="text-end">
                                <span class="count-badge">{{ $kab->total }}</span>
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="2" class="text-center text-muted py-4">Tidak ada data</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- UPTD Summary -->
    <div class="col-lg-7">
        <div class="card-custom animate-in">
            <div class="card-custom-header">
                <h6><i class="bi bi-diagram-3-fill me-2 text-primary"></i>Ringkasan UPTD</h6>
                <a href="{{ route('uptd.index') }}" class="btn-action btn-view">
                    Lihat Semua <i class="bi bi-arrow-right"></i>
                </a>
            </div>
            <div class="card-custom-body p-0">
                <table class="table-custom">
                    <thead>
                        <tr>
                            <th>Unit Kerja</th>
                            <th class="text-center">PNS</th>
                            <th class="text-center">PPPK</th>
                            <th class="text-center">Aktif</th>
                            <th class="text-center">Pensiun</th>
                            <th class="text-center">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($uptdData as $uptd)
                        <tr>
                            <td>
                                <a href="{{ route('uptd.show', $uptd) }}" class="text-primary fw-medium">
                                    {{ $uptd->nama_uptd }}
                                </a>
                            </td>
                            <td class="text-center"><span class="badge-custom badge-pns">{{ $uptd->pns_count }}</span></td>
                            <td class="text-center"><span class="badge-custom badge-pppk">{{ $uptd->pppk_count }}</span></td>
                            <td class="text-center"><span class="badge-custom badge-aktif">{{ $uptd->aktif_count }}</span></td>
                            <td class="text-center"><span class="badge-custom badge-pensiun">{{ $uptd->pensiun_count }}</span></td>
                            <td class="text-center"><span class="count-badge">{{ $uptd->pegawai_count }}</span></td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Chart defaults
    Chart.defaults.font.family = "'Inter', sans-serif";
    Chart.defaults.font.size = 12;
    Chart.defaults.plugins.legend.labels.usePointStyle = true;
    Chart.defaults.plugins.legend.labels.pointStyle = 'circle';
    Chart.defaults.plugins.legend.labels.padding = 16;

    // PNS vs PPPK Doughnut
    new Chart(document.getElementById('chartKomposisi'), {
        type: 'doughnut',
        data: {
            labels: ['PNS', 'PPPK'],
            datasets: [{
                data: [{{ $totalPns }}, {{ $totalPppk }}],
                backgroundColor: ['#704F38', '#AA8C70'],
                borderWidth: 0,
                borderRadius: 4,
                spacing: 3,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '65%',
            plugins: {
                legend: { position: 'bottom' }
            }
        }
    });

    // UPTD Bar Chart
    new Chart(document.getElementById('chartUptd'), {
        type: 'bar',
        data: {
            labels: {!! json_encode($uptdData->pluck('nama_uptd')->map(fn($n) => str_replace(['UPTD ', 'WS. ', 'W. '], '', $n))) !!},
            datasets: [
                {
                    label: 'PNS',
                    data: {!! json_encode($uptdData->pluck('pns_count')) !!},
                    backgroundColor: '#704F38',
                    borderRadius: 4,
                    barPercentage: 0.7,
                },
                {
                    label: 'PPPK',
                    data: {!! json_encode($uptdData->pluck('pppk_count')) !!},
                    backgroundColor: '#AA8C70',
                    borderRadius: 4,
                    barPercentage: 0.7,
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom' }
            },
            scales: {
                x: {
                    grid: { display: false },
                    ticks: { font: { size: 10 } }
                },
                y: {
                    grid: { color: '#EFE9E1' },
                    beginAtZero: true,
                    ticks: { stepSize: 20 }
                }
            }
        }
    });

    // Status Doughnut
    new Chart(document.getElementById('chartStatus'), {
        type: 'doughnut',
        data: {
            labels: ['Aktif', 'Pensiun'],
            datasets: [{
                data: [{{ $totalAktif }}, {{ $totalPensiun }}],
                backgroundColor: ['#553A26', '#C7AB8E'],
                borderWidth: 0,
                borderRadius: 4,
                spacing: 3,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '65%',
            plugins: {
                legend: { position: 'bottom' }
            }
        }
    });
});
</script>
@endpush
