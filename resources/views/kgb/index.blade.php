@extends('layouts.app')

@section('title', 'Kenaikan Gaji Berkala (KGB)')
@section('page-title', 'Kenaikan Gaji Berkala (KGB)')
@section('page-subtitle', 'Jadwal dan pemantauan berkala 2 tahunan bagi pegawai PNS & PPPK')

@section('content')
<!-- Stat Cards KGB -->
<div class="row g-3 mb-4">
    <div class="col-xl-3 col-md-6 col-6">
        <div class="stat-card card-total animate-in">
            <div class="stat-card-icon">
                <i class="bi bi-people-fill"></i>
            </div>
            <div class="stat-card-value">{{ number_format($totalWajibKgb) }}</div>
            <div class="stat-card-label">Pegawai Wajib KGB</div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6 col-6">
        <a href="{{ route('kgb.index', ['status_kgb' => 'Segera KGB']) }}" style="display:block;color:inherit;">
            <div class="stat-card card-kgb-warning animate-in" style="animation-delay: 0.08s;">
                <div class="stat-card-icon">
                    <i class="bi bi-bell-fill"></i>
                </div>
                <div class="stat-card-value">{{ number_format($totalSegera) }}</div>
                <div class="stat-card-label">Segera KGB (1–3 Bulan)</div>
            </div>
        </a>
    </div>
    <div class="col-xl-3 col-md-6 col-6">
        <a href="{{ route('kgb.index', ['status_kgb' => 'Jatuh Tempo']) }}" style="display:block;color:inherit;">
            <div class="stat-card card-kgb-overdue animate-in" style="animation-delay: 0.12s;">
                <div class="stat-card-icon">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                </div>
                <div class="stat-card-value">{{ number_format($totalJatuhTempo) }}</div>
                <div class="stat-card-label">Jatuh Tempo (Perlu SK)</div>
            </div>
        </a>
    </div>
    <div class="col-xl-3 col-md-6 col-6">
        <a href="{{ route('kgb.index', ['status_kgb' => 'Akan Datang']) }}" style="display:block;color:inherit;">
            <div class="stat-card card-kgb-safe animate-in" style="animation-delay: 0.16s;">
                <div class="stat-card-icon">
                    <i class="bi bi-calendar-check-fill"></i>
                </div>
                <div class="stat-card-value">{{ number_format($totalAkanDatang) }}</div>
                <div class="stat-card-label">Akan Datang (> 3 Bulan)</div>
            </div>
        </a>
    </div>
</div>

<!-- Filter Bar -->
<form action="{{ route('kgb.index') }}" method="GET" class="filter-bar mb-4">
    <div class="row g-2 align-items-end">
        <div class="col-lg-3 col-md-6">
            <label class="form-label-custom">Cari Pegawai</label>
            <div class="position-relative">
                <input type="text" name="search" class="form-control" placeholder="Nama, NIP, atau NIK..."
                       value="{{ request('search') }}" style="padding-left:36px;">
                <i class="bi bi-search" style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--text-muted);font-size:14px;"></i>
            </div>
        </div>
        <div class="col-lg-2 col-md-3 col-6">
            <label class="form-label-custom">Status KGB</label>
            <select name="status_kgb" class="form-select">
                <option value="">Semua Status KGB</option>
                <option value="Segera KGB" {{ request('status_kgb') == 'Segera KGB' ? 'selected' : '' }}>Segera KGB (1–3 Bulan)</option>
                <option value="Jatuh Tempo" {{ request('status_kgb') == 'Jatuh Tempo' ? 'selected' : '' }}>Jatuh Tempo (Perlu SK)</option>
                <option value="Akan Datang" {{ request('status_kgb') == 'Akan Datang' ? 'selected' : '' }}>Akan Datang</option>
                <option value="Pensiun" {{ request('status_kgb') == 'Pensiun' ? 'selected' : '' }}>Pensiun (Tidak Berlaku)</option>
            </select>
        </div>
        <div class="col-lg-2 col-md-3 col-6">
            <label class="form-label-custom">Jenis Pegawai</label>
            <select name="status_kepegawaian" class="form-select">
                <option value="">PNS & PPPK</option>
                <option value="PNS" {{ request('status_kepegawaian') == 'PNS' ? 'selected' : '' }}>PNS Saja</option>
                <option value="PPPK" {{ request('status_kepegawaian') == 'PPPK' ? 'selected' : '' }}>PPPK Saja</option>
            </select>
        </div>
        <div class="col-lg-2 col-md-3 col-6">
            <label class="form-label-custom">Unit Kerja</label>
            <select name="uptd_id" class="form-select">
                <option value="">Semua UPTD</option>
                @foreach($uptdList as $u)
                    <option value="{{ $u->id }}" {{ request('uptd_id') == $u->id ? 'selected' : '' }}>
                        {{ $u->nama_uptd }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="col-lg-1 col-md-3 col-6">
            <label class="form-label-custom">Golongan</label>
            <select name="golongan" class="form-select">
                <option value="">Semua</option>
                @foreach($golonganList as $gol)
                    <option value="{{ $gol }}" {{ request('golongan') == $gol ? 'selected' : '' }}>
                        {{ $gol }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="col-lg-2 col-md-6 col-12">
            <div class="d-flex gap-2">
                <button type="submit" class="btn-filter w-100 justify-content-center">
                    <i class="bi bi-funnel-fill"></i> Filter
                </button>
                <a href="{{ route('kgb.index') }}" class="btn-reset">Reset</a>
            </div>
        </div>
    </div>
</form>

<!-- Results Info -->
<div class="d-flex align-items-center justify-content-between mb-3">
    <p style="font-size:13.5px;color:var(--text-secondary);margin:0;">
        Menampilkan <strong>{{ $pegawai->firstItem() ?? 0 }}–{{ $pegawai->lastItem() ?? 0 }}</strong> dari
        <strong>{{ number_format($pegawai->total()) }}</strong> pegawai
    </p>
    <div class="d-flex align-items-center gap-2">
        <span style="font-size:12px;color:var(--text-muted);">Siklus KGB: <strong>2 Tahun Sekali</strong> (PP No. 5/2024 & PermenPAN-RB 7/2023)</span>
    </div>
</div>

<!-- Table -->
<div class="card-custom">
    <div class="card-custom-body p-0">
        <div class="table-responsive">
            <table class="table-custom">
                <thead>
                    <tr>
                        <th style="width:50px;">No</th>
                        <th>Pegawai</th>
                        <th>Status / Gol</th>
                        <th>MKG</th>
                        <th>Unit Kerja</th>
                        <th>TMT Terakhir</th>
                        <th>Jatuh Tempo KGB</th>
                        <th>Status & Sisa Waktu</th>
                        <th>Estimasi Gaji Pokok</th>
                        <th style="width:70px;" class="text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($pegawai as $index => $p)
                    <tr>
                        <td class="text-muted">{{ $pegawai->firstItem() + $index }}</td>
                        <td>
                            <a href="{{ route('pegawai.show', $p) }}" class="fw-semibold text-dark d-block" style="font-size:13.5px;">
                                {{ $p->nama }}
                            </a>
                            <small style="font-family:monospace;font-size:11.5px;color:var(--text-muted);">
                                NIP: {{ $p->nik ?? '-' }}
                            </small>
                        </td>
                        <td>
                            <span class="badge-custom {{ $p->kepegawaian_badge_class }} me-1">{{ $p->status_kepegawaian }}</span>
                            <span class="badge-custom badge-pns">{{ $p->golongan ?? '-' }}</span>
                        </td>
                        <td style="font-size:13px;color:var(--text-secondary);">
                            {{ $p->mkg_tahun ?? 0 }} Thn {{ $p->mkg_bulan ?? 0 }} Bln
                        </td>
                        <td style="font-size:12.5px;color:var(--text-secondary);max-width:150px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                            {{ $p->uptd->nama_uptd ?? '-' }}
                        </td>
                        <td style="font-size:12.5px;color:var(--text-secondary);">
                            {{ $p->tmt_kgb_terakhir ? $p->tmt_kgb_terakhir->format('d/m/Y') : '-' }}
                        </td>
                        <td>
                            @if($p->tmt_kgb_berikutnya)
                                <strong style="font-size:13px;color:var(--text-primary);">
                                    {{ $p->tmt_kgb_berikutnya->format('d/m/Y') }}
                                </strong>
                            @else
                                <span class="text-muted">-</span>
                            @endif
                        </td>
                        <td>
                            <span class="badge-custom {{ $p->kgb_badge_class }}">
                                @if($p->status_kgb === 'Jatuh Tempo')
                                    <i class="bi bi-exclamation-circle-fill"></i>
                                @elseif($p->status_kgb === 'Segera KGB')
                                    <i class="bi bi-bell-fill"></i>
                                @elseif($p->status_kgb === 'Akan Datang')
                                    <i class="bi bi-check-circle-fill"></i>
                                @endif
                                {{ $p->status_kgb }}
                            </span>
                            <div style="font-size:11.5px;color:var(--text-secondary);margin-top:2px;">
                                {{ $p->sisa_waktu_kgb }}
                            </div>
                        </td>
                        <td>
                            @if($p->estimasi_gaji_baru)
                                <div style="font-size:13px;font-weight:700;color:var(--primary);">
                                    {{ $p->estimasi_gaji_baru_formatted }}
                                </div>
                                <small style="font-size:11px;color:var(--text-muted);text-decoration:line-through;">
                                    {{ $p->gaji_pokok_formatted }}
                                </small>
                            @else
                                <span class="text-muted">-</span>
                            @endif
                        </td>
                        <td class="text-center">
                            <a href="{{ route('pegawai.show', $p) }}" class="btn-action btn-view" title="Detail Pegawai">
                                <i class="bi bi-eye"></i>
                            </a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="10">
                            <div class="empty-state">
                                <i class="bi bi-search"></i>
                                <h5>Data KGB tidak ditemukan</h5>
                                <p>Coba sesuaikan kata kunci atau filter pencarian Anda</p>
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
