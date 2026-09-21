<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use PhpOffice\PhpSpreadsheet\IOFactory;
use App\Models\Uptd;
use App\Models\Pegawai;
use Carbon\Carbon;

class PegawaiSeeder extends Seeder
{
    /**
     * List of known UPTD section headers found in the Excel file.
     */
    private array $uptdNames = [
        'UPTD WS. JENEBERANG',
        'UPTD W. POMPENGAN LARONA',
        'UPTD WS. SADDANG',
        'UPTD WS. WALANAE CENRANAE',
        'UPTD KAWASAN CPI',
    ];

    public function run(): void
    {
        $filePath = base_path('../DATA_PEGAWAI_DENGAN_STATUS.xlsx');

        if (!file_exists($filePath)) {
            $this->command->error("File Excel tidak ditemukan: {$filePath}");
            return;
        }

        $spreadsheet = IOFactory::load($filePath);

        // Create all UPTD records first
        $uptdMap = [];
        foreach ($this->uptdNames as $name) {
            $uptd = Uptd::create([
                'nama_uptd' => $name,
                'kode' => '',
            ]);
            $uptdMap[$name] = $uptd;
        }

        // Process Sheet 1: DATA PEGAWAI PPPK (contains both PNS and PPPK with tanggal lahir + status)
        $this->command->info('Memproses sheet: DATA PEGAWAI PPPK...');
        $this->processSheet1($spreadsheet->getSheetByName('DATA PEGAWAI PPPK'), $uptdMap);

        // Process Sheet 2: DATA PEGAWAI PNS (contains PPPK with KK, no tanggal lahir)
        $this->command->info('Memproses sheet: DATA PEGAWAI PNS...');
        $this->processSheet2($spreadsheet->getSheetByName('DATA PEGAWAI PNS '), $uptdMap);

        // Update UPTD counts
        foreach ($uptdMap as $uptd) {
            $uptd->update([
                'jumlah_pns' => $uptd->pegawai()->where('status_kepegawaian', 'PNS')->count(),
                'jumlah_pppk' => $uptd->pegawai()->where('status_kepegawaian', 'PPPK')->count(),
            ]);
        }

        $totalPegawai = Pegawai::count();
        $this->command->info("Total pegawai diimport: {$totalPegawai}");

        // Print per-UPTD counts
        foreach ($uptdMap as $name => $uptd) {
            $count = $uptd->pegawai()->count();
            $this->command->info("  {$name}: {$count}");
        }
    }

    /**
     * Process Sheet 1 (DATA PEGAWAI PPPK) - has TANGGAL LAHIR, STATUS PNS columns
     * Columns: NO(A), NAMA(B), (merged C), KTP/NIK(D), TANGGAL LAHIR(E), STATUS PNS(F),
     *          PROVINSI(G), KAB_KOTA(H), KELURAHAN(I), KECAMATAN(J), ALAMAT DOMISILI(K),
     *          NO HP(L), STATUS PEGAWAI(M), ALAMAT KTP(N)
     */
    private function processSheet1($sheet, array &$uptdMap): void
    {
        if (!$sheet) {
            $this->command->warn('Sheet "DATA PEGAWAI PPPK" tidak ditemukan.');
            return;
        }

        $maxRow = $sheet->getHighestRow();
        $currentUptd = null;
        $imported = 0;

        for ($row = 3; $row <= $maxRow; $row++) {
            $colB = trim((string) $sheet->getCell("B{$row}")->getValue());
            $nik = trim((string) $sheet->getCell("D{$row}")->getValue());

            // Skip completely empty rows
            if (empty($colB) && empty($nik)) {
                continue;
            }

            // Check if this is a UPTD section header (has UPTD in name, no NIK)
            if (empty($nik) && $this->isUptdHeader($colB)) {
                $foundUptd = $this->findUptd($colB, $uptdMap);
                if ($foundUptd) {
                    $currentUptd = $foundUptd;
                    $this->command->info("  > UPTD terdeteksi (row {$row}): {$currentUptd->nama_uptd}");
                }
                continue;
            }

            // Skip sub-headers and non-data rows (no NIK = not actual employee data)
            if (empty($nik)) {
                continue;
            }

            // Must have a current UPTD context
            if (!$currentUptd) {
                continue;
            }

            // Skip empty names
            if (empty($colB)) {
                continue;
            }

            $tanggalLahirRaw = $sheet->getCell("E{$row}")->getValue();
            $statusPns = trim((string) $sheet->getCell("F{$row}")->getValue());
            $provinsi = trim((string) $sheet->getCell("G{$row}")->getValue());
            $kabKota = trim((string) $sheet->getCell("H{$row}")->getValue());
            $kelurahan = trim((string) $sheet->getCell("I{$row}")->getValue());
            $kecamatan = trim((string) $sheet->getCell("J{$row}")->getValue());
            $alamat = trim((string) $sheet->getCell("K{$row}")->getValue());
            $noHp = trim((string) $sheet->getCell("L{$row}")->getValue());
            $statusPegawai = trim((string) $sheet->getCell("M{$row}")->getValue());
            $alamatKtp = trim((string) $sheet->getCell("N{$row}")->getValue());

            $tanggalLahir = $this->parseDate($tanggalLahirRaw);
            $umur = $tanggalLahir ? Carbon::parse($tanggalLahir)->age : null;

            // Determine status aktif
            $statusAktif = 'Aktif';
            if (strtolower($statusPns) === 'pensiun') {
                $statusAktif = 'Pensiun';
            } elseif ($umur !== null && $umur > 60) {
                $statusAktif = 'Pensiun';
            }

            // Determine status kepegawaian
            $kepegawaian = 'PNS';
            $statusPegawaiUpper = strtoupper($statusPegawai);
            if ($statusPegawaiUpper === 'PPPK' || $statusPegawaiUpper === 'P3K') {
                $kepegawaian = 'PPPK';
            }

            // Check for duplicate by NIK
            if (Pegawai::where('nik', $nik)->exists()) {
                continue;
            }

            Pegawai::create([
                'uptd_id' => $currentUptd->id,
                'nama' => $colB,
                'nik' => $nik,
                'tanggal_lahir' => $tanggalLahir,
                'umur' => $umur,
                'status_kepegawaian' => $kepegawaian,
                'status_aktif' => $statusAktif,
                'provinsi' => $provinsi ?: null,
                'kabupaten_kota' => $kabKota ?: null,
                'kelurahan' => $kelurahan ?: null,
                'kecamatan' => $kecamatan ?: null,
                'alamat_domisili' => $alamat ?: null,
                'no_hp' => $noHp ?: null,
                'alamat_ktp' => $alamatKtp ?: null,
            ]);

            $imported++;
        }

        $this->command->info("  Sheet 1: {$imported} pegawai diimport.");
    }

    /**
     * Process Sheet 2 (DATA PEGAWAI PNS) - has KARTU KELUARGA (KK), no TANGGAL LAHIR
     * Columns: NO(A), NAMA(B), (merged C), KTP/NIK(D), KK(E), PROVINSI(F),
     *          KAB_KOTA(G), KELURAHAN(H), KECAMATAN(I), ALAMAT DOMISILI(J),
     *          NO HP(K), STATUS PEGAWAI(L), ALAMAT KTP(M)
     */
    private function processSheet2($sheet, array &$uptdMap): void
    {
        if (!$sheet) {
            $this->command->warn('Sheet "DATA PEGAWAI PNS " tidak ditemukan.');
            return;
        }

        $maxRow = $sheet->getHighestRow();
        $currentUptd = null;
        $imported = 0;
        $updated = 0;

        for ($row = 3; $row <= $maxRow; $row++) {
            $colB = trim((string) $sheet->getCell("B{$row}")->getValue());
            $nik = trim((string) $sheet->getCell("D{$row}")->getValue());

            if (empty($colB) && empty($nik)) {
                continue;
            }

            // Check for UPTD header
            if (empty($nik) && $this->isUptdHeader($colB)) {
                $foundUptd = $this->findUptd($colB, $uptdMap);
                if ($foundUptd) {
                    $currentUptd = $foundUptd;
                }
                continue;
            }

            // Skip non-data rows
            if (empty($nik) || empty($colB) || !$currentUptd) {
                continue;
            }

            $noKk = trim((string) $sheet->getCell("E{$row}")->getValue());
            $provinsi = trim((string) $sheet->getCell("F{$row}")->getValue());
            $kabKota = trim((string) $sheet->getCell("G{$row}")->getValue());
            $kelurahan = trim((string) $sheet->getCell("H{$row}")->getValue());
            $kecamatan = trim((string) $sheet->getCell("I{$row}")->getValue());
            $alamat = trim((string) $sheet->getCell("J{$row}")->getValue());
            $noHp = trim((string) $sheet->getCell("K{$row}")->getValue());
            $statusPegawai = trim((string) $sheet->getCell("L{$row}")->getValue());
            $alamatKtp = trim((string) $sheet->getCell("M{$row}")->getValue());

            $kepegawaian = 'PNS';
            $statusPegawaiUpper = strtoupper($statusPegawai);
            if ($statusPegawaiUpper === 'PPPK' || $statusPegawaiUpper === 'P3K') {
                $kepegawaian = 'PPPK';
            }

            // Check for existing record by NIK - update with KK info
            $existing = Pegawai::where('nik', $nik)->first();
            if ($existing) {
                if (!empty($noKk) && empty($existing->no_kk)) {
                    $existing->update(['no_kk' => $noKk]);
                }
                $updated++;
                continue;
            }

            Pegawai::create([
                'uptd_id' => $currentUptd->id,
                'nama' => $colB,
                'nik' => $nik,
                'no_kk' => $noKk ?: null,
                'status_kepegawaian' => $kepegawaian,
                'status_aktif' => 'Aktif',
                'provinsi' => $provinsi ?: null,
                'kabupaten_kota' => $kabKota ?: null,
                'kelurahan' => $kelurahan ?: null,
                'kecamatan' => $kecamatan ?: null,
                'alamat_domisili' => $alamat ?: null,
                'no_hp' => $noHp ?: null,
                'alamat_ktp' => $alamatKtp ?: null,
            ]);

            $imported++;
        }

        $this->command->info("  Sheet 2: {$imported} pegawai diimport, {$updated} diperbarui (KK).");
    }

    private function isUptdHeader(string $value): bool
    {
        return str_starts_with(strtoupper(trim($value)), 'UPTD');
    }

    private function findUptd(string $value, array &$uptdMap): ?Uptd
    {
        $value = strtoupper(trim($value));

        // Exact match first
        foreach ($uptdMap as $name => $uptd) {
            if (strtoupper($name) === $value) {
                return $uptd;
            }
        }

        // Partial match
        foreach ($uptdMap as $name => $uptd) {
            if (stripos($value, $name) !== false || stripos($name, $value) !== false) {
                return $uptd;
            }
        }

        // Keyword match
        foreach ($uptdMap as $name => $uptd) {
            // Extract key parts
            $nameUpper = strtoupper($name);
            if (str_contains($value, 'JENEBERANG') && str_contains($nameUpper, 'JENEBERANG')) return $uptd;
            if (str_contains($value, 'POMPENGAN') && str_contains($nameUpper, 'POMPENGAN')) return $uptd;
            if (str_contains($value, 'SADDANG') && str_contains($nameUpper, 'SADDANG')) return $uptd;
            if (str_contains($value, 'WALANAE') && str_contains($nameUpper, 'WALANAE')) return $uptd;
            if (str_contains($value, 'CPI') && str_contains($nameUpper, 'CPI')) return $uptd;
        }

        return null;
    }

    private function parseDate($value): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        if (is_numeric($value)) {
            try {
                $date = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((int) $value);
                return $date->format('Y-m-d');
            } catch (\Exception $e) {
                return null;
            }
        }

        if (is_string($value)) {
            try {
                return Carbon::parse($value)->format('Y-m-d');
            } catch (\Exception $e) {
                return null;
            }
        }

        return null;
    }
}
