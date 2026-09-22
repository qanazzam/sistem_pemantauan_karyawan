<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Pegawai;
use App\Models\Uptd;
use Carbon\Carbon;

$allPegawai = Pegawai::all();
$updatedToPensiun = 0;
$updatedToAktif = 0;

foreach ($allPegawai as $p) {
    $age = $p->tanggal_lahir ? Carbon::parse($p->tanggal_lahir)->age : $p->umur;
    
    // If age >= 58, set to Pensiun
    if ($age !== null && $age >= 58) {
        if ($p->status_aktif !== 'Pensiun') {
            $p->update([
                'status_aktif' => 'Pensiun',
                'umur' => $age,
            ]);
            $updatedToPensiun++;
            echo "Set PENSIUN: {$p->nama} (Umur: {$age})\n";
        } else {
            $p->update(['umur' => $age]);
        }
    } else {
        // If age < 58 and was Pensiun because of old criteria, revert to Aktif if applicable
        // (unless explicit in excel)
        if ($p->status_aktif === 'Pensiun' && ($age !== null && $age < 58)) {
            $p->update([
                'status_aktif' => 'Aktif',
                'umur' => $age,
            ]);
            $updatedToAktif++;
            echo "Set AKTIF: {$p->nama} (Umur: {$age})\n";
        } elseif ($age !== null) {
            $p->update(['umur' => $age]);
        }
    }
}

echo "\n--- Summary ---\n";
echo "Updated to Pensiun: {$updatedToPensiun}\n";
echo "Updated to Aktif: {$updatedToAktif}\n";
echo "Total Pensiun now: " . Pegawai::where('status_aktif', 'Pensiun')->count() . "\n";
echo "Total Aktif now: " . Pegawai::where('status_aktif', 'Aktif')->count() . "\n";
echo "Total Pegawai: " . Pegawai::count() . "\n";

echo "\nDaftar Pegawai Pensiun (Umur >= 58):\n";
$pensiunList = Pegawai::where('status_aktif', 'Pensiun')->with('uptd')->orderByDesc('umur')->get();
foreach ($pensiunList as $p) {
    echo sprintf(
        "%-30s | Status: %-4s | Umur: %-2s | Lahir: %s | UPTD: %s\n",
        $p->nama,
        $p->status_kepegawaian,
        $p->umur,
        $p->tanggal_lahir ? $p->tanggal_lahir->format('d-m-Y') : '-',
        $p->uptd->nama_uptd ?? '-'
    );
}
