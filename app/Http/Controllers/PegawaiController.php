<?php

namespace App\Http\Controllers;

use App\Models\Pegawai;
use App\Models\Uptd;
use Carbon\Carbon;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PegawaiController extends Controller
{
    private array $golonganPns = [
        'I/a', 'I/b', 'I/c', 'I/d',
        'II/a', 'II/b', 'II/c', 'II/d',
        'III/a', 'III/b', 'III/c', 'III/d',
        'IV/a', 'IV/b', 'IV/c', 'IV/d', 'IV/e',
    ];

    private array $golonganPppk = [
        'I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII',
        'IX', 'X', 'XI', 'XII', 'XIII', 'XIV', 'XV', 'XVI', 'XVII',
    ];

    public function index(Request $request)
    {
        $query = Pegawai::with('uptd');

        // Search by name or NIK
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('nama', 'like', "%{$search}%")
                    ->orWhere('nik', 'like', "%{$search}%");
            });
        }

        // Filter by status kepegawaian
        if ($request->filled('status_kepegawaian')) {
            $query->where('status_kepegawaian', $request->status_kepegawaian);
        }

        // Filter by status aktif
        if ($request->filled('status_aktif')) {
            $query->where('status_aktif', $request->status_aktif);
        }

        // Filter by UPTD
        if ($request->filled('uptd_id')) {
            $query->where('uptd_id', $request->uptd_id);
        }

        // Filter by kabupaten/kota
        if ($request->filled('kabupaten')) {
            $query->where('kabupaten_kota', $request->kabupaten);
        }

        $pegawai = $query->orderBy('nama')->paginate(15)->withQueryString();

        $uptdList = Uptd::orderBy('nama_uptd')->get();
        $kabupatenList = Pegawai::select('kabupaten_kota')
            ->whereNotNull('kabupaten_kota')
            ->where('kabupaten_kota', '!=', '')
            ->distinct()
            ->orderBy('kabupaten_kota')
            ->pluck('kabupaten_kota');

        $totalFiltered = $query->count();

        return view('pegawai.index', compact(
            'pegawai',
            'uptdList',
            'kabupatenList',
            'totalFiltered'
        ));
    }

    public function show(Pegawai $pegawai)
    {
        $pegawai->load('uptd');

        return view('pegawai.show', compact('pegawai'));
    }

    /**
     * Show the form for creating a new employee.
     */
    public function create()
    {
        $uptdList = Uptd::orderBy('nama_uptd')->get();
        $golonganPns = $this->golonganPns;
        $golonganPppk = $this->golonganPppk;

        return view('pegawai.create', compact('uptdList', 'golonganPns', 'golonganPppk'));
    }

    /**
     * Store a newly created employee in storage.
     */
    public function store(Request $request)
    {
        // If 'nip' input was sent instead of 'nik', merge into 'nik'
        if ($request->filled('nip') && ! $request->filled('nik')) {
            $request->merge(['nik' => $request->nip]);
        }

        $validated = $request->validate([
            'nama' => 'required|string|max:255',
            'nik' => 'required|string|max:30|unique:pegawai,nik',
            'no_kk' => 'nullable|string|max:30',
            'tanggal_lahir' => 'nullable|date',
            'status_kepegawaian' => 'required|in:PNS,PPPK,ASN,PPPK PW',
            'status_aktif' => 'nullable|in:Aktif,Pensiun',
            'uptd_id' => 'required|exists:uptd,id',
            'golongan' => 'nullable|string|max:20',
            'mkg_tahun' => 'nullable|integer|min:0|max:50',
            'mkg_bulan' => 'nullable|integer|min:0|max:11',
            'tmt_kgb_terakhir' => 'nullable|date',
            'tmt_kgb_berikutnya' => 'nullable|date',
            'gaji_pokok_terakhir' => 'nullable|numeric|min:0',
            'estimasi_gaji_baru' => 'nullable|numeric|min:0',
            'provinsi' => 'nullable|string|max:100',
            'kabupaten_kota' => 'nullable|string|max:100',
            'kecamatan' => 'nullable|string|max:100',
            'kelurahan' => 'nullable|string|max:100',
            'alamat_domisili' => 'nullable|string|max:500',
            'no_hp' => 'nullable|string|max:30',
            'alamat_ktp' => 'nullable|string|max:500',
        ], [
            'nama.required' => 'Nama lengkap pegawai wajib diisi.',
            'nik.required' => 'NIP (atau NIK) wajib diisi.',
            'nik.unique' => 'NIP/NIK ini sudah terdaftar dalam sistem.',
            'status_kepegawaian.required' => 'Status kepegawaian (PNS/PPPK) wajib dipilih.',
            'uptd_id.required' => 'Unit Kerja (UPTD) wajib dipilih.',
        ]);

        // Calculate age and retirement status
        $umur = null;
        if (! empty($validated['tanggal_lahir'])) {
            $umur = Carbon::parse($validated['tanggal_lahir'])->age;
        }

        // Retirement age cutoff 58 years
        $statusAktif = $validated['status_aktif'] ?? 'Aktif';
        if ($umur !== null && $umur >= 58) {
            $statusAktif = 'Pensiun';
        }

        // Calculate next KGB date (+2 years) if not provided
        $tmtKgbBerikutnya = $validated['tmt_kgb_berikutnya'] ?? null;
        if (! empty($validated['tmt_kgb_terakhir']) && empty($tmtKgbBerikutnya)) {
            $tmtKgbBerikutnya = Carbon::parse($validated['tmt_kgb_terakhir'])->addYears(2)->toDateString();
        }

        // Calculate estimated new salary if not provided
        $gajiPokok = $validated['gaji_pokok_terakhir'] ?? null;
        $estimasiGajiBaru = $validated['estimasi_gaji_baru'] ?? null;
        if ($gajiPokok && empty($estimasiGajiBaru)) {
            $estimasiGajiBaru = $this->calculateSalaryBump((float) $gajiPokok, $validated['status_kepegawaian']);
        }

        $pegawai = Pegawai::create([
            'uptd_id' => $validated['uptd_id'],
            'nama' => $validated['nama'],
            'nik' => $validated['nik'],
            'no_kk' => $validated['no_kk'] ?? null,
            'tanggal_lahir' => $validated['tanggal_lahir'] ?? null,
            'umur' => $umur,
            'status_kepegawaian' => $validated['status_kepegawaian'],
            'status_aktif' => $statusAktif,
            'golongan' => $validated['golongan'] ?? null,
            'mkg_tahun' => $validated['mkg_tahun'] ?? 0,
            'mkg_bulan' => $validated['mkg_bulan'] ?? 0,
            'tmt_kgb_terakhir' => $validated['tmt_kgb_terakhir'] ?? null,
            'tmt_kgb_berikutnya' => $tmtKgbBerikutnya,
            'gaji_pokok_terakhir' => $gajiPokok,
            'estimasi_gaji_baru' => $estimasiGajiBaru,
            'provinsi' => $validated['provinsi'] ?? 'SULAWESI SELATAN',
            'kabupaten_kota' => $validated['kabupaten_kota'] ?? null,
            'kecamatan' => $validated['kecamatan'] ?? null,
            'kelurahan' => $validated['kelurahan'] ?? null,
            'alamat_domisili' => $validated['alamat_domisili'] ?? null,
            'no_hp' => $validated['no_hp'] ?? null,
            'alamat_ktp' => $validated['alamat_ktp'] ?? null,
        ]);

        // Update UPTD counter
        $this->updateUptdCounts($validated['uptd_id']);

        return redirect()->route('pegawai.show', $pegawai)
            ->with('success', "Pegawai atas nama <strong>{$pegawai->nama}</strong> berhasil ditambahkan!");
    }

    /**
     * Show the form for editing the specified employee.
     */
    public function edit(Pegawai $pegawai)
    {
        $uptdList = Uptd::orderBy('nama_uptd')->get();
        $golonganPns = $this->golonganPns;
        $golonganPppk = $this->golonganPppk;

        return view('pegawai.edit', compact('pegawai', 'uptdList', 'golonganPns', 'golonganPppk'));
    }

    /**
     * Update the specified employee in storage.
     */
    public function update(Request $request, Pegawai $pegawai)
    {
        if ($request->filled('nip') && !$request->filled('nik')) {
            $request->merge(['nik' => $request->nip]);
        }

        $validated = $request->validate([
            'nama' => 'required|string|max:255',
            'nik' => 'required|string|max:30|unique:pegawai,nik,' . $pegawai->id,
            'no_kk' => 'nullable|string|max:30',
            'tanggal_lahir' => 'nullable|date',
            'status_kepegawaian' => 'required|in:PNS,PPPK,ASN,PPPK PW',
            'status_aktif' => 'nullable|in:Aktif,Pensiun',
            'uptd_id' => 'required|exists:uptd,id',
            'golongan' => 'nullable|string|max:20',
            'mkg_tahun' => 'nullable|integer|min:0|max:50',
            'mkg_bulan' => 'nullable|integer|min:0|max:11',
            'tmt_kgb_terakhir' => 'nullable|date',
            'tmt_kgb_berikutnya' => 'nullable|date',
            'gaji_pokok_terakhir' => 'nullable|numeric|min:0',
            'estimasi_gaji_baru' => 'nullable|numeric|min:0',
            'provinsi' => 'nullable|string|max:100',
            'kabupaten_kota' => 'nullable|string|max:100',
            'kecamatan' => 'nullable|string|max:100',
            'kelurahan' => 'nullable|string|max:100',
            'alamat_domisili' => 'nullable|string|max:500',
            'no_hp' => 'nullable|string|max:30',
            'alamat_ktp' => 'nullable|string|max:500',
        ], [
            'nama.required' => 'Nama lengkap pegawai wajib diisi.',
            'nik.required' => 'NIP (atau NIK) wajib diisi.',
            'nik.unique' => 'NIP/NIK ini sudah digunakan oleh pegawai lain.',
            'status_kepegawaian.required' => 'Status kepegawaian (PNS/PPPK) wajib dipilih.',
            'uptd_id.required' => 'Unit Kerja (UPTD) wajib dipilih.',
        ]);

        $oldUptdId = $pegawai->uptd_id;

        // Calculate age and retirement status
        $umur = null;
        if (!empty($validated['tanggal_lahir'])) {
            $umur = Carbon::parse($validated['tanggal_lahir'])->age;
        }

        $statusAktif = $validated['status_aktif'] ?? 'Aktif';
        if ($umur !== null && $umur >= 58) {
            $statusAktif = 'Pensiun';
        }

        // Calculate next KGB date (+2 years) if not provided
        $tmtKgbBerikutnya = $validated['tmt_kgb_berikutnya'] ?? null;
        if (!empty($validated['tmt_kgb_terakhir']) && empty($tmtKgbBerikutnya)) {
            $tmtKgbBerikutnya = Carbon::parse($validated['tmt_kgb_terakhir'])->addYears(2)->toDateString();
        }

        // Calculate estimated new salary if not provided
        $gajiPokok = $validated['gaji_pokok_terakhir'] ?? null;
        $estimasiGajiBaru = $validated['estimasi_gaji_baru'] ?? null;
        if ($gajiPokok && empty($estimasiGajiBaru)) {
            $estimasiGajiBaru = $this->calculateSalaryBump((float) $gajiPokok, $validated['status_kepegawaian']);
        }

        $pegawai->update([
            'uptd_id' => $validated['uptd_id'],
            'nama' => $validated['nama'],
            'nik' => $validated['nik'],
            'no_kk' => $validated['no_kk'] ?? null,
            'tanggal_lahir' => $validated['tanggal_lahir'] ?? null,
            'umur' => $umur,
            'status_kepegawaian' => $validated['status_kepegawaian'],
            'status_aktif' => $statusAktif,
            'golongan' => $validated['golongan'] ?? null,
            'mkg_tahun' => $validated['mkg_tahun'] ?? 0,
            'mkg_bulan' => $validated['mkg_bulan'] ?? 0,
            'tmt_kgb_terakhir' => $validated['tmt_kgb_terakhir'] ?? null,
            'tmt_kgb_berikutnya' => $tmtKgbBerikutnya,
            'gaji_pokok_terakhir' => $gajiPokok,
            'estimasi_gaji_baru' => $estimasiGajiBaru,
            'provinsi' => $validated['provinsi'] ?? 'SULAWESI SELATAN',
            'kabupaten_kota' => $validated['kabupaten_kota'] ?? null,
            'kecamatan' => $validated['kecamatan'] ?? null,
            'kelurahan' => $validated['kelurahan'] ?? null,
            'alamat_domisili' => $validated['alamat_domisili'] ?? null,
            'no_hp' => $validated['no_hp'] ?? null,
            'alamat_ktp' => $validated['alamat_ktp'] ?? null,
        ]);

        // Update UPTD counter for old and new UPTD
        $this->updateUptdCounts($oldUptdId);
        if ($oldUptdId != $validated['uptd_id']) {
            $this->updateUptdCounts($validated['uptd_id']);
        }

        return redirect()->route('pegawai.show', $pegawai)
            ->with('success', "Data pegawai <strong>{$pegawai->nama}</strong> berhasil diperbarui!");
    }

    /**
     * Remove the specified employee from storage.
     */
    public function destroy(Pegawai $pegawai)
    {
        $nama = $pegawai->nama;
        $uptdId = $pegawai->uptd_id;

        $pegawai->delete();

        $this->updateUptdCounts($uptdId);

        return redirect()->route('pegawai.index')
            ->with('success', "Data pegawai <strong>{$nama}</strong> berhasil dihapus.");
    }

    /**
     * Show the Excel import view.
     */
    public function importForm()
    {
        $uptdList = Uptd::orderBy('nama_uptd')->get();

        return view('pegawai.import', compact('uptdList'));
    }

    /**
     * Download Excel template.
     */
    public function downloadTemplate()
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Template Import Pegawai');

        // Headers
        // Kolom B untuk NIP (18 digit), Kolom C untuk NIK (16 digit).
        // Jika kolom NIP kosong/strip, sistem otomatis memakai data dari kolom NIK.
        $headers = [
            'A1' => 'NAMA',
            'B1' => 'NIP',
            'C1' => 'NIK',
            'D1' => 'NO_KK',
            'E1' => 'TANGGAL_LAHIR',
            'F1' => 'STATUS_KEPEGAWAIAN',
            'G1' => 'UNIT_KERJA',
            'H1' => 'GOLONGAN',
            'I1' => 'MKG_TAHUN',
            'J1' => 'MKG_BULAN',
            'K1' => 'TMT_KGB_TERAKHIR',
            'L1' => 'GAJI_POKOK_TERAKHIR',
            'M1' => 'PROVINSI',
            'N1' => 'KABUPATEN_KOTA',
            'O1' => 'KECAMATAN',
            'P1' => 'KELURAHAN',
            'Q1' => 'ALAMAT_DOMISILI',
            'R1' => 'NO_HP',
            'S1' => 'ALAMAT_KTP',
        ];

        foreach ($headers as $cell => $val) {
            $sheet->setCellValue($cell, $val);
        }

        // Header Styling
        $headerRange = 'A1:S1';
        $sheet->getStyle($headerRange)->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
                'size' => 11,
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '543C34'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(28);

        // Sample Data Rows:
        // Baris 1: Contoh PNS dengan NIP & NIK lengkap
        // Baris 2: Contoh pegawai belum ada NIP (NIP kosong, otomatis fallback ke NIK)
        // Baris 3: Contoh PPPK dengan NIP PPPK & NIK
        $sampleData = [
            [
                'AHMAD RASYID, S.T.', '198208152005021003', '7371011508820001', '7371011508000001',
                '1982-08-15', 'PNS', 'UPTD WS. JENEBERANG', 'III/c',
                12, 0, '2024-06-01', 3850000,
                'SULAWESI SELATAN', 'MAKASSAR', 'PANAKKUKANG', 'PAMPANG',
                'Jl. Urip Sumoharjo No. 12', '081234567890', 'Jl. Urip Sumoharjo No. 12',
            ],
            [
                'NURHALISA, S.Sos.', '', '7371022204900002', '7371022204000002',
                '1990-04-22', 'PPPK', 'UPTD W. POMPENGAN LARONA', 'IX',
                4, 0, '2024-10-01', 3200000,
                'SULAWESI SELATAN', 'KOTA PALOPO', 'WARA', 'BOMBON',
                'Jl. Dr. Ratulangi No. 45', '085299887766', 'Jl. Dr. Ratulangi No. 45',
            ],
            [
                'MUHAMMAD ILHAM, S.Pd.', '199506122023211004', '7371031206950003', '7371031206000003',
                '1995-06-12', 'PPPK', 'UPTD WS. CENA RANA', 'IX',
                2, 0, '2025-03-01', 3200000,
                'SULAWESI SELATAN', 'BONE', 'TANETE RIATTANG', 'BIRU',
                'Jl. Merdeka No. 8', '081344556677', 'Jl. Merdeka No. 8',
            ],
        ];

        $rowIdx = 2;
        foreach ($sampleData as $row) {
            $colLetter = 'A';
            foreach ($row as $val) {
                $cellCoord = "{$colLetter}{$rowIdx}";
                // Simpan NIP, NIK, KK, HP sebagai teks eksplisit agar tidak berubah jadi notasi ilmiah di Excel
                if (in_array($colLetter, ['B', 'C', 'D', 'R'], true)) {
                    $sheet->setCellValueExplicit($cellCoord, (string) $val, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                } else {
                    $sheet->setCellValue($cellCoord, $val);
                }
                $colLetter++;
            }
            $rowIdx++;
        }

        // Auto-fit columns
        foreach (range('A', 'S') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $writer = new Xlsx($spreadsheet);
        $response = new StreamedResponse(function () use ($writer) {
            $writer->save('php://output');
        });

        $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $response->headers->set('Content-Disposition', 'attachment;filename="Template_Import_Pegawai_SIMPEG_PU_Sulsel.xlsx"');
        $response->headers->set('Cache-Control', 'max-age=0');

        return $response;
    }

    /**
     * Process Excel file upload and import employees.
     */
    public function importExcel(Request $request)
    {
        $request->validate([
            'excel_file' => 'required|file|mimes:xlsx,xls,csv|max:15360',
        ], [
            'excel_file.required' => 'File Excel wajib diunggah.',
            'excel_file.mimes' => 'Format file harus berupa .xlsx, .xls, atau .csv.',
            'excel_file.max' => 'Ukuran file maksimal adalah 15 MB.',
        ]);

        $updateExisting = $request->boolean('update_existing');
        $file = $request->file('excel_file');

        try {
            $spreadsheet = IOFactory::load($file->getRealPath());
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Gagal membaca berkas Excel: '.$e->getMessage());
        }

        // Cache UPTD records
        $allUptds = Uptd::all();
        $uptdMap = [];
        foreach ($allUptds as $u) {
            $uptdMap[strtoupper(trim($u->nama_uptd))] = $u;
        }

        $imported = 0;
        $updated = 0;
        $skipped = 0;
        $fallbackCount = 0;
        $errors = [];

        // Determine if file is the multi-sheet official format or tabular format
        $sheetPppk = $spreadsheet->getSheetByName('DATA PEGAWAI PPPK');
        $sheetPns = $spreadsheet->getSheetByName('DATA PEGAWAI PNS ');

        if ($sheetPppk || $sheetPns) {
            // Process as official Dinas multi-sheet format
            if ($sheetPppk) {
                $this->processDinasSheet1($sheetPppk, $uptdMap, $updateExisting, $imported, $updated, $skipped, $fallbackCount);
            }
            if ($sheetPns) {
                $this->processDinasSheet2($sheetPns, $uptdMap, $updateExisting, $imported, $updated, $skipped, $fallbackCount);
            }
        } else {
            // Process as standard tabular template (Sheet 1)
            $sheet = $spreadsheet->getActiveSheet();
            $this->processTabularSheet($sheet, $uptdMap, $updateExisting, $imported, $updated, $skipped, $errors, $fallbackCount);
        }

        // Update counts for all UPTD
        foreach (Uptd::all() as $u) {
            $this->updateUptdCounts($u->id);
        }

        $message = "Import selesai! <strong>{$imported}</strong> data pegawai berhasil ditambahkan";
        if ($updated > 0) {
            $message .= ", <strong>{$updated}</strong> data diperbarui";
        }
        if ($fallbackCount > 0) {
            $message .= " (Termasuk <strong>{$fallbackCount}</strong> data yang NIP-nya otomatis terisi dari NIK)";
        }
        if ($skipped > 0) {
            $message .= ", <strong>{$skipped}</strong> data dilewati (NIP & NIK kosong / duplikat)";
        }
        $message .= '.';

        if (! empty($errors)) {
            return redirect()->route('pegawai.index')->with('info', $message.' (Terdapat catatan: '.implode(', ', array_slice($errors, 0, 3)).')');
        }

        return redirect()->route('pegawai.index')->with('success', $message);
    }

    /**
     * Safely extract raw value from a cell, converting RichText to string.
     */
    private function getCleanCellValue($cell): mixed
    {
        if (! $cell) {
            return null;
        }
        $val = $cell->getValue();
        if ($val instanceof \PhpOffice\PhpSpreadsheet\RichText\RichText) {
            return (string) $val;
        }

        return $val;
    }

    /**
     * Safely extract cell value as a trimmed string.
     */
    private function getCellString($cell): string
    {
        $val = $this->getCleanCellValue($cell);

        return $val !== null ? trim((string) $val) : '';
    }

    /**
     * Process standard tabular template format (Row 1 = Headers).
     */
    private function processTabularSheet($sheet, array &$uptdMap, bool $updateExisting, int &$imported, int &$updated, int &$skipped, array &$errors, int &$fallbackCount = 0): void
    {
        $highestRow = $sheet->getHighestRow();
        $highestCol = $sheet->getHighestColumn();
        $highestColIndex = Coordinate::columnIndexFromString($highestCol);

        // Read header row
        $headerRow = [];
        for ($col = 1; $col <= $highestColIndex; $col++) {
            $val = $this->getCellString($sheet->getCell([$col, 1]));
            $valUpper = strtoupper(trim($val));
            if ($valUpper !== '') {
                $headerRow[$valUpper] = $col;
            }
            $valUnderscore = strtoupper(str_replace([' ', '/', '-', '.', '(', ')'], '_', trim($val)));
            $valUnderscore = trim($valUnderscore, '_');
            if ($valUnderscore !== '') {
                $headerRow[$valUnderscore] = $col;
            }
        }

        // Helper to get non-empty value by possible header names
        $getVal = function ($row, array $possibleNames) use ($sheet, $headerRow) {
            foreach ($possibleNames as $name) {
                $upper = strtoupper(trim($name));
                $col = $headerRow[$upper] ?? null;
                if (! $col) {
                    $norm = strtoupper(str_replace([' ', '/', '-', '.', '(', ')'], '_', $upper));
                    $norm = trim($norm, '_');
                    $col = $headerRow[$norm] ?? null;
                }

                if ($col !== null) {
                    $val = trim((string) $this->getCleanCellValue($sheet->getCell([$col, $row])));
                    // Skip if value is completely empty or just a dash placeholder
                    if ($val !== '' && $val !== '-') {
                        return $val;
                    }
                }
            }

            return null;
        };

        for ($row = 2; $row <= $highestRow; $row++) {
            $namaVal = $getVal($row, ['NAMA', 'NAMA_PEGAWAI', 'NAMA_LENGKAP']) ?? $this->getCleanCellValue($sheet->getCell("A{$row}"));
            $nama = $namaVal !== null ? trim((string) $namaVal) : '';

            // 1. Ambil nilai NIP khusus
            $nipVal = $getVal($row, ['NIP', 'NOMOR_INDUK_PEGAWAI', 'NIP_BARU']);

            // 2. Ambil nilai NIK khusus (KTP)
            $nikVal = $getVal($row, ['NIK', 'KTP', 'NO_KTP', 'NIK_KTP', 'KTP_NIK', 'NOMOR_INDUK_KEPENDUDUKAN']);

            // 3. Ambil jika ada header gabungan
            $idVal = $getVal($row, ['NIP_NIK', 'NIK_NIP', 'NO_IDENTITAS', 'IDENTITAS', 'ID_PEGAWAI']);

            // Fallback otomatis: Prioritaskan NIP, jika kosong/tidak ada gunakan NIK
            $nik = null;
            $usedFallback = false;

            if (! empty($nipVal) && $nipVal !== '-') {
                $nik = $nipVal;
            } elseif (! empty($nikVal) && $nikVal !== '-') {
                $nik = $nikVal;
                $usedFallback = true;
            } elseif (! empty($idVal) && $idVal !== '-') {
                $nik = $idVal;
            } else {
                // Fallback berdasarkan letak kolom fisik jika file tanpa header standar
                $cellB = trim((string) $this->getCleanCellValue($sheet->getCell("B{$row}")));
                $cellC = trim((string) $this->getCleanCellValue($sheet->getCell("C{$row}")));

                if ($cellB !== '' && $cellB !== '-') {
                    $nik = $cellB;
                } elseif ($cellC !== '' && $cellC !== '-' && is_numeric(str_replace([' ', '-'], '', $cellC))) {
                    $nik = $cellC;
                    $usedFallback = true;
                }
            }

            $nik = $nik !== null ? trim((string) $nik) : '';

            if (empty($nama) && empty($nik)) {
                continue;
            }

            if (empty($nama) || empty($nik)) {
                $skipped++;

                continue;
            }

            if ($usedFallback) {
                $fallbackCount++;
            }

            $noKkVal = $getVal($row, ['NO_KK', 'KK', 'KARTU_KELUARGA', 'NO_KARTU_KELUARGA']);
            if ($noKkVal === null) {
                if (isset($headerRow['NIK'])) {
                    $noKkVal = $this->getCleanCellValue($sheet->getCell("D{$row}"));
                } else {
                    $noKkVal = $this->getCleanCellValue($sheet->getCell("C{$row}"));
                }
            }
            $noKk = $noKkVal !== null ? trim((string) $noKkVal) : '';

            $tglLahirRaw = $getVal($row, ['TANGGAL_LAHIR', 'TGL_LAHIR', 'TGL_LHR']) ?? $this->getCleanCellValue($sheet->getCell("E{$row}"));

            $statusPegawaiVal = $getVal($row, ['STATUS_KEPEGAWAIAN', 'STATUS', 'JENIS_PEGAWAI', 'STATUS_PEGAWAI']) ?? $this->getCleanCellValue($sheet->getCell("F{$row}"));
            $statusPegawaiRaw = $statusPegawaiVal !== null ? trim((string) $statusPegawaiVal) : '';

            $uptdVal = $getVal($row, ['UNIT_KERJA', 'UPTD', 'NAMA_UPTD', 'SKPD']) ?? $this->getCleanCellValue($sheet->getCell("G{$row}"));
            $uptdRaw = $uptdVal !== null ? trim((string) $uptdVal) : '';

            $golonganVal = $getVal($row, ['GOLONGAN', 'GOL', 'PANGKAT', 'GOL_RUANG']) ?? $this->getCleanCellValue($sheet->getCell("H{$row}"));
            $golongan = $golonganVal !== null ? trim((string) $golonganVal) : '';

            $mkgTahunRaw = $getVal($row, ['MKG_TAHUN', 'MKG_THN', 'MKG', 'MASA_KERJA_TAHUN']) ?? $this->getCleanCellValue($sheet->getCell("I{$row}"));
            $mkgTahun = is_numeric($mkgTahunRaw) ? (int) $mkgTahunRaw : 0;

            $mkgBulanRaw = $getVal($row, ['MKG_BULAN', 'MKG_BLN', 'MASA_KERJA_BULAN']) ?? $this->getCleanCellValue($sheet->getCell("J{$row}"));
            $mkgBulan = is_numeric($mkgBulanRaw) ? (int) $mkgBulanRaw : 0;

            $tmtKgbRaw = $getVal($row, ['TMT_KGB_TERAKHIR', 'TMT_KGB', 'TMT_TERAKHIR', 'TMT_KGB_LALU']) ?? $this->getCleanCellValue($sheet->getCell("K{$row}"));

            $gajiPokokRaw = $getVal($row, ['GAJI_POKOK_TERAKHIR', 'GAJI_POKOK', 'GAJI', 'GAPOK']) ?? $this->getCleanCellValue($sheet->getCell("L{$row}"));

            $provinsiVal = $getVal($row, ['PROVINSI', 'PROV']) ?? $this->getCleanCellValue($sheet->getCell("M{$row}"));
            $provinsi = $provinsiVal !== null ? trim((string) $provinsiVal) : '';

            $kabKotaVal = $getVal($row, ['KABUPATEN_KOTA', 'KABUPATEN', 'KOTA', 'KAB_KOTA']) ?? $this->getCleanCellValue($sheet->getCell("N{$row}"));
            $kabKota = $kabKotaVal !== null ? trim((string) $kabKotaVal) : '';

            $kecamatanVal = $getVal($row, ['KECAMATAN', 'KEC']) ?? $this->getCleanCellValue($sheet->getCell("O{$row}"));
            $kecamatan = $kecamatanVal !== null ? trim((string) $kecamatanVal) : '';

            $kelurahanVal = $getVal($row, ['KELURAHAN', 'KEL', 'DESA']) ?? $this->getCleanCellValue($sheet->getCell("P{$row}"));
            $kelurahan = $kelurahanVal !== null ? trim((string) $kelurahanVal) : '';

            $alamatDomisiliVal = $getVal($row, ['ALAMAT_DOMISILI', 'ALAMAT', 'DOMISILI']) ?? $this->getCleanCellValue($sheet->getCell("Q{$row}"));
            $alamatDomisili = $alamatDomisiliVal !== null ? trim((string) $alamatDomisiliVal) : '';

            $noHpVal = $getVal($row, ['NO_HP', 'HP', 'TELEPON', 'NO_TELP', 'NO_HANDPHONE']) ?? $this->getCleanCellValue($sheet->getCell("R{$row}"));
            $noHp = $noHpVal !== null ? trim((string) $noHpVal) : '';

            $alamatKtpVal = $getVal($row, ['ALAMAT_KTP', 'ALAMAT_KTP_NON_SULSEL', 'KTP_ALAMAT']) ?? $this->getCleanCellValue($sheet->getCell("S{$row}"));
            $alamatKtp = $alamatKtpVal !== null ? trim((string) $alamatKtpVal) : '';

            // Parse status kepegawaian
            $kepegawaian = 'PNS';
            if (str_contains(strtoupper($statusPegawaiRaw), 'PPPK') || str_contains(strtoupper($statusPegawaiRaw), 'P3K')) {
                $kepegawaian = 'PPPK';
            }

            // Parse date of birth & age
            $tanggalLahir = $this->parseExcelDate($tglLahirRaw);
            $umur = $tanggalLahir ? Carbon::parse($tanggalLahir)->age : null;

            // Retirement age 58
            $statusAktif = 'Aktif';
            if ($umur !== null && $umur >= 58) {
                $statusAktif = 'Pensiun';
            }

            // Match UPTD
            $uptd = $this->resolveUptd($uptdRaw, $uptdMap);
            if (! $uptd) {
                $uptd = Uptd::first();
            }

            // Parse KGB Dates
            $tmtKgbTerakhir = $this->parseExcelDate($tmtKgbRaw);
            $tmtKgbBerikutnya = null;
            if ($tmtKgbTerakhir) {
                $tmtKgbBerikutnya = Carbon::parse($tmtKgbTerakhir)->addYears(2)->toDateString();
            }

            $gajiPokok = is_numeric($gajiPokokRaw) ? (float) $gajiPokokRaw : null;
            $estimasiGajiBaru = $gajiPokok ? $this->calculateSalaryBump($gajiPokok, $kepegawaian) : null;

            // Check existing by NIP/NIK
            $existing = Pegawai::where('nik', $nik)->first();
            if (! $existing && ! empty($nikVal) && $nikVal !== $nik) {
                $existing = Pegawai::where('nik', $nikVal)->first();
            }

            if ($existing) {
                if ($updateExisting) {
                    $existing->update([
                        'nama' => $nama,
                        'nik' => $nik,
                        'no_kk' => $noKk ?: $existing->no_kk,
                        'tanggal_lahir' => $tanggalLahir ?: $existing->tanggal_lahir,
                        'umur' => $umur ?: $existing->umur,
                        'status_kepegawaian' => $kepegawaian,
                        'status_aktif' => $statusAktif,
                        'uptd_id' => $uptd->id,
                        'golongan' => $golongan ?: $existing->golongan,
                        'mkg_tahun' => $mkgTahun ?: $existing->mkg_tahun,
                        'mkg_bulan' => $mkgBulan ?: $existing->mkg_bulan,
                        'tmt_kgb_terakhir' => $tmtKgbTerakhir ?: $existing->tmt_kgb_terakhir,
                        'tmt_kgb_berikutnya' => $tmtKgbBerikutnya ?: $existing->tmt_kgb_berikutnya,
                        'gaji_pokok_terakhir' => $gajiPokok ?: $existing->gaji_pokok_terakhir,
                        'estimasi_gaji_baru' => $estimasiGajiBaru ?: $existing->estimasi_gaji_baru,
                        'provinsi' => $provinsi ?: $existing->provinsi,
                        'kabupaten_kota' => $kabKota ?: $existing->kabupaten_kota,
                        'kecamatan' => $kecamatan ?: $existing->kecamatan,
                        'kelurahan' => $kelurahan ?: $existing->kelurahan,
                        'alamat_domisili' => $alamatDomisili ?: $existing->alamat_domisili,
                        'no_hp' => $noHp ?: $existing->no_hp,
                        'alamat_ktp' => $alamatKtp ?: $existing->alamat_ktp,
                    ]);
                    $updated++;
                } else {
                    $skipped++;
                }

                continue;
            }

            // Create new
            Pegawai::create([
                'uptd_id' => $uptd->id,
                'nama' => $nama,
                'nik' => $nik,
                'no_kk' => $noKk ?: null,
                'tanggal_lahir' => $tanggalLahir,
                'umur' => $umur,
                'status_kepegawaian' => $kepegawaian,
                'status_aktif' => $statusAktif,
                'golongan' => $golongan ?: ($kepegawaian === 'PNS' ? 'III/a' : 'IX'),
                'mkg_tahun' => $mkgTahun,
                'mkg_bulan' => $mkgBulan,
                'tmt_kgb_terakhir' => $tmtKgbTerakhir ?: '2024-06-01',
                'tmt_kgb_berikutnya' => $tmtKgbBerikutnya ?: '2026-06-01',
                'gaji_pokok_terakhir' => $gajiPokok ?: 3500000,
                'estimasi_gaji_baru' => $estimasiGajiBaru ?: 3650000,
                'provinsi' => $provinsi ?: 'SULAWESI SELATAN',
                'kabupaten_kota' => $kabKota ?: null,
                'kecamatan' => $kecamatan ?: null,
                'kelurahan' => $kelurahan ?: null,
                'alamat_domisili' => $alamatDomisili ?: null,
                'no_hp' => $noHp ?: null,
                'alamat_ktp' => $alamatKtp ?: null,
            ]);

            $imported++;
        }
    }

    /**
     * Process Sheet 1 from Dinas file (DATA PEGAWAI PPPK).
     */
    private function processDinasSheet1($sheet, array &$uptdMap, bool $updateExisting, int &$imported, int &$updated, int &$skipped, int &$fallbackCount = 0): void
    {
        $maxRow = $sheet->getHighestRow();
        $currentUptd = null;

        for ($row = 3; $row <= $maxRow; $row++) {
            $colB = $this->getCellString($sheet->getCell("B{$row}"));
            $colC = $this->getCellString($sheet->getCell("C{$row}"));
            $colD = $this->getCellString($sheet->getCell("D{$row}"));

            $hasNip = (! empty($colC) && $colC !== '-');
            $hasNik = (! empty($colD) && $colD !== '-');
            $nik = $hasNip ? $colC : ($hasNik ? $colD : '');

            if (empty($colB) && empty($nik)) {
                continue;
            }

            if (empty($nik) && str_starts_with(strtoupper($colB), 'UPTD')) {
                $currentUptd = $this->resolveUptd($colB, $uptdMap);

                continue;
            }

            if (empty($nik) || empty($colB) || ! $currentUptd) {
                continue;
            }

            if (! $hasNip && $hasNik) {
                $fallbackCount++;
            }

            $tanggalLahir = $this->parseExcelDate($this->getCleanCellValue($sheet->getCell("E{$row}")));
            $statusPns = $this->getCellString($sheet->getCell("F{$row}"));
            $provinsi = $this->getCellString($sheet->getCell("G{$row}"));
            $kabKota = $this->getCellString($sheet->getCell("H{$row}"));
            $kelurahan = $this->getCellString($sheet->getCell("I{$row}"));
            $kecamatan = $this->getCellString($sheet->getCell("J{$row}"));
            $alamat = $this->getCellString($sheet->getCell("K{$row}"));
            $noHp = $this->getCellString($sheet->getCell("L{$row}"));
            $statusPegawai = $this->getCellString($sheet->getCell("M{$row}"));
            $alamatKtp = $this->getCellString($sheet->getCell("N{$row}"));

            $umur = $tanggalLahir ? Carbon::parse($tanggalLahir)->age : null;
            $statusAktif = (strtolower($statusPns) === 'pensiun' || ($umur !== null && $umur >= 58)) ? 'Pensiun' : 'Aktif';

            $kepegawaian = (strtoupper($statusPegawai) === 'PPPK' || strtoupper($statusPegawai) === 'P3K') ? 'PPPK' : 'PNS';

            $existing = Pegawai::where('nik', $nik)->first();
            if ($existing) {
                if ($updateExisting) {
                    $existing->update([
                        'nama' => $colB,
                        'tanggal_lahir' => $tanggalLahir ?: $existing->tanggal_lahir,
                        'umur' => $umur ?: $existing->umur,
                        'status_kepegawaian' => $kepegawaian,
                        'status_aktif' => $statusAktif,
                        'uptd_id' => $currentUptd->id,
                        'provinsi' => $provinsi ?: $existing->provinsi,
                        'kabupaten_kota' => $kabKota ?: $existing->kabupaten_kota,
                        'kelurahan' => $kelurahan ?: $existing->kelurahan,
                        'kecamatan' => $kecamatan ?: $existing->kecamatan,
                        'alamat_domisili' => $alamat ?: $existing->alamat_domisili,
                        'no_hp' => $noHp ?: $existing->no_hp,
                        'alamat_ktp' => $alamatKtp ?: $existing->alamat_ktp,
                    ]);
                    $updated++;
                } else {
                    $skipped++;
                }

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
    }

    /**
     * Process Sheet 2 from Dinas file (DATA PEGAWAI PNS).
     */
    private function processDinasSheet2($sheet, array &$uptdMap, bool $updateExisting, int &$imported, int &$updated, int &$skipped, int &$fallbackCount = 0): void
    {
        $maxRow = $sheet->getHighestRow();
        $currentUptd = null;

        for ($row = 3; $row <= $maxRow; $row++) {
            $colB = $this->getCellString($sheet->getCell("B{$row}"));
            $colC = $this->getCellString($sheet->getCell("C{$row}"));
            $colD = $this->getCellString($sheet->getCell("D{$row}"));

            $hasNip = (! empty($colC) && $colC !== '-');
            $hasNik = (! empty($colD) && $colD !== '-');
            $nik = $hasNip ? $colC : ($hasNik ? $colD : '');

            if (empty($colB) && empty($nik)) {
                continue;
            }

            if (empty($nik) && str_starts_with(strtoupper($colB), 'UPTD')) {
                $currentUptd = $this->resolveUptd($colB, $uptdMap);

                continue;
            }

            if (empty($nik) || empty($colB) || ! $currentUptd) {
                continue;
            }

            if (! $hasNip && $hasNik) {
                $fallbackCount++;
            }

            $noKk = $this->getCellString($sheet->getCell("E{$row}"));
            $provinsi = $this->getCellString($sheet->getCell("F{$row}"));
            $kabKota = $this->getCellString($sheet->getCell("G{$row}"));
            $kelurahan = $this->getCellString($sheet->getCell("H{$row}"));
            $kecamatan = $this->getCellString($sheet->getCell("I{$row}"));
            $alamat = $this->getCellString($sheet->getCell("J{$row}"));
            $noHp = $this->getCellString($sheet->getCell("K{$row}"));
            $statusPegawai = $this->getCellString($sheet->getCell("L{$row}"));
            $alamatKtp = $this->getCellString($sheet->getCell("M{$row}"));

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
    }

    /**
     * Resolve UPTD by raw name or keyword.
     */
    private function resolveUptd(?string $rawName, array &$uptdMap): ?Uptd
    {
        if (empty($rawName)) {
            return null;
        }

        $rawUpper = strtoupper(trim($rawName));

        if (isset($uptdMap[$rawUpper])) {
            return $uptdMap[$rawUpper];
        }

        foreach ($uptdMap as $name => $uptd) {
            if (str_contains($rawUpper, $name) || str_contains($name, $rawUpper)) {
                return $uptd;
            }
        }

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

        return null;
    }

    /**
     * Helper to parse dates from Excel cells.
     */
    private function parseExcelDate($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        if (is_numeric($value)) {
            try {
                return Date::excelToDateTimeObject((int) $value)->format('Y-m-d');
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

    /**
     * Calculate typical KGB salary bump (~3% or standard step).
     */
    private function calculateSalaryBump(float $currentSalary, string $kepegawaian): float
    {
        $bump = round($currentSalary * 0.028);

        return $currentSalary + $bump;
    }

    /**
     * Update PNS & PPPK counts for an UPTD.
     */
    private function updateUptdCounts(?int $uptdId): void
    {
        if (! $uptdId) {
            return;
        }

        $uptd = Uptd::find($uptdId);
        if ($uptd) {
            $uptd->update([
                'jumlah_pns' => $uptd->pegawai()->whereIn('status_kepegawaian', Pegawai::GROUP_PNS)->count(),
                'jumlah_pppk' => $uptd->pegawai()->whereIn('status_kepegawaian', Pegawai::GROUP_PPPK)->count(),
            ]);
        }
    }
}
