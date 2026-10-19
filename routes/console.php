<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('pegawai:hitung-kgb', function () {
    $valid = 0;
    $total = 0;
    App\Models\Pegawai::query()->each(function ($p) use (&$valid, &$total) {
        $p->save();
        $total++;
        $valid += $p->tmt_pengangkatan ? 1 : 0;
    });
    $this->info("Jadwal KGB dihitung ulang: {$valid} dari {$total} pegawai memiliki NIP valid.");
})->purpose('Hitung ulang TMT pengangkatan & jadwal KGB semua pegawai dari NIP');
