<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Pegawai;
use Carbon\Carbon;

$above58 = Pegawai::whereNotNull('tanggal_lahir')
    ->get()
    ->filter(fn($p) => Carbon::parse($p->tanggal_lahir)->age >= 58);

foreach ($above58 as $p) {
    $age = Carbon::parse($p->tanggal_lahir)->age;
    echo "ID: {$p->id} | {$p->nama} | Status: {$p->status_kepegawaian} | Umur: {$age} | Lahir: " . $p->tanggal_lahir->format('d-m-Y') . " | UPTD: " . $p->uptd->nama_uptd . "\n";
}
