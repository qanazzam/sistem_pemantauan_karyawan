@extends('layouts.app')

@section('title', 'Unit Kerja (UPTD)')
@section('page-title', 'Unit Kerja')
@section('page-subtitle', 'Daftar Unit Pelaksana Teknis Dinas')

@section('content')
<div class="row g-3">
    @foreach($uptdList as $index => $uptd)
    <div class="col-lg-4 col-md-6">
        <a href="{{ route('uptd.show', $uptd) }}" style="display:block;">
            <div class="uptd-card animate-in" style="animation-delay: {{ ($index + 1) * 0.08 }}s;">
                <div class="uptd-card-icon">
                    <i class="bi bi-building"></i>
                </div>

                <h5>{{ $uptd->nama_uptd }}</h5>

                <div class="uptd-card-total">
                    <span class="number">{{ $uptd->pegawai_count }}</span>
                    <span class="label">Pegawai</span>
                </div>

                <div class="uptd-card-stats">
                    <div class="uptd-card-stat">
                        <span class="dot dot-pns"></span>
                        PNS: {{ $uptd->pns_count }}
                    </div>
                    <div class="uptd-card-stat">
                        <span class="dot dot-pppk"></span>
                        PPPK: {{ $uptd->pppk_count }}
                    </div>
                    <div class="uptd-card-stat">
                        <span class="dot dot-aktif"></span>
                        Aktif: {{ $uptd->aktif_count }}
                    </div>
                    @if($uptd->pensiun_count > 0)
                    <div class="uptd-card-stat">
                        <span class="dot dot-pensiun"></span>
                        Pensiun: {{ $uptd->pensiun_count }}
                    </div>
                    @endif
                </div>
            </div>
        </a>
    </div>
    @endforeach
</div>
@endsection
