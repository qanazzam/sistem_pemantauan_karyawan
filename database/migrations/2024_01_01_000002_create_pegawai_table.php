<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pegawai', function (Blueprint $table) {
            $table->id();
            $table->foreignId('uptd_id')->constrained('uptd')->onDelete('cascade');
            $table->string('nama');
            $table->string('nik', 20)->nullable();
            $table->string('no_kk', 20)->nullable();
            $table->date('tanggal_lahir')->nullable();
            $table->integer('umur')->nullable();
            $table->enum('status_kepegawaian', ['PNS', 'PPPK'])->default('PNS');
            $table->enum('status_aktif', ['Aktif', 'Pensiun', 'Tidak Diketahui'])->default('Aktif');
            $table->string('provinsi')->nullable();
            $table->string('kabupaten_kota')->nullable();
            $table->string('kelurahan')->nullable();
            $table->string('kecamatan')->nullable();
            $table->text('alamat_domisili')->nullable();
            $table->string('no_hp', 30)->nullable();
            $table->text('alamat_ktp')->nullable();
            $table->timestamps();

            $table->index('status_kepegawaian');
            $table->index('status_aktif');
            $table->index('kabupaten_kota');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pegawai');
    }
};
