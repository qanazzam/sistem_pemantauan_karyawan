@extends('layouts.app')

@section('title', 'Data Pegawai')
@section('page-title', 'Data Pegawai')
@section('page-subtitle', 'Daftar seluruh pegawai PNS dan PPPK')

@section('content')
@if(session('success'))
<div class="alert-custom-success">
    <i class="bi bi-check-circle-fill fs-5"></i>
    <div>{!! session('success') !!}</div>
</div>
@endif

@if(session('error'))
<div class="alert-custom-danger">
    <i class="bi bi-exclamation-triangle-fill fs-5"></i>
    <div>{!! session('error') !!}</div>
</div>
@endif

@if(session('info'))
<div class="alert-custom-info">
    <i class="bi bi-info-circle-fill fs-5"></i>
    <div>{!! session('info') !!}</div>
</div>
@endif

<!-- Filter Bar -->
<form action="{{ route('pegawai.index') }}" method="GET" class="filter-bar mb-4">
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
            <label class="form-label-custom">Status Kepegawaian</label>
            <select name="status_kepegawaian" class="form-select">
                <option value="">Semua Status</option>
                <option value="PNS" {{ request('status_kepegawaian') == 'PNS' ? 'selected' : '' }}>PNS</option>
                <option value="PPPK" {{ request('status_kepegawaian') == 'PPPK' ? 'selected' : '' }}>PPPK</option>
            </select>
        </div>
        <div class="col-lg-2 col-md-3 col-6">
            <label class="form-label-custom">Status Aktif</label>
            <select name="status_aktif" class="form-select">
                <option value="">Semua Kondisi</option>
                <option value="Aktif" {{ request('status_aktif') == 'Aktif' ? 'selected' : '' }}>Aktif</option>
                <option value="Pensiun" {{ request('status_aktif') == 'Pensiun' ? 'selected' : '' }}>Pensiun</option>
            </select>
        </div>
        <div class="col-lg-2 col-md-4 col-6">
            <label class="form-label-custom">Unit Kerja</label>
            <select name="uptd_id" class="form-select">
                <option value="">Semua UPTD</option>
                @foreach($uptdList as $uptd)
                    <option value="{{ $uptd->id }}" {{ request('uptd_id') == $uptd->id ? 'selected' : '' }}>
                        {{ $uptd->nama_uptd }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="col-lg-3 col-md-4 col-6">
            <div class="d-flex gap-2">
                <button type="submit" class="btn-filter">
                    <i class="bi bi-funnel-fill"></i> Filter
                </button>
                <a href="{{ route('pegawai.index') }}" class="btn-reset">Reset</a>
            </div>
        </div>
    </div>
</form>

<!-- Results Info & Actions -->
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
    <p style="font-size:13.5px;color:var(--text-secondary);margin:0;">
        Menampilkan <strong>{{ $pegawai->firstItem() ?? 0 }}–{{ $pegawai->lastItem() ?? 0 }}</strong> dari
        <strong>{{ number_format($pegawai->total()) }}</strong> pegawai
    </p>
    <div class="d-flex gap-2">
        <a href="{{ route('pegawai.import') }}" class="btn-secondary-custom">
            <i class="bi bi-file-earmark-excel"></i> Import Excel
        </a>
        <a href="{{ route('pegawai.create') }}" class="btn-primary-custom">
            <i class="bi bi-plus-lg"></i> Tambah Pegawai
        </a>
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
                        <th>Nama</th>
                        <th>NIP</th>
                        <th>Status</th>
                        <th>Kondisi</th>
                        <th>UPTD</th>
                        <th>Kab/Kota</th>
                        <th>No HP</th>
                        <th style="width:120px;" class="text-center">Aksi</th>
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
                        <td style="font-size:12.5px;color:var(--text-secondary);max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                            {{ $p->uptd->nama_uptd ?? '-' }}
                        </td>
                        <td style="font-size:13px;">{{ $p->kabupaten_kota ?? '-' }}</td>
                        <td style="font-size:12.5px;color:var(--text-secondary);">{{ $p->no_hp ?? '-' }}</td>
                        <td class="text-center">
                            <div class="d-flex align-items-center justify-content-center gap-1">
                                <a href="{{ route('pegawai.show', $p) }}" class="btn-action btn-view" title="Detail">
                                    <i class="bi bi-eye"></i>
                                </a>
                                <a href="{{ route('pegawai.edit', $p) }}" class="btn-action btn-edit" title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <form action="{{ route('pegawai.destroy', $p) }}" method="POST" class="d-inline" onsubmit="return confirm('Apakah Anda yakin ingin menghapus data pegawai {{ $p->nama }}?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn-action btn-delete" title="Hapus">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="9">
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
    {{-- Previous --}}
    @if($pegawai->onFirstPage())
        <span class="page-item disabled"><span class="page-link"><i class="bi bi-chevron-left"></i></span></span>
    @else
        <span class="page-item"><a class="page-link" href="{{ $pegawai->previousPageUrl() }}"><i class="bi bi-chevron-left"></i></a></span>
    @endif

    {{-- Page Numbers --}}
    @foreach($pegawai->getUrlRange(max(1, $pegawai->currentPage() - 2), min($pegawai->lastPage(), $pegawai->currentPage() + 2)) as $page => $url)
        <span class="page-item {{ $page == $pegawai->currentPage() ? 'active' : '' }}">
            <a class="page-link" href="{{ $url }}">{{ $page }}</a>
        </span>
    @endforeach

    {{-- Next --}}
    @if($pegawai->hasMorePages())
        <span class="page-item"><a class="page-link" href="{{ $pegawai->nextPageUrl() }}"><i class="bi bi-chevron-right"></i></a></span>
    @else
        <span class="page-item disabled"><span class="page-link"><i class="bi bi-chevron-right"></i></span></span>
    @endif
</nav>
@endif
@endsection
