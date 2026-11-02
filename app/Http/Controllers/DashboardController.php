<?php

namespace App\Http\Controllers;

use App\Models\Pegawai;
use App\Models\Uptd;

class DashboardController extends Controller
{
    public function index()
    {
        Pegawai::perbaruiJadwalKgb();

        $now = now()->toDateString();
        $in90Days = now()->addDays(90)->toDateString();

        // Gabungkan 7 query COUNT menjadi 1 query agregat cepat
        $stats = Pegawai::selectRaw("
            COUNT(*) as total_pegawai,
            COUNT(CASE WHEN status_kepegawaian IN ('PNS', 'ASN') THEN 1 END) as total_pns,
            COUNT(CASE WHEN status_kepegawaian IN ('PPPK', 'PPPK PW') THEN 1 END) as total_pppk,
            COUNT(CASE WHEN status_aktif = 'Aktif' THEN 1 END) as total_aktif,
            COUNT(CASE WHEN status_aktif = 'Pensiun' THEN 1 END) as total_pensiun,
            COUNT(CASE WHEN status_aktif = 'Aktif' AND tmt_kgb_berikutnya IS NOT NULL AND tmt_kgb_berikutnya <= ? THEN 1 END) as total_kgb_jatuh_tempo,
            COUNT(CASE WHEN status_aktif = 'Aktif' AND tmt_kgb_berikutnya IS NOT NULL AND tmt_kgb_berikutnya > ? AND tmt_kgb_berikutnya <= ? THEN 1 END) as total_kgb_segera
        ", [$now, $now, $in90Days])->first();

        $totalPegawai = (int) ($stats->total_pegawai ?? 0);
        $totalPns = (int) ($stats->total_pns ?? 0);
        $totalPppk = (int) ($stats->total_pppk ?? 0);
        $totalAktif = (int) ($stats->total_aktif ?? 0);
        $totalPensiun = (int) ($stats->total_pensiun ?? 0);
        $totalKgbJatuhTempo = (int) ($stats->total_kgb_jatuh_tempo ?? 0);
        $totalKgbSegera = (int) ($stats->total_kgb_segera ?? 0);

        // UPTD data for table
        $uptdData = Uptd::withCount([
            'pegawai',
            'pegawai as pns_count' => function ($query) {
                $query->whereIn('status_kepegawaian', Pegawai::GROUP_PNS);
            },
            'pegawai as pppk_count' => function ($query) {
                $query->whereIn('status_kepegawaian', Pegawai::GROUP_PPPK);
            },
            'pegawai as aktif_count' => function ($query) {
                $query->where('status_aktif', 'Aktif');
            },
            'pegawai as pensiun_count' => function ($query) {
                $query->where('status_aktif', 'Pensiun');
            },
        ])->get();

        // Distribution by kabupaten/kota (top 10)
        $kabupatenData = Pegawai::selectRaw('kabupaten_kota, COUNT(*) as total')
            ->whereNotNull('kabupaten_kota')
            ->where('kabupaten_kota', '!=', '')
            ->groupBy('kabupaten_kota')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        return view('dashboard', compact(
            'totalPegawai',
            'totalPns',
            'totalPppk',
            'totalAktif',
            'totalPensiun',
            'totalKgbJatuhTempo',
            'totalKgbSegera',
            'uptdData',
            'kabupatenData'
        ));
    }
}
