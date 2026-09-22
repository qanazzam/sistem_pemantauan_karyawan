<?php

namespace App\Http\Controllers;

use App\Models\Pegawai;
use App\Models\Uptd;
use Illuminate\Http\Request;
use Carbon\Carbon;

class KgbController extends Controller
{
    /**
     * Display a listing of Kenaikan Gaji Berkala (KGB) for all employees.
     */
    public function index(Request $request)
    {
        $now = now();
        $in90Days = now()->addDays(90);

        // Calculate statistics
        $totalWajibKgb = Pegawai::where('status_aktif', 'Aktif')->count();
        $totalJatuhTempo = Pegawai::where('status_aktif', 'Aktif')
            ->whereNotNull('tmt_kgb_berikutnya')
            ->where('tmt_kgb_berikutnya', '<=', $now)
            ->count();
        $totalSegera = Pegawai::where('status_aktif', 'Aktif')
            ->whereNotNull('tmt_kgb_berikutnya')
            ->where('tmt_kgb_berikutnya', '>', $now)
            ->where('tmt_kgb_berikutnya', '<=', $in90Days)
            ->count();
        $totalAkanDatang = Pegawai::where('status_aktif', 'Aktif')
            ->whereNotNull('tmt_kgb_berikutnya')
            ->where('tmt_kgb_berikutnya', '>', $in90Days)
            ->count();

        // Build query
        $query = Pegawai::with('uptd');

        // Search by Nama / NIK
        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->where(function ($q) use ($search) {
                $q->where('nama', 'like', "%{$search}%")
                  ->orWhere('nik', 'like', "%{$search}%");
            });
        }

        // Filter by Status Kepegawaian (PNS / PPPK)
        if ($request->filled('status_kepegawaian')) {
            $query->where('status_kepegawaian', $request->status_kepegawaian);
        }

        // Filter by UPTD
        if ($request->filled('uptd_id')) {
            $query->where('uptd_id', $request->uptd_id);
        }

        // Filter by Golongan
        if ($request->filled('golongan')) {
            $query->where('golongan', $request->golongan);
        }

        // Filter by Status KGB
        if ($request->filled('status_kgb')) {
            switch ($request->status_kgb) {
                case 'Jatuh Tempo':
                    $query->where('status_aktif', 'Aktif')
                          ->whereNotNull('tmt_kgb_berikutnya')
                          ->where('tmt_kgb_berikutnya', '<=', $now);
                    break;
                case 'Segera KGB':
                    $query->where('status_aktif', 'Aktif')
                          ->whereNotNull('tmt_kgb_berikutnya')
                          ->where('tmt_kgb_berikutnya', '>', $now)
                          ->where('tmt_kgb_berikutnya', '<=', $in90Days);
                    break;
                case 'Akan Datang':
                    $query->where('status_aktif', 'Aktif')
                          ->whereNotNull('tmt_kgb_berikutnya')
                          ->where('tmt_kgb_berikutnya', '>', $in90Days);
                    break;
                case 'Pensiun':
                    $query->where('status_aktif', 'Pensiun');
                    break;
            }
        }

        // Default sorting: Prioritize upcoming & overdue KGB dates first
        $pegawai = $query->orderByRaw('CASE WHEN tmt_kgb_berikutnya IS NULL THEN 1 ELSE 0 END, tmt_kgb_berikutnya ASC')
            ->paginate(15)
            ->withQueryString();

        $uptdList = Uptd::orderBy('nama_uptd')->get();
        $golonganList = Pegawai::whereNotNull('golongan')
            ->distinct()
            ->orderBy('golongan')
            ->pluck('golongan');

        return view('kgb.index', compact(
            'pegawai',
            'uptdList',
            'golonganList',
            'totalWajibKgb',
            'totalJatuhTempo',
            'totalSegera',
            'totalAkanDatang'
        ));
    }
}
