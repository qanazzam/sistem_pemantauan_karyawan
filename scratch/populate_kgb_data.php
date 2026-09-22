<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Pegawai;
use Carbon\Carbon;

// Standard Salary Table PP 5/2024 (Base salary per Golongan & Masa Kerja)
$gajiBase = [
    'II/a' => 2184000,
    'II/b' => 2385000,
    'II/c' => 2485900,
    'II/d' => 2591100,
    'III/a' => 2785700,
    'III/b' => 2903600,
    'III/c' => 3026400,
    'III/d' => 3154400,
    'IV/a' => 3287800,
    'IV/b' => 3426900,
    'IV/c' => 3571900,
    'IX'   => 3203600, // PPPK S1
    'VII'  => 2858800, // PPPK D3
    'V'    => 2511500, // PPPK SMA
];

$kenaikanKgb = [
    'II/a' => 70000,
    'II/b' => 75000,
    'II/c' => 85000,
    'II/d' => 90000,
    'III/a' => 105000,
    'III/b' => 115000,
    'III/c' => 125000,
    'III/d' => 135000,
    'IV/a' => 145000,
    'IV/b' => 155000,
    'IV/c' => 165000,
    'IX'   => 120000,
    'VII'  => 95000,
    'V'    => 80000,
];

$pegawais = Pegawai::all();
$now = Carbon::create(2026, 9, 22);

$countOverdue = 0;
$countWarning = 0;
$countSafe = 0;
$countPension = 0;

foreach ($pegawais as $index => $p) {
    // If retired
    if ($p->status_aktif === 'Pensiun') {
        $p->update([
            'golongan' => 'IV/a',
            'mkg_tahun' => 32,
            'mkg_bulan' => 0,
            'tmt_kgb_terakhir' => Carbon::create(2024, 4, 1),
            'tmt_kgb_berikutnya' => null,
            'gaji_pokok_terakhir' => 5399900,
            'estimasi_gaji_baru' => null,
        ]);
        $countPension++;
        continue;
    }

    $age = $p->umur_sekarang ?? 40;
    $nama = strtoupper($p->nama);

    // Determine Golongan
    if ($p->status_kepegawaian === 'PPPK') {
        if (str_contains($nama, 'S.') || str_contains($nama, 'SE') || str_contains($nama, 'SP')) {
            $golongan = 'IX';
        } elseif ($age > 45) {
            $golongan = 'VII';
        } else {
            $golongan = ($index % 3 === 0) ? 'IX' : (($index % 3 === 1) ? 'VII' : 'V');
        }
    } else {
        // PNS
        if (str_contains($nama, 'MT') || str_contains($nama, 'M.SI') || str_contains($nama, 'M.M')) {
            $golongan = 'IV/b';
        } elseif ($age >= 50 && (str_contains($nama, 'ST') || str_contains($nama, 'S.SOS') || str_contains($nama, 'SE'))) {
            $golongan = ($index % 2 === 0) ? 'IV/a' : 'III/d';
        } elseif ($age >= 45) {
            $golongan = ($index % 3 === 0) ? 'III/d' : (($index % 3 === 1) ? 'III/c' : 'III/b');
        } elseif ($age >= 35) {
            $golongan = ($index % 3 === 0) ? 'III/c' : (($index % 3 === 1) ? 'III/b' : 'III/a');
        } elseif ($age >= 30) {
            $golongan = ($index % 2 === 0) ? 'III/a' : 'II/d';
        } else {
            $golongan = ($index % 2 === 0) ? 'II/c' : 'II/d';
        }
    }

    // Estimate Masa Kerja Golongan (MKG) based on age
    $estMasaKerja = max(2, min(32, ($age - 25)));
    // Make it even for standard KGB period
    $mkgTahun = (int) (floor($estMasaKerja / 2) * 2);
    $mkgBulan = 0;

    // Calculate salaries
    $base = $gajiBase[$golongan] ?? 2800000;
    $step = $kenaikanKgb[$golongan] ?? 100000;
    $gajiPokok = $base + (($mkgTahun / 2) * $step);
    $gajiBaru = $gajiPokok + $step;

    // Distribute TMT KGB realistically:
    // Some are Overdue (Jatuh Tempo): TMT Berikutnya in June/July/August 2026 (15-20%)
    // Some are Warning (Segera KGB): TMT Berikutnya in Oct/Nov/Dec 2026 (15-20%)
    // Majority are Safe (Akan Datang): TMT Berikutnya in 2027 / 2028 (60-70%)
    $dist = $index % 10;
    if ($dist === 0 || $dist === 1) {
        // Jatuh Tempo (Overdue: Juni - Agustus 2026)
        $month = ($index % 3 === 0) ? 6 : (($index % 3 === 1) ? 7 : 8);
        $day = 1;
        $tmtNext = Carbon::create(2026, $month, $day);
        $tmtLast = $tmtNext->copy()->subYears(2);
        $countOverdue++;
    } elseif ($dist === 2 || $dist === 3) {
        // Segera KGB (Dalam 1-3 bulan: Oktober - Desember 2026)
        $month = ($index % 3 === 0) ? 10 : (($index % 3 === 1) ? 11 : 12);
        $day = 1;
        $tmtNext = Carbon::create(2026, $month, $day);
        $tmtLast = $tmtNext->copy()->subYears(2);
        $countWarning++;
    } else {
        // Akan Datang (Tahun 2027)
        $month = ($index % 12) + 1;
        $day = 1;
        $tmtNext = Carbon::create(2027, $month, $day);
        $tmtLast = $tmtNext->copy()->subYears(2);
        $countSafe++;
    }

    $p->update([
        'golongan' => $golongan,
        'mkg_tahun' => $mkgTahun,
        'mkg_bulan' => $mkgBulan,
        'tmt_kgb_terakhir' => $tmtLast,
        'tmt_kgb_berikutnya' => $tmtNext,
        'gaji_pokok_terakhir' => $gajiPokok,
        'estimasi_gaji_baru' => $gajiBaru,
    ]);
}

echo "=== Sukses Mengisi Data KGB Pegawai ===\n";
echo "Total Pegawai: " . Pegawai::count() . "\n";
echo "Pensiun (Tidak Berlaku): " . $countPension . "\n";
echo "KGB Jatuh Tempo (Overdue): " . $countOverdue . "\n";
echo "Segera KGB (1-3 Bulan): " . $countWarning . "\n";
echo "Akan Datang (Aman): " . $countSafe . "\n";

echo "\nContoh 5 Pegawai Segera KGB:\n";
$warningList = Pegawai::where('status_aktif', 'Aktif')
    ->where('tmt_kgb_berikutnya', '>', '2026-09-22')
    ->where('tmt_kgb_berikutnya', '<=', '2026-12-31')
    ->limit(5)
    ->get();

foreach ($warningList as $w) {
    echo "{$w->nama} ({$w->status_kepegawaian} {$w->golongan}) | TMT Berikutnya: " . $w->tmt_kgb_berikutnya->format('d-m-Y') . " | Sisa: {$w->sisa_waktu_kgb} | Gaji Baru: {$w->estimasi_gaji_baru_formatted}\n";
}
