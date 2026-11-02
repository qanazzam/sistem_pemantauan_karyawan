<?php

namespace Tests\Feature;

use App\Models\Pegawai;
use App\Models\Uptd;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PegawaiNipNikTest extends TestCase
{
    use RefreshDatabase;

    private Uptd $uptd;

    protected function setUp(): void
    {
        parent::setUp();

        $this->uptd = Uptd::create([
            'nama_uptd' => 'UPTD WS. JENEBERANG',
            'kode' => 'JNB',
        ]);
    }

    public function test_can_create_pegawai_with_both_nip_and_nik(): void
    {
        $response = $this->post(route('pegawai.store'), [
            'nama' => 'Budi Santoso',
            'nip' => '198507152010011002',
            'nik' => '7371011507850001',
            'status_kepegawaian' => 'PNS',
            'uptd_id' => $this->uptd->id,
            'golongan' => 'III/a',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('pegawai', [
            'nama' => 'Budi Santoso',
            'nip' => '198507152010011002',
            'nik' => '7371011507850001',
        ]);

        $pegawai = Pegawai::where('nip', '198507152010011002')->first();
        $this->assertNotNull($pegawai->tmt_pengangkatan);
        $this->assertNotNull($pegawai->tmt_kgb_berikutnya);
        $this->assertEquals('1985-07-15', $pegawai->tanggal_lahir?->format('Y-m-d'));
        $this->assertNotNull($pegawai->umur);
    }

    public function test_can_create_pegawai_with_only_nik_when_nip_is_empty(): void
    {
        $response = $this->post(route('pegawai.store'), [
            'nama' => 'Siti Nurhaliza',
            'nip' => '',
            'nik' => '7371022204900002',
            'status_kepegawaian' => 'PPPK',
            'uptd_id' => $this->uptd->id,
            'golongan' => 'IX',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('pegawai', [
            'nama' => 'Siti Nurhaliza',
            'nip' => null,
            'nik' => '7371022204900002',
        ]);

        $pegawai = Pegawai::where('nik', '7371022204900002')->first();
        $this->assertEquals('7371022204900002', $pegawai->nomor_identitas);
        $this->assertNull($pegawai->nip);
        $this->assertNull($pegawai->tanggal_lahir);
        $this->assertNull($pegawai->umur);
        $this->assertNull($pegawai->umur_sekarang);
        $this->assertEquals('-', $pegawai->tanggal_lahir_formatted);
        $this->assertNull($pegawai->tmt_pengangkatan);
        $this->assertNull($pegawai->tmt_kgb_terakhir);
        $this->assertNull($pegawai->tmt_kgb_berikutnya);
        $this->assertEquals('-', $pegawai->tmt_pengangkatan_formatted);
        $this->assertEquals('-', $pegawai->tmt_kgb_terakhir_formatted);
        $this->assertEquals('-', $pegawai->tmt_kgb_berikutnya_formatted);
        $this->assertEquals('-', $pegawai->status_kgb);

        // Check show view
        $showResponse = $this->get(route('pegawai.show', $pegawai));
        $showResponse->assertOk();
        $showResponse->assertDontSee('NIP tidak valid');

        // Check KGB view
        $kgbResponse = $this->get(route('kgb.index'));
        $kgbResponse->assertOk();
        $kgbResponse->assertDontSee('NIP tidak valid');
    }

    public function test_updating_pegawai_removing_nip_clears_tanggal_lahir_and_umur(): void
    {
        $pegawai = Pegawai::create([
            'uptd_id' => $this->uptd->id,
            'nama' => 'Andi Wijaya',
            'nip' => '198812102015021001',
            'nik' => '7371011012880001',
            'status_kepegawaian' => 'PNS',
        ]);

        $this->assertEquals('1988-12-10', $pegawai->tanggal_lahir?->format('Y-m-d'));
        $this->assertNotNull($pegawai->umur);

        // Update removing NIP, keeping only NIK
        $response = $this->put(route('pegawai.update', $pegawai), [
            'nama' => 'Andi Wijaya',
            'nip' => '',
            'nik' => '7371011012880001',
            'status_kepegawaian' => 'PPPK',
            'uptd_id' => $this->uptd->id,
        ]);

        $response->assertRedirect();
        $pegawai->refresh();

        $this->assertNull($pegawai->nip);
        $this->assertNull($pegawai->tanggal_lahir);
        $this->assertNull($pegawai->umur);
        $this->assertNull($pegawai->tmt_pengangkatan);
        $this->assertNull($pegawai->tmt_kgb_berikutnya);
    }

    public function test_validation_fails_if_both_nip_and_nik_are_missing(): void
    {
        $response = $this->post(route('pegawai.store'), [
            'nama' => 'Tanpa Identitas',
            'nip' => '',
            'nik' => '',
            'status_kepegawaian' => 'PNS',
            'uptd_id' => $this->uptd->id,
        ]);

        $response->assertSessionHasErrors(['nip', 'nik']);
    }

    public function test_can_search_pegawai_by_nip_or_nik(): void
    {
        Pegawai::create([
            'uptd_id' => $this->uptd->id,
            'nama' => 'Pegawai Satu',
            'nip' => '198001012005011001',
            'nik' => '7371010101800001',
            'status_kepegawaian' => 'PNS',
        ]);

        $responseNip = $this->get(route('pegawai.index', ['search' => '198001012005011001']));
        $responseNip->assertOk();
        $responseNip->assertSee('Pegawai Satu');

        $responseNik = $this->get(route('pegawai.index', ['search' => '7371010101800001']));
        $responseNik->assertOk();
        $responseNik->assertSee('Pegawai Satu');
    }
}
