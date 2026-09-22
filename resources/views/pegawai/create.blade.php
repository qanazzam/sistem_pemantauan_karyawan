@extends('layouts.app')

@section('title', 'Tambah Pegawai Baru')
@section('page-title', 'Tambah Pegawai')
@section('page-subtitle', 'Input data pegawai PNS & PPPK satu per satu')

@section('content')
<div class="mb-4 d-flex flex-wrap align-items-center justify-content-between gap-3">
    <a href="{{ route('pegawai.index') }}" class="btn-secondary-custom">
        <i class="bi bi-arrow-left"></i> Kembali ke Daftar
    </a>
    <a href="{{ route('pegawai.import') }}" class="btn-secondary-custom">
        <i class="bi bi-file-earmark-excel"></i> Ingin Import Banyak Sekaligus? Buka Import Excel &rarr;
    </a>
</div>

@if($errors->any())
<div class="alert-custom-danger">
    <i class="bi bi-exclamation-triangle-fill fs-5"></i>
    <div>
        <strong>Terdapat kesalahan pengisian formulir:</strong>
        <ul class="mb-0 mt-1 ps-3" style="font-size:13px;">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
</div>
@endif

<form action="{{ route('pegawai.store') }}" method="POST" id="formTambahPegawai">
    @csrf

    <!-- SECTION 1: DATA IDENTITAS & KEPEGAWAIAN -->
    <div class="card-custom mb-4">
        <div class="card-custom-body p-4">
            <h5 class="form-section-title">
                <i class="bi bi-person-badge text-primary"></i> Data Identitas & Kepegawaian
            </h5>

            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label-custom">Nama Lengkap <span class="text-danger">*</span></label>
                    <input type="text" name="nama" class="form-control @error('nama') is-invalid @enderror"
                           placeholder="Contoh: AHMAD RASYID, S.T." value="{{ old('nama') }}" required>
                    @error('nama')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-3">
                    <label class="form-label-custom">NIP <span class="text-danger">*</span></label>
                    <input type="text" name="nik" maxlength="25" class="form-control font-monospace @error('nik') is-invalid @enderror"
                           placeholder="NIP atau NIK" value="{{ old('nik') }}" required>
                    <small class="text-muted" style="font-size:11px;">Jika belum ada NIP, masukkan NIK</small>
                    @error('nik')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-3">
                    <label class="form-label-custom">No. Kartu Keluarga (KK)</label>
                    <input type="text" name="no_kk" maxlength="20" class="form-control font-monospace"
                           placeholder="16 digit No KK" value="{{ old('no_kk') }}">
                </div>

                <div class="col-md-3">
                    <label class="form-label-custom">Tanggal Lahir</label>
                    <input type="date" name="tanggal_lahir" id="inputTanggalLahir" class="form-control"
                           value="{{ old('tanggal_lahir') }}">
                </div>

                <div class="col-md-3">
                    <label class="form-label-custom">Usia Terhitung</label>
                    <div class="input-group">
                        <input type="text" id="displayUmur" class="form-control" placeholder="Otomatis terhitung" readonly
                               style="background-color: var(--light-secondary); font-weight: 600;">
                        <span class="input-group-text">Tahun</span>
                    </div>
                    <small id="pensionNotice" class="text-danger fw-semibold d-none" style="font-size:11.5px;">
                        <i class="bi bi-exclamation-circle"></i> Usia &ge; 58 Tahun (Otomatis Pensiun)
                    </small>
                </div>

                <div class="col-md-3">
                    <label class="form-label-custom">Status Kepegawaian <span class="text-danger">*</span></label>
                    <select name="status_kepegawaian" id="selectKepegawaian" class="form-select @error('status_kepegawaian') is-invalid @enderror" required>
                        <option value="PNS" {{ old('status_kepegawaian') == 'PNS' ? 'selected' : '' }}>PNS</option>
                        <option value="PPPK" {{ old('status_kepegawaian') == 'PPPK' ? 'selected' : '' }}>PPPK</option>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label-custom">Status Kondisi</label>
                    <select name="status_aktif" id="selectStatusAktif" class="form-select">
                        <option value="Aktif" {{ old('status_aktif', 'Aktif') == 'Aktif' ? 'selected' : '' }}>Aktif</option>
                        <option value="Pensiun" {{ old('status_aktif') == 'Pensiun' ? 'selected' : '' }}>Pensiun (BUP &ge; 58)</option>
                    </select>
                </div>

                <div class="col-md-6">
                    <label class="form-label-custom">Unit Kerja (UPTD) <span class="text-danger">*</span></label>
                    <select name="uptd_id" class="form-select @error('uptd_id') is-invalid @enderror" required>
                        <option value="">-- Pilih Unit Kerja (UPTD) --</option>
                        @foreach($uptdList as $uptd)
                            <option value="{{ $uptd->id }}" {{ old('uptd_id') == $uptd->id ? 'selected' : '' }}>
                                {{ $uptd->nama_uptd }}
                            </option>
                        @endforeach
                    </select>
                    @error('uptd_id')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-6">
                    <label class="form-label-custom">No. Handphone / Telepon</label>
                    <input type="text" name="no_hp" class="form-control"
                           placeholder="Contoh: 081234567890" value="{{ old('no_hp') }}">
                </div>
            </div>
        </div>
    </div>

    <!-- SECTION 2: KENAIKAN GAJI BERKALA (KGB) -->
    <div class="card-custom mb-4">
        <div class="card-custom-body p-4">
            <h5 class="form-section-title">
                <i class="bi bi-cash-stack text-primary"></i> Data Kenaikan Gaji Berkala (KGB)
                <span style="font-size:12px;font-weight:normal;color:var(--text-muted);margin-left:auto;">
                    Siklus 2 Tahun (PP No. 5/2024 & PermenPAN-RB No. 7/2023)
                </span>
            </h5>

            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label-custom">Pangkat / Golongan</label>
                    <select name="golongan" id="selectGolongan" class="form-select">
                        <option value="">-- Pilih Golongan --</option>
                        <optgroup label="Golongan PNS" id="groupGolonganPns">
                            @foreach($golonganPns as $gol)
                                <option value="{{ $gol }}" {{ old('golongan') == $gol ? 'selected' : '' }}>{{ $gol }}</option>
                            @endforeach
                        </optgroup>
                        <optgroup label="Golongan PPPK" id="groupGolonganPppk">
                            @foreach($golonganPppk as $gol)
                                <option value="Gol. {{ $gol }}" {{ old('golongan') == "Gol. {$gol}" ? 'selected' : '' }}>Golongan {{ $gol }}</option>
                            @endforeach
                        </optgroup>
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label-custom">Masa Kerja Golongan (MKG) - Tahun</label>
                    <input type="number" name="mkg_tahun" min="0" max="50" class="form-control"
                           placeholder="Contoh: 12" value="{{ old('mkg_tahun', 0) }}">
                </div>

                <div class="col-md-4">
                    <label class="form-label-custom">Masa Kerja Golongan (MKG) - Bulan</label>
                    <input type="number" name="mkg_bulan" min="0" max="11" class="form-control"
                           placeholder="Contoh: 0" value="{{ old('mkg_bulan', 0) }}">
                </div>

                <div class="col-md-3">
                    <label class="form-label-custom">TMT KGB Terakhir</label>
                    <input type="date" name="tmt_kgb_terakhir" id="inputTmtKgbTerakhir" class="form-control"
                           value="{{ old('tmt_kgb_terakhir') }}">
                    <small class="text-muted" style="font-size:11px;">Tanggal SK KGB terakhir</small>
                </div>

                <div class="col-md-3">
                    <label class="form-label-custom">Jatuh Tempo KGB Berikutnya</label>
                    <input type="date" name="tmt_kgb_berikutnya" id="inputTmtKgbBerikutnya" class="form-control"
                           value="{{ old('tmt_kgb_berikutnya') }}">
                    <small class="text-muted" style="font-size:11px;">Otomatis +2 tahun dari TMT Terakhir</small>
                </div>

                <div class="col-md-3">
                    <label class="form-label-custom">Gaji Pokok Terakhir (Rp)</label>
                    <input type="number" step="1000" name="gaji_pokok_terakhir" id="inputGajiPokok" class="form-control font-monospace"
                           placeholder="Contoh: 4200000" value="{{ old('gaji_pokok_terakhir') }}">
                    <small id="displayGajiFormatted" class="text-muted fw-semibold" style="font-size:11px;">-</small>
                </div>

                <div class="col-md-3">
                    <label class="form-label-custom">Estimasi Gaji Pokok Baru (Rp)</label>
                    <input type="number" step="1000" name="estimasi_gaji_baru" id="inputGajiBaru" class="form-control font-monospace"
                           placeholder="Contoh: 4350000" value="{{ old('estimasi_gaji_baru') }}">
                    <small id="displayGajiBaruFormatted" class="text-muted fw-semibold" style="font-size:11px;">-</small>
                </div>
            </div>
        </div>
    </div>

    <!-- SECTION 3: ALAMAT & DOMISILI -->
    <div class="card-custom mb-4">
        <div class="card-custom-body p-4">
            <h5 class="form-section-title">
                <i class="bi bi-geo-alt text-primary"></i> Data Wilayah & Alamat
            </h5>

            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label-custom">Provinsi</label>
                    <input type="text" name="provinsi" class="form-control"
                           value="{{ old('provinsi', 'SULAWESI SELATAN') }}">
                </div>

                <div class="col-md-3">
                    <label class="form-label-custom">Kabupaten / Kota</label>
                    <input type="text" name="kabupaten_kota" class="form-control"
                           placeholder="Contoh: MAKASSAR" value="{{ old('kabupaten_kota') }}">
                </div>

                <div class="col-md-3">
                    <label class="form-label-custom">Kecamatan</label>
                    <input type="text" name="kecamatan" class="form-control"
                           placeholder="Contoh: PANAKKUKANG" value="{{ old('kecamatan') }}">
                </div>

                <div class="col-md-3">
                    <label class="form-label-custom">Kelurahan / Desa</label>
                    <input type="text" name="kelurahan" class="form-control"
                           placeholder="Contoh: KARUWISI" value="{{ old('kelurahan') }}">
                </div>

                <div class="col-md-6">
                    <label class="form-label-custom">Alamat Domisili</label>
                    <textarea name="alamat_domisili" rows="2" class="form-control"
                              placeholder="Alamat tempat tinggal saat ini...">{{ old('alamat_domisili') }}</textarea>
                </div>

                <div class="col-md-6">
                    <label class="form-label-custom">Alamat KTP</label>
                    <textarea name="alamat_ktp" rows="2" class="form-control"
                              placeholder="Alamat sesuai KTP jika berbeda...">{{ old('alamat_ktp') }}</textarea>
                </div>
            </div>
        </div>
    </div>

    <!-- FORM ACTION BAR -->
    <div class="d-flex align-items-center justify-content-end gap-2 pb-5">
        <a href="{{ route('pegawai.index') }}" class="btn-secondary-custom">
            Batal
        </a>
        <button type="submit" class="btn-primary-custom">
            <i class="bi bi-check2-circle fs-6"></i> Simpan Data Pegawai
        </button>
    </div>
</form>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const inputTglLahir = document.getElementById('inputTanggalLahir');
    const displayUmur = document.getElementById('displayUmur');
    const selectStatusAktif = document.getElementById('selectStatusAktif');
    const pensionNotice = document.getElementById('pensionNotice');

    // Auto-calculate age from date of birth
    function calculateAge() {
        if (!inputTglLahir.value) {
            displayUmur.value = '';
            pensionNotice.classList.add('d-none');
            return;
        }

        const dob = new Date(inputTglLahir.value);
        const today = new Date();
        let age = today.getFullYear() - dob.getFullYear();
        const m = today.getMonth() - dob.getMonth();
        if (m < 0 || (m === 0 && today.getDate() < dob.getDate())) {
            age--;
        }

        if (age >= 0 && age < 120) {
            displayUmur.value = age;
            if (age >= 58) {
                pensionNotice.classList.remove('d-none');
                selectStatusAktif.value = 'Pensiun';
            } else {
                pensionNotice.classList.add('d-none');
            }
        }
    }

    inputTglLahir.addEventListener('change', calculateAge);
    if (inputTglLahir.value) calculateAge();

    // Auto calculate Next KGB date (+2 years)
    const inputTmtTerakhir = document.getElementById('inputTmtKgbTerakhir');
    const inputTmtBerikutnya = document.getElementById('inputTmtKgbBerikutnya');

    inputTmtTerakhir.addEventListener('change', function() {
        if (!inputTmtTerakhir.value) return;
        const d = new Date(inputTmtTerakhir.value);
        d.setFullYear(d.getFullYear() + 2);
        const yyyy = d.getFullYear();
        const mm = String(d.getMonth() + 1).padStart(2, '0');
        const dd = String(d.getDate()).padStart(2, '0');
        inputTmtBerikutnya.value = `${yyyy}-${mm}-${dd}`;
    });

    // Format currency helper & auto-bump salary
    const inputGaji = document.getElementById('inputGajiPokok');
    const inputGajiBaru = document.getElementById('inputGajiBaru');
    const displayGaji = document.getElementById('displayGajiFormatted');
    const displayGajiBaru = document.getElementById('displayGajiBaruFormatted');

    function formatRupiah(val) {
        if (!val || isNaN(val)) return '-';
        return 'Rp ' + Number(val).toLocaleString('id-ID');
    }

    inputGaji.addEventListener('input', function() {
        const val = parseFloat(inputGaji.value);
        displayGaji.innerText = formatRupiah(val);

        if (!isNaN(val) && val > 0 && !inputGajiBaru.value) {
            // Auto bump ~2.8% rounded to hundreds
            const bump = Math.round(val * 0.028);
            const estNew = val + bump;
            inputGajiBaru.value = estNew;
            displayGajiBaru.innerText = formatRupiah(estNew);
        }
    });

    inputGajiBaru.addEventListener('input', function() {
        displayGajiBaru.innerText = formatRupiah(parseFloat(inputGajiBaru.value));
    });

    if (inputGaji.value) displayGaji.innerText = formatRupiah(inputGaji.value);
    if (inputGajiBaru.value) displayGajiBaru.innerText = formatRupiah(inputGajiBaru.value);
});
</script>
@endpush
@endsection
