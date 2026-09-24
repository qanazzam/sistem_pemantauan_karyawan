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
            'nama' => 'Arifuddin',
            'nik' => '196412312008011018',
            'golongan' => 'II/d',
        ]);

        $pegawai = Pegawai::where('nik', '196412312008011018')->first();
        $this->assertNotNull($pegawai);
        $this->assertEquals('UPTD WS. WALANAE CENRANAE', $pegawai->uptd->nama_uptd);
    }
}
