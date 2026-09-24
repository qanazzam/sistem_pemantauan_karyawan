@extends('layouts.app')

@section('title', $pegawai->nama)
@section('page-title', 'Detail Pegawai')
@section('page-subtitle', 'Informasi lengkap data kepegawaian dan riwayat KGB')

@section('content')
<div class="mb-4 d-flex align-items-center justify-content-between flex-wrap gap-2">
    <a href="{{ route('pegawai.index') }}" class="btn-back">
        <i class="bi bi-arrow-left"></i> Kembali ke Daftar
    </a>
    <div class="d-flex gap-2">
        <a href="{{ route('pegawai.edit', $pegawai) }}" class="btn-primary-custom" style="padding: 6px 16px; font-size:13px;">
            <i class="bi bi-pencil-square"></i> Edit Data
        </a>
        <form action="{{ route('pegawai.destroy', $pegawai) }}" method="POST" class="d-inline" onsubmit="return confirm('Apakah Anda yakin ingin menghapus data pegawai {{ $pegawai->nama }}?')">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn-secondary-custom text-danger" style="padding: 6px 16px; font-size:13px;">
                <i class="bi bi-trash"></i> Hapus Pegawai
            </button>
        </form>
    </div>
</div>

<div class="detail-card animate-in">
    <!-- Header Hero -->
    <div class="detail-header">
        <div class="detail-header-content flex-wrap">
            <div class="detail-avatar">
                {{ strtoupper(substr($pegawai->nama, 0, 2)) }}
            </div>
            <div class="flex-grow-1">
                <div class="detail-name">{{ $pegawai->nama }}</div>
                <div class="detail-uptd">
                    <span>{{ $pegawai->uptd->nama_uptd ?? 'Tidak ada UPTD' }}</span>
                </div>
                <div class="detail-header-badges">
                    <span class="detail-header-badge">
                        {{ $pegawai->status_kepegawaian }}
                    </span>
                    <span class="detail-header-badge">
                        {{ $pegawai->status_aktif }}
                    </span>
                    @if($pegawai->golongan)
                    <span class="detail-header-badge">
                        Golongan {{ $pegawai->golongan }}
                    </span>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Body Sections -->
    <div class="detail-body">
        <!-- Section 1: Informasi Kepegawaian & Pribadi -->
        <div class="detail-section">
            <div class="detail-section-header">
                <h6 class="detail-section-title">
                    <i class="bi bi-person-vcard"></i> Informasi Pribadi & Kepegawaian
                </h6>
            </div>
            <div class="detail-info-grid">
                <div class="detail-info-item">
                    <span class="detail-info-label">NIP / NIK</span>
                    <span class="detail-info-value font-monospace">{{ $pegawai->nik ?? '-' }}</span>
                </div>

                @if($pegawai->no_kk)
                <div class="detail-info-item">
                    <span class="detail-info-label">Nomor Kartu Keluarga</span>
                    <span class="detail-info-value font-monospace">{{ $pegawai->no_kk }}</span>
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
                        <span class="badge-custom {{ $pegawai->kepegawaian_badge_class }}">
                            {{ $pegawai->status_kepegawaian }}
                        </span>
                    </span>
                </div>

                <div class="detail-info-item">
                    <span class="detail-info-label">Status Aktif</span>
                    <span class="detail-info-value">
                        <span class="badge-custom {{ $pegawai->status_badge_class }}">
                            {{ $pegawai->status_aktif }}
                        </span>
                    </span>
                </div>
            </div>
        </div>

        <!-- Section 2: Kenaikan Gaji Berkala (KGB) -->
        <div class="detail-section">
            <div class="detail-section-header">
                <h6 class="detail-section-title">
                    <i class="bi bi-cash-stack"></i> Kenaikan Gaji Berkala (KGB)
                </h6>
                @if($pegawai->status_aktif === 'Aktif')
                <span class="badge-custom {{ $pegawai->kgb_badge_class }}">
                    {{ $pegawai->status_kgb }} &bull; {{ $pegawai->sisa_waktu_kgb }}
                </span>
                @else
                <span class="badge-custom badge-pensiun">Pensiun (Tidak Berlaku)</span>
                @endif
            </div>

            <div class="detail-kgb-box">
                <div class="detail-info-grid">
                    <div class="detail-info-item" style="background:#ffffff;">
                        <span class="detail-info-label">Golongan / Pangkat</span>
                        <span class="detail-info-value text-primary">
                            {{ $pegawai->golongan ?? '-' }}
                            <small class="text-secondary fw-normal">({{ $pegawai->status_kepegawaian }})</small>
                        </span>
                    </div>

                    <div class="detail-info-item" style="background:#ffffff;">
                        <span class="detail-info-label">Masa Kerja Golongan (MKG)</span>
                        <span class="detail-info-value">
                            {{ $pegawai->mkg_tahun ?? 0 }} Tahun {{ $pegawai->mkg_bulan ?? 0 }} Bulan
                        </span>
                    </div>

                    <div class="detail-info-item" style="background:#ffffff;">
                        <span class="detail-info-label">TMT KGB Terakhir</span>
                        <span class="detail-info-value">{{ $pegawai->tmt_kgb_terakhir_formatted }}</span>
                    </div>

                    <div class="detail-info-item" style="background:#ffffff; border-color: var(--primary);">
                        <span class="detail-info-label text-primary">Jatuh Tempo KGB Berikutnya</span>
                        <span class="detail-info-value text-primary fw-bold">
                            {{ $pegawai->tmt_kgb_berikutnya_formatted }}
                        </span>
                    </div>

                    <div class="detail-info-item" style="background:#ffffff;">
                        <span class="detail-info-label">Gaji Pokok Terakhir</span>
                        <span class="detail-info-value">{{ $pegawai->gaji_pokok_formatted }}</span>
                    </div>

                    <div class="detail-info-item" style="background:#ffffff; border-color: var(--primary);">
                        <span class="detail-info-label text-primary">Estimasi Gaji Pokok Baru</span>
                        <span class="detail-info-value text-primary fw-bold" style="font-size: 16px;">
                            {{ $pegawai->estimasi_gaji_baru_formatted }}
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Section 3: Alamat & Kontak -->
        <div class="detail-section">
            <div class="detail-section-header">
                <h6 class="detail-section-title">
                    <i class="bi bi-geo-alt"></i> Alamat & Kontak
                </h6>
            </div>
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

                <div class="detail-info-item span-2">
                    <span class="detail-info-label">Alamat Domisili</span>
                    <span class="detail-info-value">{{ $pegawai->alamat_domisili ?? '-' }}</span>
                </div>

                <div class="detail-info-item">
                    <span class="detail-info-label">Nomor Handphone</span>
                    <span class="detail-info-value">
                        @if($pegawai->no_hp)
                            <a href="tel:{{ $pegawai->no_hp }}" class="text-primary text-decoration-none">
                                <i class="bi bi-telephone-fill me-1" style="font-size: 12px;"></i>
                                {{ $pegawai->no_hp }}
                            </a>
                        @else
                            -
                        @endif
                    </span>
                </div>

                @if($pegawai->alamat_ktp)
                <div class="detail-info-item span-2">
                    <span class="detail-info-label">Alamat KTP</span>
                    <span class="detail-info-value">{{ $pegawai->alamat_ktp }}</span>
                </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
