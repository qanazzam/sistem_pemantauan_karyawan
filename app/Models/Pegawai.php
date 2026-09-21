<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

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
    ];

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
     * Calculate current age.
     */
    public function getUmurSekarangAttribute(): ?int
    {
        if (!$this->tanggal_lahir) {
            return null;
        }
        return $this->tanggal_lahir->age;
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
