<?php

namespace App\Http\Controllers;

use App\Models\Pegawai;
use App\Models\Uptd;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
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
    public const DEFAULT_UPTDS = [
        ['nama_uptd' => 'UPTD WS. JENEBERANG', 'kode' => 'JNB'],
        ['nama_uptd' => 'UPTD W. POMPENGAN LARONA', 'kode' => 'PL'],
        ['nama_uptd' => 'UPTD WS. SADDANG', 'kode' => 'SDG'],
        ['nama_uptd' => 'UPTD WS. WALANAE CENRANAE', 'kode' => 'WC'],
        ['nama_uptd' => 'UPTD KAWASAN CPI', 'kode' => 'CPI'],
        ['nama_uptd' => 'DINAS SUMBER DAYA AIR, CIPTA KARYA & TATA RUANG', 'kode' => 'DSDA'],
    ];

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

        // Search by name, NIP, or NIK
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('nama', 'like', "%{$search}%")
                    ->orWhere('nip', 'like', "%{$search}%")
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

        $this->ensureDefaultUptds();
        $uptdList = Uptd::orderBy('nama_uptd')->get();
        $kabupatenList = Cache::remember('kabupaten_list_all', 300, function () {
            return Pegawai::select('kabupaten_kota')
                ->whereNotNull('kabupaten_kota')
                ->where('kabupaten_kota', '!=', '')
                ->distinct()
                ->orderBy('kabupaten_kota')
                ->pluck('kabupaten_kota');
        });

        $totalFiltered = $pegawai->total();

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
        $this->ensureDefaultUptds();
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
        $validated = $request->validate([
            'nama' => 'required|string|max:255',
            'nip' => 'nullable|required_without:nik|string|max:30|unique:pegawai,nip',
            'nik' => 'nullable|required_without:nip|string|max:30|unique:pegawai,nik',
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
            'nip.required_without' => 'NIP atau NIK wajib diisi salah satu.',
            'nik.required_without' => 'NIP atau NIK wajib diisi salah satu.',
            'nip.unique' => 'NIP ini sudah terdaftar dalam sistem.',
            'nik.unique' => 'NIK ini sudah terdaftar dalam sistem.',
            'status_kepegawaian.required' => 'Status kepegawaian (PNS/PPPK) wajib dipilih.',
            'uptd_id.required' => 'Unit Kerja (UPTD) wajib dipilih.',
        ]);

        $nip = ! empty($validated['nip']) ? trim($validated['nip']) : null;
        $nik = ! empty($validated['nik']) ? trim($validated['nik']) : null;

        // Tanggal lahir & umur otomatis dari NIP; jika tidak ada NIP (fallback ke NIK), tanggal lahir kosong
        $tanggalLahir = null;
        $umur = null;
        if ($nip) {
            $parsedTgl = Pegawai::tanggalLahirDariNip($nip);
            if ($parsedTgl) {
                $tanggalLahir = $parsedTgl->toDateString();
                $umur = $parsedTgl->age;
            }
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
            'nip' => $nip,
            'nik' => $nik,
            'no_kk' => $validated['no_kk'] ?? null,
            'tanggal_lahir' => $tanggalLahir,
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
        $this->ensureDefaultUptds();
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
        $validated = $request->validate([
            'nama' => 'required|string|max:255',
            'nip' => 'nullable|required_without:nik|string|max:30|unique:pegawai,nip,' . $pegawai->id,
            'nik' => 'nullable|required_without:nip|string|max:30|unique:pegawai,nik,' . $pegawai->id,
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
            'nip.required_without' => 'NIP atau NIK wajib diisi salah satu.',
            'nik.required_without' => 'NIP atau NIK wajib diisi salah satu.',
            'nip.unique' => 'NIP ini sudah digunakan oleh pegawai lain.',
            'nik.unique' => 'NIK ini sudah digunakan oleh pegawai lain.',
            'status_kepegawaian.required' => 'Status kepegawaian (PNS/PPPK) wajib dipilih.',
            'uptd_id.required' => 'Unit Kerja (UPTD) wajib dipilih.',
        ]);

        $oldUptdId = $pegawai->uptd_id;

        $nip = ! empty($validated['nip']) ? trim($validated['nip']) : null;
        $nik = ! empty($validated['nik']) ? trim($validated['nik']) : null;

        // Tanggal lahir & umur otomatis dari NIP; jika tidak ada NIP (fallback ke NIK), tanggal lahir kosong
        $tanggalLahir = null;
        $umur = null;
        if ($nip) {
            $parsedTgl = Pegawai::tanggalLahirDariNip($nip);
            if ($parsedTgl) {
                $tanggalLahir = $parsedTgl->toDateString();
                $umur = $parsedTgl->age;
            }
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
            'nip' => $nip,
            'nik' => $nik,
            'no_kk' => $validated['no_kk'] ?? null,
            'tanggal_lahir' => $tanggalLahir,
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
        $this->ensureDefaultUptds();
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
                '', 'PPPK', 'UPTD W. POMPENGAN LARONA', 'IX',
                4, 0, '2024-10-01', 3200000,
                'SULAWESI SELATAN', 'KOTA PALOPO', 'WARA', 'BOMBON',
                'Jl. Dr. Ratulangi No. 45', '085299887766', 'Jl. Dr. Ratulangi No. 45',
            ],
            [
                'MUHAMMAD ILHAM, S.Pd.', '199506122023211004', '7371031206950003', '7371031206000003',
                '1995-06-12', 'PPPK', 'UPTD WS. WALANAE CENRANAE', 'IX',
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

        $this->ensureDefaultUptds();

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

        // Helper to resolve column index by header names, with fallback if header row has no match
        $findCol = function (array $possibleNames, ?int $defaultCol = null) use ($headerRow) {
            foreach ($possibleNames as $name) {
                $upper = strtoupper(trim($name));
                if (isset($headerRow[$upper])) {
                    return $headerRow[$upper];
                }
                $norm = strtoupper(str_replace([' ', '/', '-', '.', '(', ')'], '_', $upper));
                $norm = trim($norm, '_');
                if (isset($headerRow[$norm])) {
                    return $headerRow[$norm];
                }
            }

            return $defaultCol;
        };

        // Detect if sheet has an explicit NIK column header
        $hasNikHeader = (bool) $findCol(['NIK', 'KTP', 'NO_KTP', 'NIK_KTP', 'KTP_NIK', 'NOMOR_INDUK_KEPENDUDUKAN']);

        // Pre-resolve column indices for all fields based on header names (or standard 18/19 column layout)
        $colNama = $findCol(['NAMA', 'NAMA_PEGAWAI', 'NAMA_LENGKAP'], 1);
        $colNip = $findCol(['NIP', 'NOMOR_INDUK_PEGAWAI', 'NIP_BARU'], 2);
        $colNik = $findCol(['NIK', 'KTP', 'NO_KTP', 'NIK_KTP', 'KTP_NIK', 'NOMOR_INDUK_KEPENDUDUKAN'], $hasNikHeader ? 3 : null);
        $colNoKk = $findCol(['NO_KK', 'KK', 'KARTU_KELUARGA', 'NO_KARTU_KELUARGA'], $hasNikHeader ? 4 : 3);
        $colTglLahir = $findCol(['TANGGAL_LAHIR', 'TGL_LAHIR', 'TGL_LHR'], $hasNikHeader ? 5 : 4);
        $colStatusPegawai = $findCol(['STATUS_KEPEGAWAIAN', 'STATUS', 'JENIS_PEGAWAI', 'STATUS_PEGAWAI'], $hasNikHeader ? 6 : 5);
        $colUptd = $findCol(['UNIT_KERJA', 'UPTD', 'NAMA_UPTD', 'SKPD'], $hasNikHeader ? 7 : 6);
        $colGolongan = $findCol(['GOLONGAN', 'GOL', 'PANGKAT', 'GOL_RUANG'], $hasNikHeader ? 8 : 7);
        $colMkgTahun = $findCol(['MKG_TAHUN', 'MKG_THN', 'MKG', 'MASA_KERJA_TAHUN'], $hasNikHeader ? 9 : 8);
        $colMkgBulan = $findCol(['MKG_BULAN', 'MKG_BLN', 'MASA_KERJA_BULAN'], $hasNikHeader ? 10 : 9);
        $colTmtKgb = $findCol(['TMT_KGB_TERAKHIR', 'TMT_KGB', 'TMT_TERAKHIR', 'TMT_KGB_LALU'], $hasNikHeader ? 11 : 10);
        $colGajiPokok = $findCol(['GAJI_POKOK_TERAKHIR', 'GAJI_POKOK', 'GAJI', 'GAPOK'], $hasNikHeader ? 12 : 11);
        $colProvinsi = $findCol(['PROVINSI', 'PROV'], $hasNikHeader ? 13 : 12);
        $colKabKota = $findCol(['KABUPATEN_KOTA', 'KABUPATEN', 'KOTA', 'KAB_KOTA'], $hasNikHeader ? 14 : 13);
        $colKecamatan = $findCol(['KECAMATAN', 'KEC'], $hasNikHeader ? 15 : 14);
        $colKelurahan = $findCol(['KELURAHAN', 'KEL', 'DESA'], $hasNikHeader ? 16 : 15);
        $colAlamatDomisili = $findCol(['ALAMAT_DOMISILI', 'ALAMAT', 'DOMISILI'], $hasNikHeader ? 17 : 16);
        $colNoHp = $findCol(['NO_HP', 'HP', 'TELEPON', 'NO_TELP', 'NO_HANDPHONE'], $hasNikHeader ? 18 : 17);
        $colAlamatKtp = $findCol(['ALAMAT_KTP', 'ALAMAT_KTP_NON_SULSEL', 'KTP_ALAMAT'], $hasNikHeader ? 19 : 18);
        $colIdGabungan = $findCol(['NIP_NIK', 'NIK_NIP', 'NO_IDENTITAS', 'IDENTITAS', 'ID_PEGAWAI']);

        for ($row = 2; $row <= $highestRow; $row++) {
            $getCell = function (?int $col) use ($sheet, $row) {
                if (! $col) {
                    return '';
                }
                $v = $this->getCleanCellValue($sheet->getCell([$col, $row]));
                if ($v === null) {
                    return '';
                }
                $s = trim((string) $v);

                return ($s === '-') ? '' : $s;
            };

            $nama = $getCell($colNama);
            $rawNip = $getCell($colNip);
            $rawNik = $getCell($colNik);
            $idVal = $getCell($colIdGabungan);

            $nip = $rawNip !== '' ? $rawNip : null;
            $nik = $rawNik !== '' ? $rawNik : null;

            // Jika belum terpisah, periksa panjang digit B dan C
            if (! $nip && ! $nik) {
                $cellB = $getCell(2);
                $cellC = $getCell(3);

                if ($cellB !== '') {
                    $digitsB = preg_replace('/\D/', '', $cellB);
                    if (strlen($digitsB) === 18) {
                        $nip = $cellB;
                    } elseif (strlen($digitsB) === 16) {
                        $nik = $cellB;
                    } else {
                        $nip = $cellB;
                    }
                }

                if ($cellC !== '') {
                    $digitsC = preg_replace('/\D/', '', $cellC);
                    if (strlen($digitsC) === 16) {
                        $nik = $cellC;
                    } elseif (strlen($digitsC) === 18 && ! $nip) {
                        $nip = $cellC;
                    }
                }
            }

            if (! $nip && $idVal !== '') {
                $digitsId = preg_replace('/\D/', '', $idVal);
                if (strlen($digitsId) === 18) {
                    $nip = $idVal;
                } else {
                    $nik = $idVal;
                }
            }

            if (empty($nama) && empty($nip) && empty($nik)) {
                continue;
            }

            if (empty($nama) || (empty($nip) && empty($nik))) {
                $skipped++;

                continue;
            }

            if (empty($nip) && ! empty($nik)) {
                $fallbackCount++;
            }

            $noKk = $getCell($colNoKk);
            $tglLahirRaw = $getCell($colTglLahir);
            $statusPegawaiRaw = $getCell($colStatusPegawai);
            $uptdRaw = $getCell($colUptd);
            $golongan = $getCell($colGolongan);

            $mkgTahunRaw = $getCell($colMkgTahun);
            $mkgTahun = is_numeric($mkgTahunRaw) ? (int) $mkgTahunRaw : 0;

            $mkgBulanRaw = $getCell($colMkgBulan);
            $mkgBulan = is_numeric($mkgBulanRaw) ? (int) $mkgBulanRaw : 0;

            $tmtKgbRaw = $getCell($colTmtKgb);
            $gajiPokokRaw = $getCell($colGajiPokok);

            $provinsi = $getCell($colProvinsi);
            $kabKota = $getCell($colKabKota);
            $kecamatan = $getCell($colKecamatan);
            $kelurahan = $getCell($colKelurahan);
            $alamatDomisili = $getCell($colAlamatDomisili);
            $noHp = $getCell($colNoHp);
            $alamatKtp = $getCell($colAlamatKtp);

            // Validasi proteksi No HP: jika no_hp tidak berformat nomor telepon (misal berisi huruf/plat), pindahkan ke alamat_ktp jika alamat_ktp masih kosong
            if ($noHp !== '' && preg_match('/[a-zA-Z]{2,}/', $noHp)) {
                if ($alamatKtp === '') {
                    $alamatKtp = $noHp;
                }
                $noHp = '';
            }

            // Parse status kepegawaian
            $kepegawaian = 'PNS';
            $statusUpper = strtoupper($statusPegawaiRaw);
            if (str_contains($statusUpper, 'PPPK PW') || str_contains($statusUpper, 'P3K PW')) {
                $kepegawaian = 'PPPK PW';
            } elseif (str_contains($statusUpper, 'PPPK') || str_contains($statusUpper, 'P3K')) {
                $kepegawaian = 'PPPK';
            } elseif (str_contains($statusUpper, 'ASN')) {
                $kepegawaian = 'ASN';
            }

            // Tanggal lahir & umur otomatis dari NIP; jika tidak ada NIP (fallback ke NIK), tanggal lahir kosong
            $tanggalLahir = null;
            $umur = null;
            if ($nip) {
                $parsedTgl = Pegawai::tanggalLahirDariNip($nip);
                if ($parsedTgl) {
                    $tanggalLahir = $parsedTgl->toDateString();
                    $umur = $parsedTgl->age;
                }
            }

            // Retirement age 58
            $statusAktif = 'Aktif';
            if ($umur !== null && $umur >= 58) {
                $statusAktif = 'Pensiun';
            }

            // Match UPTD
            $uptd = $this->resolveUptd($uptdRaw, $uptdMap);
            if (! $uptd) {
                $uptd = $this->getDefaultUptd($uptdMap);
            }

            // Parse KGB Dates
            $tmtKgbTerakhir = $this->parseExcelDate($tmtKgbRaw);
            $tmtKgbBerikutnya = null;
            if ($tmtKgbTerakhir) {
                $tmtKgbBerikutnya = Carbon::parse($tmtKgbTerakhir)->addYears(2)->toDateString();
            }

            $gajiPokok = is_numeric($gajiPokokRaw) ? (float) $gajiPokokRaw : null;
            $estimasiGajiBaru = $gajiPokok ? $this->calculateSalaryBump($gajiPokok, $kepegawaian) : null;

            // Check existing by NIP or NIK
            $existing = null;
            if ($nip) {
                $existing = Pegawai::where('nip', $nip)->first();
                if (! $existing) {
                    $existing = Pegawai::where('nik', $nip)->first();
                }
            }
            if (! $existing && $nik) {
                $existing = Pegawai::where('nik', $nik)->first();
                if (! $existing) {
                    $existing = Pegawai::where('nip', $nik)->first();
                }
            }

            if ($existing) {
                if ($updateExisting) {
                    $existing->update([
                        'nama' => $nama,
                        'nip' => $nip ?: $existing->nip,
                        'nik' => $nik ?: $existing->nik,
                        'no_kk' => $noKk ?: $existing->no_kk,
                        'tanggal_lahir' => $tanggalLahir,
                        'umur' => $umur,
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
                'nip' => $nip,
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

            $nip = (! empty($colC) && $colC !== '-') ? $colC : null;
            $nik = (! empty($colD) && $colD !== '-') ? $colD : null;

            if (empty($colB) && empty($nip) && empty($nik)) {
                continue;
            }

            if (empty($nip) && empty($nik) && str_starts_with(strtoupper($colB), 'UPTD')) {
                $currentUptd = $this->resolveUptd($colB, $uptdMap);

                continue;
            }

            if ((empty($nip) && empty($nik)) || empty($colB)) {
                continue;
            }

            if (! $currentUptd) {
                $currentUptd = $this->getDefaultUptd($uptdMap);
            }

            if (empty($nip) && ! empty($nik)) {
                $fallbackCount++;
            }

            // Tanggal lahir & umur otomatis dari NIP; jika tidak ada NIP (fallback ke NIK), tanggal lahir kosong
            $tanggalLahir = null;
            $umur = null;
            if ($nip) {
                $parsedTgl = Pegawai::tanggalLahirDariNip($nip);
                if ($parsedTgl) {
                    $tanggalLahir = $parsedTgl->toDateString();
                    $umur = $parsedTgl->age;
                }
            }

            $statusPns = $this->getCellString($sheet->getCell("F{$row}"));
            $provinsi = $this->getCellString($sheet->getCell("G{$row}"));
            $kabKota = $this->getCellString($sheet->getCell("H{$row}"));
            $kelurahan = $this->getCellString($sheet->getCell("I{$row}"));
            $kecamatan = $this->getCellString($sheet->getCell("J{$row}"));
            $alamat = $this->getCellString($sheet->getCell("K{$row}"));
            $noHp = $this->getCellString($sheet->getCell("L{$row}"));
            $statusPegawai = $this->getCellString($sheet->getCell("M{$row}"));
            $alamatKtp = $this->getCellString($sheet->getCell("N{$row}"));

            $statusAktif = (strtolower($statusPns) === 'pensiun' || ($umur !== null && $umur >= 58)) ? 'Pensiun' : 'Aktif';

            $kepegawaian = 'PNS';
            $statusUpper = strtoupper($statusPegawai);
            if (str_contains($statusUpper, 'PPPK PW') || str_contains($statusUpper, 'P3K PW')) {
                $kepegawaian = 'PPPK PW';
            } elseif (str_contains($statusUpper, 'PPPK') || str_contains($statusUpper, 'P3K')) {
                $kepegawaian = 'PPPK';
            } elseif (str_contains($statusUpper, 'ASN')) {
                $kepegawaian = 'ASN';
            }

            $existing = null;
            if ($nip) {
                $existing = Pegawai::where('nip', $nip)->first();
                if (! $existing) {
                    $existing = Pegawai::where('nik', $nip)->first();
                }
            }
            if (! $existing && $nik) {
                $existing = Pegawai::where('nik', $nik)->first();
                if (! $existing) {
                    $existing = Pegawai::where('nip', $nik)->first();
                }
            }

            if ($existing) {
                if ($updateExisting) {
                    $existing->update([
                        'nama' => $colB,
                        'nip' => $nip ?: $existing->nip,
                        'nik' => $nik ?: $existing->nik,
                        'tanggal_lahir' => $tanggalLahir,
                        'umur' => $umur,
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
                'nip' => $nip,
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

            $nip = (! empty($colC) && $colC !== '-') ? $colC : null;
            $nik = (! empty($colD) && $colD !== '-') ? $colD : null;

            if (empty($colB) && empty($nip) && empty($nik)) {
                continue;
            }

            if (empty($nip) && empty($nik) && str_starts_with(strtoupper($colB), 'UPTD')) {
                $currentUptd = $this->resolveUptd($colB, $uptdMap);

                continue;
            }

            if ((empty($nip) && empty($nik)) || empty($colB)) {
                continue;
            }

            if (! $currentUptd) {
                $currentUptd = $this->getDefaultUptd($uptdMap);
            }

            if (empty($nip) && ! empty($nik)) {
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

            // Tanggal lahir & umur otomatis dari NIP; jika tidak ada NIP (fallback ke NIK), tanggal lahir kosong
            $tanggalLahir = null;
            $umur = null;
            if ($nip) {
                $parsedTgl = Pegawai::tanggalLahirDariNip($nip);
                if ($parsedTgl) {
                    $tanggalLahir = $parsedTgl->toDateString();
                    $umur = $parsedTgl->age;
                }
            }

            $kepegawaian = 'PNS';
            $statusUpper = strtoupper($statusPegawai);
            if (str_contains($statusUpper, 'PPPK PW') || str_contains($statusUpper, 'P3K PW')) {
                $kepegawaian = 'PPPK PW';
            } elseif (str_contains($statusUpper, 'PPPK') || str_contains($statusUpper, 'P3K')) {
                $kepegawaian = 'PPPK';
            } elseif (str_contains($statusUpper, 'ASN')) {
                $kepegawaian = 'ASN';
            }

            $existing = null;
            if ($nip) {
                $existing = Pegawai::where('nip', $nip)->first();
                if (! $existing) {
                    $existing = Pegawai::where('nik', $nip)->first();
                }
            }
            if (! $existing && $nik) {
                $existing = Pegawai::where('nik', $nik)->first();
                if (! $existing) {
                    $existing = Pegawai::where('nip', $nik)->first();
                }
            }

            if ($existing) {
                if ($updateExisting) {
                    $existing->update([
                        'nama' => $colB,
                        'nip' => $nip ?: $existing->nip,
                        'nik' => $nik ?: $existing->nik,
                        'no_kk' => $noKk ?: $existing->no_kk,
                        'tanggal_lahir' => $tanggalLahir,
                        'umur' => $umur,
                        'status_kepegawaian' => $kepegawaian,
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
                    if (! empty($noKk) && empty($existing->no_kk)) {
                        $existing->update(['no_kk' => $noKk]);
                    }
                    $skipped++;
                }

                continue;
            }

            Pegawai::create([
                'uptd_id' => $currentUptd->id,
                'nama' => $colB,
                'nip' => $nip,
                'nik' => $nik,
                'no_kk' => $noKk ?: null,
                'tanggal_lahir' => $tanggalLahir,
                'umur' => $umur,
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

        $cleanedName = trim(preg_replace('/\s+/', ' ', $rawName));
        if (strlen($cleanedName) > 2) {
            $newUptd = Uptd::firstOrCreate(
                ['nama_uptd' => $cleanedName],
                ['kode' => strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $cleanedName), 0, 4)) ?: 'UPTD']
            );
            $uptdMap[$rawUpper] = $newUptd;
            $uptdMap[strtoupper($cleanedName)] = $newUptd;

            return $newUptd;
        }

        return null;
    }

    /**
     * Ensure default UPTD entries exist in database.
     */
    private function ensureDefaultUptds(): void
    {
        if (Uptd::count() === 0) {
            foreach (self::DEFAULT_UPTDS as $item) {
                Uptd::firstOrCreate(
                    ['nama_uptd' => $item['nama_uptd']],
                    ['kode' => $item['kode']]
                );
            }
        }
    }

    /**
     * Get or create a reliable fallback UPTD instance.
     */
    private function getDefaultUptd(array &$uptdMap): Uptd
    {
        $this->ensureDefaultUptds();

        $default = Uptd::first();
        if (! $default) {
            $default = Uptd::create([
                'nama_uptd' => 'UPTD WS. JENEBERANG',
                'kode' => 'JNB',
            ]);
        }

        $uptdMap[strtoupper(trim($default->nama_uptd))] = $default;

        return $default;
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
