<?php

namespace Database\Seeders;

use App\Models\Uptd;
use Illuminate\Database\Seeder;

class UptdSeeder extends Seeder
{
    public const UPTD_DEFINITIONS = [
        ['nama_uptd' => 'UPTD WS. JENEBERANG', 'kode' => 'JNB'],
        ['nama_uptd' => 'UPTD W. POMPENGAN LARONA', 'kode' => 'PL'],
        ['nama_uptd' => 'UPTD WS. SADDANG', 'kode' => 'SDG'],
        ['nama_uptd' => 'UPTD WS. WALANAE CENRANAE', 'kode' => 'WC'],
        ['nama_uptd' => 'UPTD KAWASAN CPI', 'kode' => 'CPI'],
        ['nama_uptd' => 'DINAS SUMBER DAYA AIR, CIPTA KARYA & TATA RUANG', 'kode' => 'DSDA'],
    ];

    public function run(): void
    {
        foreach (self::UPTD_DEFINITIONS as $item) {
            Uptd::firstOrCreate(
                ['nama_uptd' => $item['nama_uptd']],
                ['kode' => $item['kode']]
            );
        }
    }
}
