<?php

namespace App\Http\Controllers;

use App\Models\Pegawai;
use App\Models\Uptd;

class DashboardController extends Controller
{
    public function index()
    {
        Pegawai::perbaruiJadwalKgb();

        // Total statistics
        $totalPegawai = Pegawai::count();
        $totalPns = Pegawai::whereIn('status_kepegawaian', Pegawai::GROUP_PNS)->count();
        $totalPppk = Pegawai::whereIn('status_kepegawaian', Pegawai::GROUP_PPPK)->count();
        $totalAktif = Pegawai::where('status_aktif', 'Aktif')->count();
        $totalPensiun = Pegawai::where('status_aktif', 'Pensiun')->count();

        // UPTD data for charts
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

        // Recent pegawai
        $recentPegawai = Pegawai::with('uptd')
            ->latest()
            ->limit(5)
            ->get();

        // KGB statistics
        $totalKgbJatuhTempo = Pegawai::where('status_aktif', 'Aktif')
            ->whereNotNull('tmt_kgb_berikutnya')
            ->where('tmt_kgb_berikutnya', '<=', now())
            ->count();
        $totalKgbSegera = Pegawai::where('status_aktif', 'Aktif')
            ->whereNotNull('tmt_kgb_berikutnya')
            ->where('tmt_kgb_berikutnya', '>', now())
            ->where('tmt_kgb_berikutnya', '<=', now()->addDays(90))
            ->count();

        return view('dashboard', compact(
            'totalPegawai',
            'totalPns',
            'totalPppk',
            'totalAktif',
            'totalPensiun',
            'totalKgbJatuhTempo',
            'totalKgbSegera',
            'uptdData',
            'kabupatenData',
            'recentPegawai'
        ));
    }
}
