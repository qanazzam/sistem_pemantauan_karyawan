@extends('layouts.app')

@section('title', 'Import Data Pegawai dari Excel')
@section('page-title', 'Import Data Pegawai')
@section('page-subtitle', 'Unggah berkas Excel (.xlsx / .xls / .csv) untuk input data pegawai massal')

@section('content')
<div class="mb-4 d-flex flex-wrap align-items-center justify-content-between gap-3">
    <a href="{{ route('pegawai.index') }}" class="btn-secondary-custom">
        <i class="bi bi-arrow-left"></i> Kembali ke Daftar
    </a>
    <a href="{{ route('pegawai.create') }}" class="btn-secondary-custom">
        <i class="bi bi-person-plus"></i> Input Manual (1 per 1) &rarr;
    </a>
</div>

@if(session('error'))
<div class="alert-custom-danger">
    <i class="bi bi-exclamation-triangle-fill fs-5"></i>
    <div>{!! session('error') !!}</div>
</div>
@endif

<div class="row g-4 mb-4">
    <!-- UPLOAD ZONE -->
    <div class="col-lg-7">
        <div class="card-custom h-100">
            <div class="card-custom-body p-4">
                <h5 class="form-section-title">
                    <i class="bi bi-cloud-arrow-up text-primary"></i> Unggah Berkas Excel
                </h5>
                <p style="font-size:13px;color:var(--text-secondary);" class="mb-3">
                    Pilih atau tarik berkas spreadsheet berisi data pegawai. Sistem mendukung berkas template resmi SIMPEG maupun berkas dinas asli (format multi-sheet).
                </p>

                <form action="{{ route('pegawai.import.process') }}" method="POST" enctype="multipart/form-data" id="importForm">
                    @csrf

                    <div class="upload-dropzone mb-3" id="dropzone">
                        <i class="bi bi-file-earmark-spreadsheet" style="font-size: 42px; color: var(--primary);"></i>
                        <h6 class="mt-2 mb-1 fw-bold" style="color: var(--text-primary);" id="dropzonePrompt">
                            Tarik berkas ke sini atau <span style="color: var(--primary); text-decoration: underline;">klik untuk memilih</span>
                        </h6>
                        <p class="text-muted mb-0" style="font-size: 12px;">Format yang didukung: <strong>.xlsx, .xls, .csv</strong> (Maksimal 15 MB)</p>
                        <input type="file" name="excel_file" id="excelFileInput" accept=".xlsx,.xls,.csv" required>
                    </div>

                    <!-- Selected File Info -->
                    <div id="fileInfoBox" class="p-3 mb-3 d-none" style="background: #F3ECE4; border: 1px solid var(--border-color); border-radius: var(--border-radius-sm);">
                        <div class="d-flex align-items-center justify-content-between">
                            <div class="d-flex align-items-center gap-2">
                                <i class="bi bi-file-earmark-check-fill text-success fs-5"></i>
                                <div>
                                    <div class="fw-bold" style="font-size:13.5px;" id="selectedFileName">-</div>
                                    <div class="text-muted" style="font-size:11.5px;" id="selectedFileSize">-</div>
                                </div>
                            </div>
                            <button type="button" class="btn btn-sm btn-link text-danger p-0" id="btnRemoveFile" title="Batalkan pilihan">
                                <i class="bi bi-x-circle fs-5"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Options -->
                    <div class="form-check p-3 mb-4" style="background: var(--light); border: 1px solid var(--border-color); border-radius: var(--border-radius-sm);">
                        <input class="form-check-input ms-0 me-2" type="checkbox" name="update_existing" value="1" id="checkUpdateExisting">
                        <label class="form-check-label" for="checkUpdateExisting" style="font-size: 13px; font-weight: 600; color: var(--text-primary); cursor: pointer;">
                            Perbarui data jika NIP/NIK sudah ada di database (Update data yang ada)
                        </label>
                        <div class="text-muted ps-4" style="font-size: 11.5px; margin-top: 2px;">
                            Jika tidak dicentang, pegawai dengan NIP/NIK yang sudah ada akan dilewati (skip) agar data yang lama tetap aman.
                        </div>
                    </div>

                    <button type="submit" class="btn-primary-custom w-100 justify-content-center py-2 fs-6" id="btnSubmitImport">
                        <i class="bi bi-box-arrow-in-down"></i> Mulai Proses Import Data
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- TEMPLATE & INSTRUCTIONS -->
    <div class="col-lg-5">
        <div class="card-custom h-100">
            <div class="card-custom-body p-4 d-flex flex-column justify-content-between">
                <div>
                    <h5 class="form-section-title">
                        <i class="bi bi-download text-primary"></i> Unduh Template Excel
                    </h5>
                    <p style="font-size: 13px; color: var(--text-secondary);" class="mb-3">
                        Gunakan berkas template resmi yang sudah diformat dengan kolom standar kepegawaian dan formula pendukung untuk mempermudah proses input massal.
                    </p>

                    <a href="{{ route('pegawai.template') }}" class="btn-secondary-custom w-100 justify-content-center mb-4 py-2" style="background: #FAF8F5;">
                        <i class="bi bi-file-earmark-arrow-down text-success fs-5"></i>
                        <span>Unduh Template Format SIMPEG (.xlsx)</span>
                    </a>

                    <h6 class="fw-bold mb-2" style="font-size: 13px; color: var(--text-primary);">
                        <i class="bi bi-info-circle text-primary"></i> Ketentuan Data Import:
                    </h6>
                    <ul style="font-size: 12.5px; color: var(--text-secondary); line-height: 1.7; padding-left: 20px;" class="mb-0">
                        <li><strong>Kolom Wajib</strong>: <code>NAMA</code>, <code>NIP</code> (atau NIK), <code>STATUS_KEPEGAWAIAN</code> (PNS/PPPK), dan <code>UNIT_KERJA</code>.</li>
                        <li><strong>Batas Usia Pensiun</strong>: Pegawai yang berumur <strong>&ge; 58 tahun</strong> otomatis berstatus <strong>Pensiun</strong>.</li>
                        <li><strong>Format Tanggal</strong>: Gunakan format tanggal standar Excel atau teks <code>YYYY-MM-DD</code> (contoh: <code>1985-07-25</code>).</li>
                        <li><strong>Kenaikan Gaji (KGB)</strong>: Jika kolom TMT KGB diisi, sistem otomatis menghitung jadwal jatuh tempo berikutnya (+2 tahun).</li>
                        <li><strong>Dukungan File Asli</strong>: Berkas asli dinas seperti <code>DATA_PEGAWAI_DENGAN_STATUS.xlsx</code> dapat langsung diunggah tanpa modifikasi.</li>
                    </ul>
                </div>

                <div class="mt-4 pt-3 border-top" style="font-size: 12px; color: var(--text-muted);">
                    <i class="bi bi-shield-check text-success"></i> Proses import aman & dilengkapi validasi data otomatis.
                </div>
            </div>
        </div>
    </div>
</div>

<!-- COLUMN DICTIONARY TABLE -->
<div class="card-custom mb-5">
    <div class="card-custom-body p-4">
        <h5 class="form-section-title">
            <i class="bi bi-table text-primary"></i> Daftar Kolom Template Excel
        </h5>
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0" style="font-size: 12.5px;">
                <thead style="background: var(--light-secondary); color: var(--text-primary);">
                    <tr>
                        <th style="width: 160px;">Nama Kolom</th>
                        <th style="width: 100px;">Wajib?</th>
                        <th style="width: 140px;">Tipe Data</th>
                        <th>Contoh Nilai</th>
                        <th>Keterangan</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><code>NAMA</code></td>
                        <td><span class="badge bg-danger">Wajib</span></td>
                        <td>Teks</td>
                        <td>AHMAD RASYID, S.T.</td>
                        <td>Nama lengkap beserta gelar</td>
                    </tr>
                    <tr>
                        <td><code>NIP</code></td>
                        <td><span class="badge bg-danger">Wajib</span></td>
                        <td>Angka/Teks</td>
                        <td>19820815 200502 1 003 / 7371011508820001</td>
                        <td>Nomor Induk Pegawai (atau NIK KTP jika belum memiliki NIP)</td>
                    </tr>
                    <tr>
                        <td><code>NO_KK</code></td>
                        <td><span class="badge bg-secondary">Opsional</span></td>
                        <td>Angka/Teks</td>
                        <td>7371011508000001</td>
                        <td>Nomor Kartu Keluarga</td>
                    </tr>
                    <tr>
                        <td><code>TANGGAL_LAHIR</code></td>
                        <td><span class="badge bg-secondary">Opsional</span></td>
                        <td>Tanggal</td>
                        <td>1982-08-15</td>
                        <td>Tanggal lahir (umur &ge; 58 otomatis pensiun)</td>
                    </tr>
                    <tr>
                        <td><code>STATUS_KEPEGAWAIAN</code></td>
                        <td><span class="badge bg-danger">Wajib</span></td>
                        <td>Pilihan</td>
                        <td>PNS atau PPPK</td>
                        <td>Status kepegawaian ASN</td>
                    </tr>
                    <tr>
                        <td><code>UNIT_KERJA</code></td>
                        <td><span class="badge bg-danger">Wajib</span></td>
                        <td>Teks</td>
                        <td>UPTD WS. JENEBERANG</td>
                        <td>Nama UPTD tempat bertugas</td>
                    </tr>
                    <tr>
                        <td><code>GOLONGAN</code></td>
                        <td><span class="badge bg-secondary">Opsional</span></td>
                        <td>Teks</td>
                        <td>III/c atau Gol. IX</td>
                        <td>Pangkat/Golongan ruang untuk KGB</td>
                    </tr>
                    <tr>
                        <td><code>TMT_KGB_TERAKHIR</code></td>
                        <td><span class="badge bg-secondary">Opsional</span></td>
                        <td>Tanggal</td>
                        <td>2024-06-01</td>
                        <td>Tanggal SK kenaikan gaji berkala terakhir</td>
                    </tr>
                    <tr>
                        <td><code>GAJI_POKOK_TERAKHIR</code></td>
                        <td><span class="badge bg-secondary">Opsional</span></td>
                        <td>Angka</td>
                        <td>3850000</td>
                        <td>Gaji pokok dasar (tanpa titik atau koma)</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const fileInput = document.getElementById('excelFileInput');
    const dropzone = document.getElementById('dropzone');
    const fileInfoBox = document.getElementById('fileInfoBox');
    const selectedFileName = document.getElementById('selectedFileName');
    const selectedFileSize = document.getElementById('selectedFileSize');
    const btnRemoveFile = document.getElementById('btnRemoveFile');
    const btnSubmitImport = document.getElementById('btnSubmitImport');

    function updateFileDisplay(file) {
        if (!file) {
            fileInfoBox.classList.add('d-none');
            return;
        }
        selectedFileName.innerText = file.name;
        const sizeMb = (file.size / (1024 * 1024)).toFixed(2);
        selectedFileSize.innerText = `${sizeMb} MB`;
        fileInfoBox.classList.remove('d-none');
    }

    fileInput.addEventListener('change', function(e) {
        if (this.files && this.files[0]) {
            updateFileDisplay(this.files[0]);
        }
    });

    btnRemoveFile.addEventListener('click', function(e) {
        e.preventDefault();
        fileInput.value = '';
        fileInfoBox.classList.add('d-none');
    });

    // Drag and drop events
    ['dragenter', 'dragover'].forEach(eventName => {
        dropzone.addEventListener(eventName, function(e) {
            e.preventDefault();
            e.stopPropagation();
            dropzone.classList.add('dragover');
        }, false);
    });

    ['dragleave', 'drop'].forEach(eventName => {
        dropzone.addEventListener(eventName, function(e) {
            e.preventDefault();
            e.stopPropagation();
            dropzone.classList.remove('dragover');
        }, false);
    });

    dropzone.addEventListener('drop', function(e) {
        const dt = e.dataTransfer;
        const files = dt.files;
        if (files && files.length > 0) {
            fileInput.files = files;
            updateFileDisplay(files[0]);
        }
    });

    document.getElementById('importForm').addEventListener('submit', function() {
        btnSubmitImport.disabled = true;
        btnSubmitImport.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span> Sedang Memproses Berkas...';
    });
});
</script>
@endpush
@endsection
