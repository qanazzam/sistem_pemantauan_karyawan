<?php

namespace App\Console\Commands;

use App\Models\Pegawai;
use App\Models\Uptd;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ImportPegawaiGabungan extends Command
{
    protected $signature = 'pegawai:import-gabungan
                            {file : Path file Excel}
                            {--sheet= : Nama sheet (default SEMUA PEGAWAI)}
                            {--dry-run : Simulasi tanpa menyimpan}';

    protected $description = 'Import data pegawai gabungan DSDA-CKTR (insert baru, update jika sudah ada)';

    private const DEFAULT_UPTD = 'DINAS SUMBER DAYA AIR, CIPTA KARYA & TATA RUANG';

    public function handle(): int
    {
        $file = $this->argument('file');
        if (! is_file($file)) {
            $this->error("File tidak ditemukan: {$file}");

            return self::FAILURE;
        }

        $reader = IOFactory::createReader('Xlsx');
        $reader->setReadDataOnly(true);
        $reader->setLoadSheetsOnly([$this->option('sheet') ?: 'SEMUA PEGAWAI']);
        $rows = $reader->load($file)->getActiveSheet()->toArray(null, false, false, false);
        array_shift($rows); // header

        $dry = (bool) $this->option('dry-run');
        $stats = ['insert' => 0, 'update' => 0, 'skip' => 0];

        DB::beginTransaction();

        $uptd = Uptd::firstOrCreate(
            ['nama_uptd' => self::DEFAULT_UPTD],
            ['kode' => 'DSDA']
        );

        $existing = Pegawai::all();
        $byNik = $existing->filter(fn ($p) => $this->digits($p->nik) !== '')->groupBy(fn ($p) => $this->digits($p->nik));
        $byName = $existing->groupBy(fn ($p) => $this->normName($p->nama));
        $used = [];

        foreach ($rows as $r) {
            $nama = $this->clean($r[1] ?? null);
            if ($nama === null) {
                $stats['skip']++;

                continue;
            }

            $nip = $this->digits($r[2] ?? null);
            $kk = $this->digits($r[3] ?? null);

            $data = array_filter([
                'nama' => $nama,
                'nik' => $nip !== '' ? substr($nip, 0, 20) : null,
                'no_kk' => $kk !== '' ? substr($kk, 0, 20) : null,
                'provinsi' => $this->clean($r[4] ?? null),
                'kabupaten_kota' => $this->clean($r[5] ?? null),
                'kelurahan' => $this->clean($r[6] ?? null),
                'kecamatan' => $this->clean($r[7] ?? null),
                'alamat_domisili' => $this->clean($r[8] ?? null),
                'no_hp' => ($h = $this->digits($r[9] ?? null)) !== '' ? substr($h, 0, 30) : null,
                'alamat_ktp' => $this->clean($r[11] ?? null),
            ], fn ($v) => $v !== null);

            $status = $this->mapStatus($r[10] ?? null, $nip);

            // Cari data yang sama: NIP + nama dulu, lalu nama (dipersempit dengan KK),
            // terakhir NIP saja jika pemegang NIP-nya hanya satu dan namanya tidak ada yang cocok.
            $found = null;
            $nameCands = ($byName[$this->normName($nama)] ?? collect())->reject(fn ($p) => isset($used[$p->id]));
            $nikCands = strlen($nip) >= 16
                ? ($byNik[$nip] ?? collect())->reject(fn ($p) => isset($used[$p->id]))
                : collect();

            $found = $nikCands->first(fn ($p) => $this->normName($p->nama) === $this->normName($nama));
            if (! $found) {
                $cands = $nameCands;
                if ($cands->count() > 1 && $kk !== '') {
                    $narrow = $cands->filter(fn ($p) => $this->digits($p->no_kk) === $kk);
                    $cands = $narrow->isNotEmpty() ? $narrow : $cands;
                }
                $found = $cands->first();
            }
            if (! $found && $nikCands->count() === 1) {
                $found = $nikCands->first();
            }

            if ($found) {
                $used[$found->id] = true;
                if ($status) {
                    $data['status_kepegawaian'] = $status;
                }
                $found->update($data);
                $stats['update']++;
            } else {
                $new = Pegawai::create($data + [
                    'uptd_id' => $uptd->id,
                    'status_kepegawaian' => $status ?? 'PNS',
                    'status_aktif' => 'Aktif',
                ]);
                $used[$new->id] = true;
                $stats['insert']++;
            }
        }

        // Sinkronkan jumlah PNS/PPPK per UPTD
        foreach (Uptd::all() as $u) {
            $u->update([
                'jumlah_pns' => $u->pegawai()->whereIn('status_kepegawaian', Pegawai::GROUP_PNS)->count(),
                'jumlah_pppk' => $u->pegawai()->whereIn('status_kepegawaian', Pegawai::GROUP_PPPK)->count(),
            ]);
        }

        $dry ? DB::rollBack() : DB::commit();

        $this->info(($dry ? '[DRY-RUN] ' : '')."Baru: {$stats['insert']} | Diupdate: {$stats['update']} | Dilewati: {$stats['skip']}");

        return self::SUCCESS;
    }

    private function clean($v): ?string
    {
        $v = trim((string) $v);

        return ($v === '' || $v === '-') ? null : $v;
    }

    private function digits($v): string
    {
        if (is_float($v) || is_int($v)) {
            $v = sprintf('%.0f', $v);
        }

        return preg_replace('/\D/', '', (string) $v);
    }

    private function normName($v): string
    {
        return preg_replace('/[^A-Z]/', '', strtoupper((string) $v));
    }

    /**
     * Status disimpan apa adanya: PNS, PPPK, PPPK PW, ASN (P3K => PPPK).
     */
    private function mapStatus($v, string $nip): ?string
    {
        $s = preg_replace('/\s+/', ' ', strtoupper(trim((string) $v)));

        return match ($s) {
            'PNS', 'PPPK', 'PPPK PW', 'ASN' => $s,
            'P3K' => 'PPPK',
            default => null,
        };
    }
}
