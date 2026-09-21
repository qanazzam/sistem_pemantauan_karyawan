@extends('layouts.app')

@section('title', $uptd->nama_uptd)
@section('page-title', $uptd->nama_uptd)
@section('page-subtitle', 'Detail Unit Pelaksana Teknis Dinas')

@section('content')
<a href="{{ route('uptd.index') }}" class="btn-back">
    <i class="bi bi-arrow-left"></i> Kembali ke Daftar UPTD
</a>

<!-- Stats -->
<div class="row g-3 mb-4">
    <div class="col-xl col-md-4 col-6">
        <div class="stat-card card-total animate-in">
            <div class="stat-card-icon">
                <i class="bi bi-people-fill"></i>
            </div>
            <div class="stat-card-value">{{ $stats['total'] }}</div>
            <div class="stat-card-label">Total Pegawai</div>
        </div>
    </div>
    <div class="col-xl col-md-4 col-6">
        <div class="stat-card card-pns animate-in">
            <div class="stat-card-icon">
                <i class="bi bi-person-badge-fill"></i>
            </div>
            <div class="stat-card-value">{{ $stats['pns'] }}</div>
            <div class="stat-card-label">PNS</div>
        </div>
    </div>
    <div class="col-xl col-md-4 col-6">
        <div class="stat-card card-pppk animate-in">
            <div class="stat-card-icon">
                <i class="bi bi-person-workspace"></i>
            </div>
            <div class="stat-card-value">{{ $stats['pppk'] }}</div>
            <div class="stat-card-label">PPPK</div>
        </div>
    </div>
    <div class="col-xl col-md-6 col-6">
        <div class="stat-card card-aktif animate-in">
            <div class="stat-card-icon">
                <i class="bi bi-check-circle-fill"></i>
            </div>
            <div class="stat-card-value">{{ $stats['aktif'] }}</div>
            <div class="stat-card-label">Aktif</div>
        </div>
    </div>
    <div class="col-xl col-md-6 col-6">
        <div class="stat-card card-pensiun animate-in">
            <div class="stat-card-icon">
                <i class="bi bi-hourglass-split"></i>
            </div>
            <div class="stat-card-value">{{ $stats['pensiun'] }}</div>
            <div class="stat-card-label">Pensiun</div>
        </div>
    </div>
</div>

<!-- Filter -->
<form action="{{ route('uptd.show', $uptd) }}" method="GET" class="filter-bar mb-4">
    <div class="row g-2 align-items-end">
        <div class="col-lg-4 col-md-6">
            <label class="form-label" style="font-size:12px;font-weight:600;color:var(--text-secondary);">Cari Pegawai</label>
            <div class="position-relative">
                <input type="text" name="search" class="form-control" placeholder="Nama atau NIK..."
                       value="{{ request('search') }}" style="padding-left:36px;">
                <i class="bi bi-search" style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--text-muted);font-size:14px;"></i>
            </div>
        </div>
        <div class="col-lg-2 col-md-3 col-6">
            <label class="form-label" style="font-size:12px;font-weight:600;color:var(--text-secondary);">Status</label>
            <select name="status_kepegawaian" class="form-select">
                <option value="">Semua</option>
                <option value="PNS" {{ request('status_kepegawaian') == 'PNS' ? 'selected' : '' }}>PNS</option>
                <option value="PPPK" {{ request('status_kepegawaian') == 'PPPK' ? 'selected' : '' }}>PPPK</option>
            </select>
        </div>
        <div class="col-lg-2 col-md-3 col-6">
            <label class="form-label" style="font-size:12px;font-weight:600;color:var(--text-secondary);">Kondisi</label>
            <select name="status_aktif" class="form-select">
                <option value="">Semua</option>
                <option value="Aktif" {{ request('status_aktif') == 'Aktif' ? 'selected' : '' }}>Aktif</option>
                <option value="Pensiun" {{ request('status_aktif') == 'Pensiun' ? 'selected' : '' }}>Pensiun</option>
            </select>
        </div>
        <div class="col-lg-4 col-md-4">
            <div class="d-flex gap-2">
                <button type="submit" class="btn-filter">
                    <i class="bi bi-funnel-fill"></i> Filter
                </button>
                <a href="{{ route('uptd.show', $uptd) }}" class="btn-reset">Reset</a>
            </div>
        </div>
    </div>
</form>

<!-- Table -->
<div class="card-custom">
    <div class="card-custom-header">
        <h6>Daftar Pegawai {{ $uptd->nama_uptd }}</h6>
        <span class="count-badge">{{ $pegawai->total() }}</span>
    </div>
    <div class="card-custom-body p-0">
        <div class="table-responsive">
            <table class="table-custom">
                <thead>
                    <tr>
                        <th style="width:50px;">No</th>
                        <th>Nama</th>
                        <th>NIK</th>
                        <th>Status</th>
                        <th>Kondisi</th>
                        <th>Kab/Kota</th>
                        <th>No HP</th>
                        <th style="width:80px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($pegawai as $index => $p)
                    <tr>
                        <td class="text-muted">{{ $pegawai->firstItem() + $index }}</td>
                        <td>
                            <a href="{{ route('pegawai.show', $p) }}" class="fw-semibold text-dark" style="font-size:13.5px;">
                                {{ $p->nama }}
                            </a>
                        </td>
                        <td style="font-family:monospace;font-size:12.5px;color:var(--text-secondary);">{{ $p->nik ?? '-' }}</td>
                        <td><span class="badge-custom {{ $p->kepegawaian_badge_class }}">{{ $p->status_kepegawaian }}</span></td>
                        <td><span class="badge-custom {{ $p->status_badge_class }}">{{ $p->status_aktif }}</span></td>
                        <td style="font-size:13px;">{{ $p->kabupaten_kota ?? '-' }}</td>
                        <td style="font-size:12.5px;color:var(--text-secondary);">{{ $p->no_hp ?? '-' }}</td>
                        <td>
                            <a href="{{ route('pegawai.show', $p) }}" class="btn-action btn-view" title="Detail">
                                <i class="bi bi-eye"></i>
                            </a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8">
                            <div class="empty-state">
                                <i class="bi bi-search"></i>
                                <h5>Data tidak ditemukan</h5>
                                <p>Coba ubah filter pencarian Anda</p>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Pagination -->
@if($pegawai->hasPages())
<nav class="pagination-custom">
    @if($pegawai->onFirstPage())
        <span class="page-item disabled"><span class="page-link"><i class="bi bi-chevron-left"></i></span></span>
    @else
        <span class="page-item"><a class="page-link" href="{{ $pegawai->previousPageUrl() }}"><i class="bi bi-chevron-left"></i></a></span>
    @endif

    @foreach($pegawai->getUrlRange(max(1, $pegawai->currentPage() - 2), min($pegawai->lastPage(), $pegawai->currentPage() + 2)) as $page => $url)
        <span class="page-item {{ $page == $pegawai->currentPage() ? 'active' : '' }}">
            <a class="page-link" href="{{ $url }}">{{ $page }}</a>
        </span>
    @endforeach

    @if($pegawai->hasMorePages())
        <span class="page-item"><a class="page-link" href="{{ $pegawai->nextPageUrl() }}"><i class="bi bi-chevron-right"></i></a></span>
    @else
        <span class="page-item disabled"><span class="page-link"><i class="bi bi-chevron-right"></i></span></span>
    @endif
</nav>
@endif
@endsection
