<?php

namespace App\Http\Controllers;

use App\Models\Uptd;
use Illuminate\Http\Request;

class UptdController extends Controller
{
    public function index()
    {
        $uptdList = Uptd::withCount([
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

        return view('uptd.index', compact('uptdList'));
    }

    public function show(Request $request, Uptd $uptd)
    {
        $query = $uptd->pegawai();

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('nama', 'like', "%{$search}%")
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

        $stats = [
            'total' => $uptd->pegawai()->count(),
            'pns' => $uptd->pegawai()->where('status_kepegawaian', 'PNS')->count(),
            'pppk' => $uptd->pegawai()->where('status_kepegawaian', 'PPPK')->count(),
            'aktif' => $uptd->pegawai()->where('status_aktif', 'Aktif')->count(),
            'pensiun' => $uptd->pegawai()->where('status_aktif', 'Pensiun')->count(),
        ];

        return view('uptd.show', compact('uptd', 'pegawai', 'stats'));
    }
}
