<?php

namespace App\Http\Controllers;

use App\Models\Pegawai;
use App\Models\Uptd;
use Illuminate\Http\Request;

class UptdController extends Controller
{
    public function index()
    {
        $uptdList = Uptd::withCount([
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

        return view('uptd.index', compact('uptdList'));
    }

    public function show(Request $request, Uptd $uptd)
    {
        $query = $uptd->pegawai();

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('nama', 'like', "%{$search}%")
                    ->orWhere('nip', 'like', "%{$search}%")
                    ->orWhere('nik', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status_kepegawaian')) {
            $query->where('status_kepegawaian', $request->status_kepegawaian);
        }

        if ($request->filled('status_aktif')) {
            $query->where('status_aktif', $request->status_aktif);
        }

        $pegawai = $query->orderBy('nama')->paginate(15)->withQueryString();

        $statsRaw = $uptd->pegawai()
            ->selectRaw("
                COUNT(*) as total,
                COUNT(CASE WHEN status_kepegawaian IN ('PNS', 'ASN') THEN 1 END) as pns,
                COUNT(CASE WHEN status_kepegawaian IN ('PPPK', 'PPPK PW') THEN 1 END) as pppk,
                COUNT(CASE WHEN status_aktif = 'Aktif' THEN 1 END) as aktif,
                COUNT(CASE WHEN status_aktif = 'Pensiun' THEN 1 END) as pensiun
            ")
            ->first();

        $stats = [
            'total' => (int) ($statsRaw->total ?? 0),
            'pns' => (int) ($statsRaw->pns ?? 0),
            'pppk' => (int) ($statsRaw->pppk ?? 0),
            'aktif' => (int) ($statsRaw->aktif ?? 0),
            'pensiun' => (int) ($statsRaw->pensiun ?? 0),
        ];

        return view('uptd.show', compact('uptd', 'pegawai', 'stats'));
    }
}
