@extends('layouts.app')

@section('title', $pegawai->nama)
@section('page-title', 'Detail Pegawai')
@section('page-subtitle', $pegawai->nama)

@section('content')
<a href="{{ url()->previous() }}" class="btn-back">
    <i class="bi bi-arrow-left"></i> Kembali
</a>

<div class="detail-card animate-in">
    <!-- Header -->
    <div class="detail-header">
        <div class="detail-avatar">
            {{ strtoupper(substr($pegawai->nama, 0, 2)) }}
        </div>
        <div class="detail-name">{{ $pegawai->nama }}</div>
        <div class="detail-uptd">
            <i class="bi bi-building me-1"></i>
            {{ $pegawai->uptd->nama_uptd ?? 'Tidak ada UPTD' }}
        </div>
        <div class="d-flex gap-2 mt-3" style="position:relative;z-index:2;">
            <span class="badge-custom {{ $pegawai->kepegawaian_badge_class }}" style="background:rgba(255,255,255,0.2);color:#fff;">
                {{ $pegawai->status_kepegawaian }}
            </span>
            <span class="badge-custom {{ $pegawai->status_badge_class }}" style="background:rgba(255,255,255,0.2);color:#fff;">
                <i class="bi {{ $pegawai->status_aktif == 'Aktif' ? 'bi-check-circle' : 'bi-hourglass-split' }}"></i>
                {{ $pegawai->status_aktif }}
            </span>
        </div>
    </div>

    <!-- Body -->
    <div class="detail-body">
        <h6 style="font-size:13px;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.05em;margin-bottom:20px;">
            Informasi Pribadi
        </h6>

        <div class="detail-info-grid">
            <div class="detail-info-item">
                <span class="detail-info-label">NIP (atau NIK)</span>
                <span class="detail-info-value" style="font-family:monospace;">{{ $pegawai->nik ?? '-' }}</span>
            </div>

            @if($pegawai->no_kk)
            <div class="detail-info-item">
                <span class="detail-info-label">No. Kartu Keluarga</span>
                <span class="detail-info-value" style="font-family:monospace;">{{ $pegawai->no_kk }}</span>
            </div>
            @endif

            <div class="detail-info-item">
                <span class="detail-info-label">Tanggal Lahir</span>
                <span class="detail-info-value">{{ $pegawai->tanggal_lahir_formatted }}</span>
            </div>

            <div class="detail-info-item">
                <span class="detail-info-label">Umur</span>
                <span class="detail-info-value">
                    {{ $pegawai->umur_sekarang ? $pegawai->umur_sekarang . ' Tahun' : '-' }}
                </span>
            </div>

            <div class="detail-info-item">
                <span class="detail-info-label">Status Kepegawaian</span>
                <span class="detail-info-value">
                    <span class="badge-custom {{ $pegawai->kepegawaian_badge_class }}">{{ $pegawai->status_kepegawaian }}</span>
                </span>
            </div>

            <div class="detail-info-item">
                <span class="detail-info-label">Status Aktif</span>
                <span class="detail-info-value">
                    <span class="badge-custom {{ $pegawai->status_badge_class }}">{{ $pegawai->status_aktif }}</span>
                </span>
            </div>
        </div>

        <hr style="margin:28px 0;border-color:var(--border-color);">

        <div class="d-flex align-items-center justify-content-between mb-3">
            <h6 style="font-size:13px;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.05em;margin:0;">
                <i class="bi bi-cash-stack me-1 text-primary"></i> Kenaikan Gaji Berkala (KGB)
            </h6>
            <span class="badge-custom {{ $pegawai->kgb_badge_class }}">
                {{ $pegawai->status_kgb }} ({{ $pegawai->sisa_waktu_kgb }})
            </span>
        </div>

        <div class="detail-info-grid">
            <div class="detail-info-item">
                <span class="detail-info-label">Golongan / Pangkat</span>
                <span class="detail-info-value fw-bold text-primary">
                    {{ $pegawai->golongan ?? '-' }}
                    <span style="font-size:12px;font-weight:normal;color:var(--text-secondary);">({{ $pegawai->status_kepegawaian }})</span>
                </span>
            </div>

            <div class="detail-info-item">
                <span class="detail-info-label">Masa Kerja Golongan (MKG)</span>
                <span class="detail-info-value">
                    {{ $pegawai->mkg_tahun ?? 0 }} Tahun {{ $pegawai->mkg_bulan ?? 0 }} Bulan
                </span>
            </div>

            <div class="detail-info-item">
                <span class="detail-info-label">TMT KGB Terakhir</span>
                <span class="detail-info-value">{{ $pegawai->tmt_kgb_terakhir_formatted }}</span>
            </div>

            <div class="detail-info-item">
                <span class="detail-info-label">Jatuh Tempo KGB Berikutnya</span>
                <span class="detail-info-value fw-bold" style="color:var(--text-primary);">
                    {{ $pegawai->tmt_kgb_berikutnya_formatted }}
                </span>
            </div>

            <div class="detail-info-item">
                <span class="detail-info-label">Gaji Pokok Terakhir</span>
                <span class="detail-info-value">{{ $pegawai->gaji_pokok_formatted }}</span>
            </div>

            <div class="detail-info-item">
                <span class="detail-info-label">Estimasi Gaji Pokok Baru</span>
                <span class="detail-info-value fw-bold" style="color:var(--primary);font-size:16px;">
                    {{ $pegawai->estimasi_gaji_baru_formatted }}
                </span>
            </div>
        </div>

        <hr style="margin:28px 0;border-color:var(--border-color);">

        <h6 style="font-size:13px;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.05em;margin-bottom:20px;">
            Alamat & Kontak
        </h6>

        <div class="detail-info-grid">
            <div class="detail-info-item">
                <span class="detail-info-label">Provinsi</span>
                <span class="detail-info-value">{{ $pegawai->provinsi ?? '-' }}</span>
            </div>

            <div class="detail-info-item">
                <span class="detail-info-label">Kabupaten / Kota</span>
                <span class="detail-info-value">{{ $pegawai->kabupaten_kota ?? '-' }}</span>
            </div>

            <div class="detail-info-item">
                <span class="detail-info-label">Kecamatan</span>
                <span class="detail-info-value">{{ $pegawai->kecamatan ?? '-' }}</span>
            </div>

            <div class="detail-info-item">
                <span class="detail-info-label">Kelurahan</span>
                <span class="detail-info-value">{{ $pegawai->kelurahan ?? '-' }}</span>
            </div>

            <div class="detail-info-item" style="grid-column: span 2;">
                <span class="detail-info-label">Alamat Domisili</span>
                <span class="detail-info-value">{{ $pegawai->alamat_domisili ?? '-' }}</span>
            </div>

            <div class="detail-info-item">
                <span class="detail-info-label">No. HP</span>
                <span class="detail-info-value">
                    @if($pegawai->no_hp)
                        <i class="bi bi-telephone-fill me-1" style="font-size:12px;color:var(--primary);"></i>
                        {{ $pegawai->no_hp }}
                    @else
                        -
                    @endif
                </span>
            </div>

            @if($pegawai->alamat_ktp)
            <div class="detail-info-item" style="grid-column: span 2;">
                <span class="detail-info-label">Alamat KTP</span>
                <span class="detail-info-value">{{ $pegawai->alamat_ktp }}</span>
            </div>
            @endif
        </div>
    </div>
</div>
@endsection
