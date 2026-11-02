<?php

namespace Tests\Feature;

use App\Models\Pegawai;
use App\Models\Uptd;
use Database\Seeders\PegawaiSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PegawaiSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_pegawai_seeder_imports_data_from_excel(): void
    {
        $this->seed(PegawaiSeeder::class);

        $this->assertGreaterThan(0, Pegawai::count());
        $this->assertGreaterThan(0, Uptd::count());

        $this->assertDatabaseHas('pegawai', [
            'nama' => 'FITRIANI, SH.,MM',
            'nik' => '197712092005021004',
            'golongan' => 'III/a',
        ]);

        $pegawai = Pegawai::where('nik', '197712092005021004')->first();
        $this->assertNotNull($pegawai);
        $this->assertNotNull($pegawai->uptd);
    }
}
