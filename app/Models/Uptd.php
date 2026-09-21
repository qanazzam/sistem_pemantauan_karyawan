<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Uptd extends Model
{
    protected $table = 'uptd';

    protected $fillable = [
        'kode',
        'nama_uptd',
        'jumlah_pns',
        'jumlah_pppk',
    ];

    /**
     * Get all pegawai belonging to this UPTD.
     */
    public function pegawai(): HasMany
    {
        return $this->hasMany(Pegawai::class);
    }

    /**
     * Get the total number of pegawai.
     */
    public function getTotalPegawaiAttribute(): int
    {
        return $this->pegawai()->count();
    }
}
