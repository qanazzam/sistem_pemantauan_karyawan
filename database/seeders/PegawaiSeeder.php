<?php

namespace Database\Seeders;

use App\Models\Pegawai;
use App\Models\Uptd;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;

class PegawaiSeeder extends Seeder
{
    /**
     * List of initial UPTD & Unit Kerja to be seeded.
     */
    private array $uptdDefinitions = [
        ['nama_uptd' => 'UPTD WS. JENEBERANG', 'kode' => 'JNB'],
        ['nama_uptd' => 'UPTD W. POMPENGAN LARONA', 'kode' => 'PL'],
        ['nama_uptd' => 'UPTD WS. SADDANG', 'kode' => 'SDG'],
        ['nama_uptd' => 'UPTD WS. WALANAE CENRANAE', 'kode' => 'WC'],
        ['nama_uptd' => 'UPTD KAWASAN CPI', 'kode' => 'CPI'],
        ['nama_uptd' => 'DINAS SUMBER DAYA AIR, CIPTA KARYA & TATA RUANG', 'kode' => 'DSDA'],
    ];

    /**
     * Standard salary base PP 5/2024 for PNS & PPPK.
     */
    private array $gajiBase = [
        'I/a' => 1685700,
        'I/b' => 1840800,
        'I/c' => 1918700,
        'I/d' => 1999900,
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
        'IV/d' => 3723000,
        'IV/e' => 3880400,
        'IX' => 3203600,
        'VII' => 2858800,
        'V' => 2511500,
    ];

    /**
     * Standard salary step (KGB increment).
     */
    private array $kenaikanKgb = [
        'I/a' => 60000,
        'I/b' => 65000,
        'I/c' => 68000,
        'I/d' => 70000,
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
        'IV/d' => 175000,
        'IV/e' => 185000,
        'IX' => 120000,
        'VII' => 95000,
        'V' => 80000,
    ];

    public function run(): void
    {
        $filePath = base_path('DATA_PEGAWAI_DENGAN_STATUS.xlsx');

        if (! file_exists($filePath)) {
            $this->command->error("File Excel tidak ditemukan: {$filePath}");

            return;
        }

        $spreadsheet = IOFactory::load($filePath);

        // Seed or load UPTD records
        $uptdMap = [];
        foreach ($this->uptdDefinitions as $item) {
            $uptd = Uptd::firstOrCreate(
                ['nama_uptd' => $item['nama_uptd']],
                ['kode' => $item['kode']]
            );
            $uptdMap[strtoupper($item['nama_uptd'])] = $uptd;
        }

        // Also index any existing UPTDs in database
        foreach (Uptd::all() as $u) {
            $uptdMap[strtoupper(trim($u->nama_uptd))] = $u;
        }

        // Check format: multi-sheet Dinas vs Tabular
        $sheetPppk = $spreadsheet->getSheetByName('DATA PEGAWAI PPPK');
        $sheetPns = $spreadsheet->getSheetByName('DATA PEGAWAI PNS ');

        if ($sheetPppk || $sheetPns) {
            // Multi-sheet Dinas format
            if ($sheetPppk) {
                $this->command->info('Memproses sheet: DATA PEGAWAI PPPK...');
                $this->processSheet1($sheetPppk, $uptdMap);
            }
            if ($sheetPns) {
                $this->command->info('Memproses sheet: DATA PEGAWAI PNS...');
                $this->processSheet2($sheetPns, $uptdMap);
            }
        } else {
            // Standard Tabular format (matches DATA_PEGAWAI_DENGAN_STATUS.xlsx)
            $sheet = $spreadsheet->getActiveSheet();
            $title = $sheet->getTitle();
            $this->command->info("Memproses sheet tabular: {$title}...");
            $this->processTabularSheet($sheet, $uptdMap);
        }

        // Update UPTD counts
        foreach (Uptd::all() as $uptd) {
            $uptd->update([
                'jumlah_pns' => $uptd->pegawai()->where('status_kepegawaian', 'PNS')->count(),
                'jumlah_pppk' => $uptd->pegawai()->where('status_kepegawaian', 'PPPK')->count(),
            ]);
        }

        $totalPegawai = Pegawai::count();
        $this->command->info("Total pegawai diimport/tersimpan: {$totalPegawai}");

        // Print per-UPTD counts
        foreach (Uptd::orderBy('nama_uptd')->get() as $uptd) {
            $count = $uptd->pegawai()->count();
            $this->command->info("  {$uptd->nama_uptd}: {$count} pegawai ({$uptd->jumlah_pns} PNS, {$uptd->jumlah_pppk} PPPK)");
        }
    }

    /**
     * Process tabular sheet based on row 1 column headers.
     * Compatible with DATA_PEGAWAI_DENGAN_STATUS.xlsx and official template.
     */
    private function processTabularSheet($sheet, array &$uptdMap): void
    {
        $highestRow = $sheet->getHighestRow();
        $highestCol = $sheet->getHighestColumn();
        $highestColIndex = Coordinate::columnIndexFromString($highestCol);

        // Map column headers from row 1
        $headerMap = [];
        for ($col = 1; $col <= $highestColIndex; $col++) {
            $val = trim((string) $sheet->getCell([$col, 1])->getValue());
            if ($val !== '') {
                $norm = strtoupper(str_replace([' ', '-', '/'], '_', $val));
                $headerMap[$norm] = $col;
            }
        }

        $getVal = function (int $row, array $possibleHeaders) use ($sheet, $headerMap) {
            foreach ($possibleHeaders as $header) {
                $norm = strtoupper($header);
                if (isset($headerMap[$norm])) {
                    $col = $headerMap[$norm];

                    return $sheet->getCell([$col, $row])->getValue();
                }
            }

            return null;
        };

        $imported = 0;
        $updated = 0;
        $skipped = 0;

        for ($row = 2; $row <= $highestRow; $row++) {
            $nama = trim((string) ($getVal($row, ['NAMA', 'NAMA_PEGAWAI', 'NAMA_LENGKAP']) ?? $sheet->getCell("A{$row}")->getValue()));
            $nipRaw = $getVal($row, ['NIP', 'NIK', 'NIP_NIK', 'NIK_KTP', 'KTP']) ?? $sheet->getCell("B{$row}")->getValue();
            $nik = trim((string) $nipRaw);

            // Skip empty rows or incomplete data
            if (empty($nama) && empty($nik)) {
                continue;
            }
            if (empty($nama) || empty($nik)) {
                $skipped++;
                $this->command->warn("  [Baris {$row}] Dilewati: Nama atau NIP/NIK kosong (Nama: '{$nama}', NIP: '{$nik}').");

                continue;
            }

            $noKk = trim((string) ($getVal($row, ['NO_KK', 'KK', 'KARTU_KELUARGA']) ?? $sheet->getCell("C{$row}")->getValue()));
            $tglLahirRaw = $getVal($row, ['TANGGAL_LAHIR', 'TGL_LAHIR']) ?? $sheet->getCell("D{$row}")->getValue();
            $statusPegawaiRaw = trim((string) ($getVal($row, ['STATUS_KEPEGAWAIAN', 'STATUS', 'JENIS_PEGAWAI']) ?? $sheet->getCell("E{$row}")->getValue()));
            $unitKerjaRaw = trim((string) ($getVal($row, ['UNIT_KERJA', 'UPTD', 'NAMA_UPTD']) ?? $sheet->getCell("F{$row}")->getValue()));
            $golonganRaw = trim((string) ($getVal($row, ['GOLONGAN', 'GOL', 'PANGKAT']) ?? $sheet->getCell("G{$row}")->getValue()));
            $mkgTahunRaw = $getVal($row, ['MKG_TAHUN', 'MKG_THN', 'MKG']) ?? $sheet->getCell("H{$row}")->getValue();
            $mkgBulanRaw = $getVal($row, ['MKG_BULAN', 'MKG_BLN']) ?? $sheet->getCell("I{$row}")->getValue();
            $tmtKgbRaw = $getVal($row, ['TMT_KGB_TERAKHIR', 'TMT_KGB', 'TMT_TERAKHIR']) ?? $sheet->getCell("J{$row}")->getValue();
            $gajiPokokRaw = $getVal($row, ['GAJI_POKOK_TERAKHIR', 'GAJI_POKOK', 'GAJI']) ?? $sheet->getCell("K{$row}")->getValue();
            $provinsiRaw = trim((string) ($getVal($row, ['PROVINSI']) ?? $sheet->getCell("L{$row}")->getValue()));
            $kabKota = trim((string) ($getVal($row, ['KABUPATEN_KOTA', 'KABUPATEN', 'KOTA']) ?? $sheet->getCell("M{$row}")->getValue()));
            $kecamatan = trim((string) ($getVal($row, ['KECAMATAN']) ?? $sheet->getCell("N{$row}")->getValue()));
            $kelurahan = trim((string) ($getVal($row, ['KELURAHAN']) ?? $sheet->getCell("O{$row}")->getValue()));
            $alamatDomisili = trim((string) ($getVal($row, ['ALAMAT_DOMISILI', 'ALAMAT']) ?? $sheet->getCell("P{$row}")->getValue()));
            $noHp = trim((string) ($getVal($row, ['NO_HP', 'HP', 'TELEPON']) ?? $sheet->getCell("Q{$row}")->getValue()));
            $alamatKtp = trim((string) ($getVal($row, ['ALAMAT_KTP']) ?? $sheet->getCell("R{$row}")->getValue()));

            // Determine status kepegawaian (PNS vs PPPK)
            $kepegawaian = 'PNS';
            $statusUpper = strtoupper($statusPegawaiRaw);
            if (str_contains($statusUpper, 'PPPK') || str_contains($statusUpper, 'P3K')) {
                $kepegawaian = 'PPPK';
            }

            // Parse Tanggal Lahir & calculate age
            $tanggalLahir = $this->parseDate($tglLahirRaw);
            $umur = $tanggalLahir ? Carbon::parse($tanggalLahir)->age : null;

            // Determine status aktif (Batas Usia Pensiun = 58 tahun)
            $statusAktif = 'Aktif';
            if ($umur !== null && $umur >= 58) {
                $statusAktif = 'Pensiun';
            }

            // Match UPTD / Unit Kerja
            $uptd = $this->resolveUptd($unitKerjaRaw, $uptdMap);
            if (! $uptd) {
                $uptd = $uptdMap['UPTD WS. JENEBERANG'] ?? Uptd::first();
            }

            // Golongan
            $golongan = $golonganRaw ?: ($kepegawaian === 'PNS' ? 'III/a' : 'IX');

            // Masa Kerja Golongan (MKG)
            $mkgTahun = is_numeric($mkgTahunRaw)
                ? (int) $mkgTahunRaw
                : max(0, min(32, (int) (floor(max(0, ($umur ?? 40) - 25) / 2) * 2)));
            $mkgBulan = is_numeric($mkgBulanRaw) ? (int) $mkgBulanRaw : 0;

            // KGB Dates & Salaries
            $tmtKgbTerakhir = $this->parseDate($tmtKgbRaw);
            if ($tmtKgbTerakhir) {
                $tmtKgbBerikutnya = Carbon::parse($tmtKgbTerakhir)->addYears(2)->toDateString();
            } elseif ($statusAktif === 'Pensiun') {
                $tmtKgbTerakhir = '2024-04-01';
                $tmtKgbBerikutnya = null;
            } else {
                // Distribute KGB realistic schedule for active employees
                $dist = $imported % 10;
                if ($dist === 0 || $dist === 1) {
                    // Jatuh Tempo (Overdue: Juni - Agustus 2026)
                    $month = ($imported % 3 === 0) ? 6 : (($imported % 3 === 1) ? 7 : 8);
                    $tmtNextObj = Carbon::create(2026, $month, 1);
                    $tmtLastObj = $tmtNextObj->copy()->subYears(2);
                } elseif ($dist === 2 || $dist === 3) {
                    // Segera KGB (Dalam 1-3 bulan: Oktober - Desember 2026)
                    $month = ($imported % 3 === 0) ? 10 : (($imported % 3 === 1) ? 11 : 12);
                    $tmtNextObj = Carbon::create(2026, $month, 1);
                    $tmtLastObj = $tmtNextObj->copy()->subYears(2);
                } else {
                    // Akan Datang (Aman: 2027)
                    $month = ($imported % 12) + 1;
                    $tmtNextObj = Carbon::create(2027, $month, 1);
                    $tmtLastObj = $tmtNextObj->copy()->subYears(2);
                }
                $tmtKgbBerikutnya = $tmtNextObj->toDateString();
                $tmtKgbTerakhir = $tmtLastObj->toDateString();
            }

            // Gaji Pokok & Estimasi Gaji Baru
            $baseSalary = $this->gajiBase[$golongan] ?? 2800000;
            $stepSalary = $this->kenaikanKgb[$golongan] ?? 100000;
            $gajiPokok = is_numeric($gajiPokokRaw)
                ? (float) $gajiPokokRaw
                : ($baseSalary + (($mkgTahun / 2) * $stepSalary));

            $estimasiGajiBaru = ($statusAktif === 'Pensiun')
                ? null
                : ($gajiPokok + $stepSalary);

            $payload = [
                'uptd_id' => $uptd->id,
                'nama' => $nama,
                'nik' => $nik,
                'no_kk' => $noKk ?: null,
                'tanggal_lahir' => $tanggalLahir,
                'umur' => $umur,
                'status_kepegawaian' => $kepegawaian,
                'status_aktif' => $statusAktif,
                'golongan' => $golongan,
                'mkg_tahun' => $mkgTahun,
                'mkg_bulan' => $mkgBulan,
                'tmt_kgb_terakhir' => $tmtKgbTerakhir,
                'tmt_kgb_berikutnya' => $tmtKgbBerikutnya,
                'gaji_pokok_terakhir' => $gajiPokok,
                'estimasi_gaji_baru' => $estimasiGajiBaru,
                'provinsi' => $provinsiRaw ?: 'SULAWESI SELATAN',
                'kabupaten_kota' => $kabKota ?: null,
                'kecamatan' => $kecamatan ?: null,
                'kelurahan' => $kelurahan ?: null,
                'alamat_domisili' => $alamatDomisili ?: null,
                'no_hp' => $noHp ?: null,
                'alamat_ktp' => $alamatKtp ?: null,
            ];

            $existing = Pegawai::where('nik', $nik)->first();
            if ($existing) {
                $existing->update($payload);
                $updated++;
            } else {
                Pegawai::create($payload);
                $imported++;
            }
        }

        $this->command->info("  Sheet Tabular: {$imported} pegawai baru diimport, {$updated} diperbarui, {$skipped} dilewati.");
    }

    /**
     * Process Sheet 1 (DATA PEGAWAI PPPK) from legacy Dinas file format.
     */
    private function processSheet1($sheet, array &$uptdMap): void
    {
        $maxRow = $sheet->getHighestRow();
        $currentUptd = null;
        $imported = 0;

        for ($row = 3; $row <= $maxRow; $row++) {
            $colB = trim((string) $sheet->getCell("B{$row}")->getValue());
            $nik = trim((string) $sheet->getCell("D{$row}")->getValue());

            if (empty($colB) && empty($nik)) {
                continue;
            }

            // Check if this is an UPTD section header
            if (empty($nik) && $this->isUptdHeader($colB)) {
                $foundUptd = $this->resolveUptd($colB, $uptdMap);
                if ($foundUptd) {
                    $currentUptd = $foundUptd;
                    $this->command->info("  > UPTD terdeteksi (row {$row}): {$currentUptd->nama_uptd}");
                }

                continue;
            }

            if (empty($nik) || empty($colB) || ! $currentUptd) {
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

            $statusAktif = 'Aktif';
            if (strtolower($statusPns) === 'pensiun' || ($umur !== null && $umur >= 58)) {
                $statusAktif = 'Pensiun';
            }

            $kepegawaian = (strtoupper($statusPegawai) === 'PPPK' || strtoupper($statusPegawai) === 'P3K') ? 'PPPK' : 'PNS';

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
                'golongan' => $kepegawaian === 'PNS' ? 'III/a' : 'IX',
                'mkg_tahun' => 0,
                'mkg_bulan' => 0,
                'tmt_kgb_terakhir' => '2024-06-01',
                'tmt_kgb_berikutnya' => '2026-06-01',
                'gaji_pokok_terakhir' => 3500000,
                'estimasi_gaji_baru' => 3650000,
                'provinsi' => $provinsi ?: 'SULAWESI SELATAN',
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
     * Process Sheet 2 (DATA PEGAWAI PNS) from legacy Dinas file format.
     */
    private function processSheet2($sheet, array &$uptdMap): void
    {
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

            if (empty($nik) && $this->isUptdHeader($colB)) {
                $foundUptd = $this->resolveUptd($colB, $uptdMap);
                if ($foundUptd) {
                    $currentUptd = $foundUptd;
                }

                continue;
            }

            if (empty($nik) || empty($colB) || ! $currentUptd) {
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

            $kepegawaian = (strtoupper($statusPegawai) === 'PPPK' || strtoupper($statusPegawai) === 'P3K') ? 'PPPK' : 'PNS';

            $existing = Pegawai::where('nik', $nik)->first();
            if ($existing) {
                if (! empty($noKk) && empty($existing->no_kk)) {
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
                'golongan' => $kepegawaian === 'PNS' ? 'III/a' : 'IX',
                'mkg_tahun' => 0,
                'mkg_bulan' => 0,
                'tmt_kgb_terakhir' => '2024-06-01',
                'tmt_kgb_berikutnya' => '2026-06-01',
                'gaji_pokok_terakhir' => 3500000,
                'estimasi_gaji_baru' => 3650000,
                'provinsi' => $provinsi ?: 'SULAWESI SELATAN',
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

    /**
     * Resolve UPTD by raw string or keyword matching.
     */
    private function resolveUptd(?string $rawName, array &$uptdMap): ?Uptd
    {
        if (empty($rawName)) {
            return null;
        }

        $rawUpper = strtoupper(trim($rawName));

        // Exact match
        if (isset($uptdMap[$rawUpper])) {
            return $uptdMap[$rawUpper];
        }

        // Substring match in keys
        foreach ($uptdMap as $name => $uptd) {
            if (str_contains($rawUpper, $name) || str_contains($name, $rawUpper)) {
                return $uptd;
            }
        }

        // Keyword matches
        if (str_contains($rawUpper, 'WALANAE') || str_contains($rawUpper, 'CENRANAE') || str_contains($rawUpper, 'WALCEN')) {
            return $uptdMap['UPTD WS. WALANAE CENRANAE'] ?? null;
        }
        if (str_contains($rawUpper, 'POMPENGAN') || str_contains($rawUpper, 'LARONA') || str_contains($rawUpper, 'POMP')) {
            return $uptdMap['UPTD W. POMPENGAN LARONA'] ?? null;
        }
        if (str_contains($rawUpper, 'SADDANG')) {
            return $uptdMap['UPTD WS. SADDANG'] ?? null;
        }
        if (str_contains($rawUpper, 'JENEBERANG') || str_contains($rawUpper, 'JENEB')) {
            return $uptdMap['UPTD WS. JENEBERANG'] ?? null;
        }
        if (str_contains($rawUpper, 'CPI')) {
            return $uptdMap['UPTD KAWASAN CPI'] ?? null;
        }
        if (str_contains($rawUpper, 'DINAS') || str_contains($rawUpper, 'PUTR') || str_contains($rawUpper, 'SUMBER DAYA AIR')) {
            return $uptdMap['DINAS SUMBER DAYA AIR, CIPTA KARYA & TATA RUANG'] ?? null;
        }

        // Dynamically create if unrecognized
        $uptd = Uptd::firstOrCreate(
            ['nama_uptd' => $rawUpper],
            ['kode' => substr($rawUpper, 0, 4)]
        );
        $uptdMap[$rawUpper] = $uptd;

        return $uptd;
    }

    private function parseDate($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        if (is_numeric($value)) {
            try {
                $date = Date::excelToDateTimeObject((int) $value);

                return $date->format('Y-m-d');
            } catch (\Exception $e) {
                return null;
            }
        }

        if (is_string($value)) {
            try {
                return Carbon::parse(trim($value))->format('Y-m-d');
            } catch (\Exception $e) {
                return null;
            }
        }

        return null;
    }
}
