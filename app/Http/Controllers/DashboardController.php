<?php

namespace App\Http\Controllers;

use App\Models\Pegawai;
use App\Models\Uptd;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index()
    {
        // Total statistics
        $totalPegawai = Pegawai::count();
        $totalPns = Pegawai::where('status_kepegawaian', 'PNS')->count();
        $totalPppk = Pegawai::where('status_kepegawaian', 'PPPK')->count();
        $totalAktif = Pegawai::where('status_aktif', 'Aktif')->count();
        $totalPensiun = Pegawai::where('status_aktif', 'Pensiun')->count();

        // UPTD data for charts
        $uptdData = Uptd::withCount([
            'pegawai',
            'pegawai as pns_count' => function ($query) {
                $query->where('status_kepegawaian', 'PNS');
            },
            'pegawai as pppk_count' => function ($query) {
                $query->where('status_kepegawaian', 'PPPK');
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

        return view('dashboard', compact(
            'totalPegawai',
            'totalPns',
            'totalPppk',
            'totalAktif',
            'totalPensiun',
            'uptdData',
            'kabupatenData',
            'recentPegawai'
        ));
    }
}
