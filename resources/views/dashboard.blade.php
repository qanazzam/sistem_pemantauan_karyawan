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

@if($totalKgbSegera > 0 || $totalKgbJatuhTempo > 0)
<!-- KGB Notice Banner -->
<div class="card-custom mb-4 animate-in" style="background:#FFFFFF;border-left:4px solid var(--primary);">
    <div class="p-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div class="d-flex align-items-center gap-3">
            <div style="width:40px;height:40px;border-radius:8px;background:var(--brown-subtle);display:flex;align-items:center;justify-content:center;color:var(--primary);font-size:20px;flex-shrink:0;">
                <i class="bi bi-bell-fill"></i>
            </div>
            <div>
                <strong style="font-size:14px;color:var(--text-primary);display:block;">Pemberitahuan Kenaikan Gaji Berkala (KGB)</strong>
                <span style="font-size:13px;color:var(--text-secondary);">
                    Terdapat <strong>{{ $totalKgbSegera }} pegawai</strong> mendekati jadwal KGB (1–3 bulan) dan <strong>{{ $totalKgbJatuhTempo }} pegawai</strong> telah jatuh tempo perlu penerbitan SK.
                </span>
            </div>
        </div>
        <a href="{{ route('kgb.index') }}" class="btn-filter" style="padding:7px 16px;font-size:13px;">
            Pantau Jadwal KGB <i class="bi bi-arrow-right"></i>
        </a>
    </div>
</div>
@endif

<!-- Main Data Row -->
<div class="row g-3">
    <!-- UPTD Summary -->
    <div class="col-lg-7">
        <div class="card-custom animate-in">
            <div class="card-custom-header">
                <h6><i class="bi bi-diagram-3-fill me-2 text-primary"></i>Ringkasan Unit Kerja (UPTD)</h6>
                <a href="{{ route('uptd.index') }}" class="btn-action btn-view">
                    Lihat Semua <i class="bi bi-arrow-right"></i>
                </a>
            </div>
            <div class="card-custom-body p-0">
                <div class="table-responsive">
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

    <!-- Top Kabupaten -->
    <div class="col-lg-5">
        <div class="card-custom animate-in">
            <div class="card-custom-header">
                <h6><i class="bi bi-geo-alt-fill me-2 text-primary"></i>Distribusi Kab/Kota (Top 10)</h6>
            </div>
            <div class="card-custom-body p-0">
                <div class="table-responsive">
                    <table class="table-custom">
                        <thead>
                            <tr>
                                <th>Kabupaten/Kota</th>
                                <th class="text-end">Jumlah Pegawai</th>
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
    </div>
</div>
@endsection
