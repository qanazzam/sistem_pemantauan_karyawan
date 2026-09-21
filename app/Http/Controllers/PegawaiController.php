<?php

namespace App\Http\Controllers;

use App\Models\Pegawai;
use App\Models\Uptd;
use Illuminate\Http\Request;

class PegawaiController extends Controller
{
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

        // Summary counts for current filter
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
}
