<?php

namespace Tests\Feature;

use App\Models\Pegawai;
use App\Models\Uptd;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class PegawaiImportTest extends TestCase
{
    use RefreshDatabase;

    private function createExcelFile(array $rows, string $sheetTitle = 'Sheet1'): UploadedFile
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle($sheetTitle);

        foreach ($rows as $rowIndex => $row) {
            foreach ($row as $colIndex => $val) {
                $sheet->setCellValue([$colIndex + 1, $rowIndex + 1], $val);
            }
        }

        $tempPath = tempnam(sys_get_temp_dir(), 'test_import_') . '.xlsx';
        $writer = new Xlsx($spreadsheet);
        $writer->save($tempPath);

        return new UploadedFile(
            $tempPath,
            'test_import.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );
    }

    public function test_import_auto_seeds_uptd_when_database_is_empty_and_does_not_crash(): void
    {
        // Ensure UPTD table is completely empty
        Uptd::query()->delete();
        $this->assertEquals(0, Uptd::count());

        $file = $this->createExcelFile([
            ['NAMA', 'NIP', 'NIK', 'UNIT_KERJA', 'STATUS_KEPEGAWAIAN', 'GOLONGAN'],
            ['Andi Makmur', '198701012015011001', '7371010101870001', 'UPTD WS. JENEBERANG', 'PNS', 'III/a'],
        ]);

        $response = $this->post(route('pegawai.import.process'), [
            'excel_file' => $file,
        ]);

        $response->assertRedirect(route('pegawai.index'));
        $response->assertSessionHas('success');

        // Confirm UPTD was auto-seeded and pegawai was imported
        $this->assertGreaterThan(0, Uptd::count());
        $this->assertDatabaseHas('pegawai', [
            'nama' => 'Andi Makmur',
            'nip' => '198701012015011001',
            'nik' => '7371010101870001',
        ]);
    }

    public function test_import_falls_back_to_default_uptd_when_unit_kerja_column_is_empty(): void
    {
        Uptd::query()->delete();

        $file = $this->createExcelFile([
            ['NAMA', 'NIP', 'NIK', 'UNIT_KERJA', 'STATUS_KEPEGAWAIAN', 'GOLONGAN'],
            ['Siti Fatimah', '199002022020022002', '7371020202900002', '', 'PPPK', 'IX'],
        ]);

        $response = $this->post(route('pegawai.import.process'), [
            'excel_file' => $file,
        ]);

        $response->assertRedirect(route('pegawai.index'));
        $response->assertSessionHas('success');

        $pegawai = Pegawai::where('nip', '199002022020022002')->first();
        $this->assertNotNull($pegawai);
        $this->assertNotNull($pegawai->uptd_id);
    }

    public function test_import_auto_creates_new_uptd_if_unrecognized_name_given(): void
    {
        $file = $this->createExcelFile([
            ['NAMA', 'NIP', 'NIK', 'UNIT_KERJA', 'STATUS_KEPEGAWAIAN', 'GOLONGAN'],
            ['Rahmat Hidayat', '199203032021031003', '7371030303920003', 'UPTD WS. TALLO BONE', 'PNS', 'III/b'],
        ]);

        $response = $this->post(route('pegawai.import.process'), [
            'excel_file' => $file,
        ]);

        $response->assertRedirect(route('pegawai.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('uptd', [
            'nama_uptd' => 'UPTD WS. TALLO BONE',
        ]);
        $this->assertDatabaseHas('pegawai', [
            'nama' => 'Rahmat Hidayat',
        ]);
    }

    public function test_import_supports_empty_nip_with_nik_fallback(): void
    {
        $file = $this->createExcelFile([
            ['NAMA', 'NIP', 'NIK', 'UNIT_KERJA', 'STATUS_KEPEGAWAIAN', 'GOLONGAN'],
            ['Nur Hasanah', '', '7371040404940004', 'UPTD WS. JENEBERANG', 'PPPK', 'IX'],
        ]);

        $response = $this->post(route('pegawai.import.process'), [
            'excel_file' => $file,
        ]);

        $response->assertRedirect(route('pegawai.index'));
        $response->assertSessionHas('success');

        $pegawai = Pegawai::where('nik', '7371040404940004')->first();
        $this->assertNotNull($pegawai);
        $this->assertNull($pegawai->nip);
        $this->assertEquals('7371040404940004', $pegawai->nomor_identitas);
        $this->assertNull($pegawai->tanggal_lahir);
        $this->assertNull($pegawai->umur);
    }

    public function test_import_empty_no_hp_does_not_fill_from_alamat_ktp(): void
    {
        $file = $this->createExcelFile([
            ['NAMA', 'NIP', 'NIK', 'UNIT_KERJA', 'STATUS_KEPEGAWAIAN', 'GOLONGAN', 'NO_HP', 'ALAMAT_KTP'],
            ['Sahrul Gunawan', '199505052020121005', '7371050505950005', 'UPTD WS. JENEBERANG', 'PNS', 'III/a', '', 'Jl. Perintis Kemerdekaan KM 10 / DD 6560 VE'],
        ]);

        $response = $this->post(route('pegawai.import.process'), [
            'excel_file' => $file,
        ]);

        $response->assertRedirect(route('pegawai.index'));
        $response->assertSessionHas('success');

        $pegawai = Pegawai::where('nip', '199505052020121005')->first();
        $this->assertNotNull($pegawai);
        $this->assertEmpty($pegawai->no_hp);
        $this->assertStringContainsString('DD 6560 VE', $pegawai->alamat_ktp);
    }
}

