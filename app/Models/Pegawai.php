<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Pegawai extends Model
{
    protected $table = 'pegawai';

    protected $fillable = [
        'uptd_id',
        'nama',
        'nik',
        'no_kk',
        'tanggal_lahir',
        'umur',
        'status_kepegawaian',
        'status_aktif',
        'golongan',
        'mkg_tahun',
        'mkg_bulan',
        'tmt_kgb_terakhir',
        'tmt_kgb_berikutnya',
        'gaji_pokok_terakhir',
        'estimasi_gaji_baru',
        'provinsi',
        'kabupaten_kota',
        'kelurahan',
        'kecamatan',
        'alamat_domisili',
        'no_hp',
        'alamat_ktp',
    ];

    protected $casts = [
        'tanggal_lahir' => 'date',
        'tmt_kgb_terakhir' => 'date',
        'tmt_kgb_berikutnya' => 'date',
        'gaji_pokok_terakhir' => 'float',
        'estimasi_gaji_baru' => 'float',
    ];

    /**
     * NIP / NIK alias accessor and mutator.
     */
    public function getNipAttribute(): ?string
    {
        return $this->nik;
    }

    public function setNipAttribute($value): void
    {
        $this->attributes['nik'] = $value;
    }

    /**
     * Get the UPTD that this pegawai belongs to.
     */
    public function uptd(): BelongsTo
    {
        return $this->belongsTo(Uptd::class);
    }

    /**
     * Get formatted tanggal lahir.
     */
    public function getTanggalLahirFormattedAttribute(): string
    {
        return $this->tanggal_lahir
            ? $this->tanggal_lahir->format('d F Y')
            : '-';
    }

    /**
     * Get formatted TMT KGB Terakhir.
     */
    public function getTmtKgbTerakhirFormattedAttribute(): string
    {
        return $this->tmt_kgb_terakhir
            ? $this->tmt_kgb_terakhir->format('d F Y')
            : '-';
    }

    /**
     * Get formatted TMT KGB Berikutnya.
     */
    public function getTmtKgbBerikutnyaFormattedAttribute(): string
    {
        return $this->tmt_kgb_berikutnya
            ? $this->tmt_kgb_berikutnya->format('d F Y')
            : '-';
    }

    /**
     * Calculate current age.
     */
    public function getUmurSekarangAttribute(): ?int
    {
        if (! $this->tanggal_lahir) {
            return $this->umur;
        }

        return $this->tanggal_lahir->age;
    }

    /**
     * Determine status KGB.
     */
    public function getStatusKgbAttribute(): string
    {
        if ($this->status_aktif === 'Pensiun') {
            return 'Pensiun';
        }

        if (! $this->tmt_kgb_berikutnya) {
            return 'Belum Ada Data';
        }

        if ($this->tmt_kgb_berikutnya->isPast()) {
            return 'Jatuh Tempo';
        }

        if ($this->tmt_kgb_berikutnya->diffInDays(now()) <= 90) {
            return 'Segera KGB';
        }

        return 'Akan Datang';
    }

    /**
     * Get badge class for KGB.
     */
    public function getKgbBadgeClassAttribute(): string
    {
        return match ($this->status_kgb) {
            'Jatuh Tempo' => 'badge-kgb-overdue',
            'Segera KGB' => 'badge-kgb-warning',
            'Akan Datang' => 'badge-kgb-safe',
            'Pensiun' => 'badge-pensiun',
            default => 'badge-unknown',
        };
    }

    /**
     * Get remaining time until KGB.
     */
    public function getSisaWaktuKgbAttribute(): string
    {
        if ($this->status_aktif === 'Pensiun') {
            return 'Pensiun';
        }

        if (! $this->tmt_kgb_berikutnya) {
            return '-';
        }

        if ($this->tmt_kgb_berikutnya->isPast()) {
            $days = abs((int) now()->diffInDays($this->tmt_kgb_berikutnya));

            return $days === 0 ? 'Hari ini' : "Lewat {$days} hari";
        }

        $days = abs((int) now()->diffInDays($this->tmt_kgb_berikutnya));
        $months = abs((int) now()->diffInMonths($this->tmt_kgb_berikutnya));

        if ($months >= 1) {
            return "Sisa {$months} bulan";
        }

        return "Sisa {$days} hari";
    }

    /**
     * Formatted Gaji Pokok Terakhir.
     */
    public function getGajiPokokFormattedAttribute(): string
    {
        return $this->gaji_pokok_terakhir
            ? 'Rp '.number_format($this->gaji_pokok_terakhir, 0, ',', '.')
            : '-';
    }

    /**
     * Formatted Estimasi Gaji Baru.
     */
    public function getEstimasiGajiBaruFormattedAttribute(): string
    {
        return $this->estimasi_gaji_baru
            ? 'Rp '.number_format($this->estimasi_gaji_baru, 0, ',', '.')
            : '-';
    }

    /**
     * Get status badge CSS class.
     */
    public function getStatusBadgeClassAttribute(): string
    {
        return match ($this->status_aktif) {
            'Aktif' => 'badge-aktif',
            'Pensiun' => 'badge-pensiun',
            default => 'badge-unknown',
        };
    }

    /**
     * Get kepegawaian badge CSS class.
     */
    public function getKepegawaianBadgeClassAttribute(): string
    {
        return match ($this->status_kepegawaian) {
            'PNS' => 'badge-pns',
            'PPPK' => 'badge-pppk',
            default => 'badge-unknown',
        };
    }
}
