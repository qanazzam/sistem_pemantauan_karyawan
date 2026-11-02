<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;

class Pegawai extends Model
{
    protected $table = 'pegawai';

    /**
     * Pengelompokan status untuk rekap jumlah PNS & PPPK.
     */
    public const GROUP_PNS = ['PNS', 'ASN'];
    public const GROUP_PPPK = ['PPPK', 'PPPK PW'];

    protected $fillable = [
        'uptd_id',
        'nama',
        'nip',
        'nik',
        'no_kk',
        'tanggal_lahir',
        'umur',
        'status_kepegawaian',
        'tmt_pengangkatan',
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
        'tmt_pengangkatan' => 'date',
        'tmt_kgb_terakhir' => 'date',
        'tmt_kgb_berikutnya' => 'date',
        'gaji_pokok_terakhir' => 'float',
        'estimasi_gaji_baru' => 'float',
    ];

    /**
     * Siklus KGB (tahun) bagi PNS & PPPK.
     */
    public const SIKLUS_KGB_TAHUN = 2;

    protected static function booted(): void
    {
        // Setiap kali disimpan, sinkronkan tanggal lahir dari NIP & hitung ulang TMT pengangkatan & jadwal KGB dari NIP.
        static::saving(function (Pegawai $p) {
            $p->sinkronkanTanggalLahirDariNip();
            $p->hitungJadwalKgb();
        });
    }

    /**
     * Ambil tanggal lahir dari NIP 18 digit (8 digit pertama: YYYYMMDD).
     */
    public static function tanggalLahirDariNip(?string $nip): ?Carbon
    {
        $nip = preg_replace('/\D/', '', (string) $nip);
        if (strlen($nip) !== 18) {
            return null;
        }

        $tahun = (int) substr($nip, 0, 4);
        $bulan = (int) substr($nip, 4, 2);
        $hari = (int) substr($nip, 6, 2);

        if ($tahun < 1930 || $tahun > (int) now()->year || ! checkdate($bulan, $hari, $tahun)) {
            return null;
        }

        return Carbon::create($tahun, $bulan, $hari)->startOfDay();
    }

    /**
     * Sinkronkan tanggal lahir otomatis dari NIP.
     * Jika ada NIP (18 digit), tanggal lahir diisi otomatis dari NIP dan umur dihitung.
     * Jika tidak ada NIP (fallback ke NIK) atau NIP tidak valid, maka tanggal lahir dan umur dikosongkan (null).
     */
    public function sinkronkanTanggalLahirDariNip(): void
    {
        if (empty($this->nip)) {
            $this->tanggal_lahir = null;
            $this->umur = null;

            return;
        }

        $tglLahir = self::tanggalLahirDariNip($this->nip);
        if ($tglLahir) {
            $this->tanggal_lahir = $tglLahir->toDateString();
            $this->umur = $tglLahir->age;
        } else {
            // NIP tidak valid 18 digit
            $this->tanggal_lahir = null;
            $this->umur = null;
        }
    }

    /**
     * Ambil TMT pengangkatan dari NIP 18 digit.
     * Format NIP PNS : YYYYMMDD (lahir) + YYYYMM (TMT CPNS) + G (jenis kelamin) + NNN (urut).
     * Format NIP PPPK: YYYYMMDD (lahir) + YYYY (tahun TMT) + "21" (kode PPPK) + G + NNN.
     *   Bulan TMT tidak tercantum, sehingga dianggap Januari (perkiraan).
     */
    public static function tmtDariNip(?string $nip): ?Carbon
    {
        $nip = preg_replace('/\D/', '', (string) $nip);
        if (strlen($nip) !== 18) {
            return null;
        }

        $tahun = (int) substr($nip, 8, 4);
        $bulan = (int) substr($nip, 12, 2);
        $lahir = (int) substr($nip, 0, 4);

        if ($bulan === 21) {
            $bulan = 1; // NIP PPPK: bulan TMT tidak diketahui
        }

        if ($bulan < 1 || $bulan > 12 || $tahun < 1960 || $tahun > (int) now()->year || $tahun - $lahir < 17) {
            return null;
        }

        return Carbon::create($tahun, $bulan, 1)->startOfDay();
    }

    /**
     * True jika bulan TMT hanya perkiraan (NIP berformat PPPK kode "21").
     */
    public function getTmtPerkiraanAttribute(): bool
    {
        if (empty($this->nip)) {
            return false;
        }

        $nip = preg_replace('/\D/', '', (string) $this->nip);

        return strlen($nip) === 18 && substr($nip, 12, 2) === '21';
    }

    /**
     * Hitung KGB terakhir & berikutnya: setiap 2 tahun sejak TMT pengangkatan.
     * Jika NIP kosong (fallback ke NIK) atau NIP tidak valid, TMT dan Jadwal KGB menjadi null ("-").
     */
    public function hitungJadwalKgb(): void
    {
        if (empty($this->nip)) {
            $this->tmt_pengangkatan = null;
            $this->tmt_kgb_terakhir = null;
            $this->tmt_kgb_berikutnya = null;

            return;
        }

        $tmt = self::tmtDariNip($this->nip);
        if (! $tmt) {
            // NIP tidak valid: jadwal KGB dan TMT dikosongkan agar menjadi "-"
            $this->tmt_pengangkatan = null;
            $this->tmt_kgb_terakhir = null;
            $this->tmt_kgb_berikutnya = null;

            return;
        }

        $this->tmt_pengangkatan = $tmt;
        $batas = now()->startOfMonth();

        $n = 1;
        while ($tmt->copy()->addYears($n * self::SIKLUS_KGB_TAHUN)->lt($batas)) {
            $n++;
        }

        $this->tmt_kgb_berikutnya = $tmt->copy()->addYears($n * self::SIKLUS_KGB_TAHUN);
        $this->tmt_kgb_terakhir = $n > 1 ? $tmt->copy()->addYears(($n - 1) * self::SIKLUS_KGB_TAHUN) : null;
    }

    /**
     * Majukan jadwal KGB yang sudah lewat bulan (dipanggil saat halaman dibuka).
     * Dibatasi dengan cache agar tidak berjalan berulang-ulang di setiap navigasi halaman.
     */
    public static function perbaruiJadwalKgb(bool $force = false): void
    {
        if (! $force && Cache::has('kgb_jadwal_last_updated')) {
            return;
        }

        static::whereNotNull('tmt_pengangkatan')
            ->where(fn ($q) => $q->whereNull('tmt_kgb_berikutnya')
                ->orWhere('tmt_kgb_berikutnya', '<', now()->startOfMonth()->toDateString()))
            ->chunkById(100, function ($pegawais) {
                foreach ($pegawais as $p) {
                    $p->save();
                }
            });

        Cache::put('kgb_jadwal_last_updated', true, now()->addHour());
    }

    /**
     * Masa kerja sejak TMT pengangkatan.
     */
    public function getMasaKerjaAttribute(): string
    {
        if (! $this->tmt_pengangkatan) {
            return '-';
        }

        $d = $this->tmt_pengangkatan->diff(now());

        return "{$d->y} Thn {$d->m} Bln";
    }

    /**
     * Identitas tampilan (NIP jika ada, atau NIK jika belum memiliki NIP).
     */
    public function getNomorIdentitasAttribute(): ?string
    {
        return $this->nip ?: $this->nik;
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
     * Get formatted TMT Pengangkatan.
     */
    public function getTmtPengangkatanFormattedAttribute(): string
    {
        if (! $this->tmt_pengangkatan) {
            return '-';
        }

        $formatted = $this->tmt_pengangkatan->locale('id')->translatedFormat('F Y');
        if ($this->tmt_perkiraan) {
            $formatted .= ' (perkiraan)';
        }

        return $formatted;
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
            return null;
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
            return '-';
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
            'PNS', 'ASN' => 'badge-pns',
            'PPPK', 'PPPK PW' => 'badge-pppk',
            default => 'badge-unknown',
        };
    }
}
