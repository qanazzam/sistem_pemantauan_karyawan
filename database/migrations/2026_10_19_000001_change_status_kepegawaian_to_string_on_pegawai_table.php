<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ubah enum status_kepegawaian menjadi string agar mendukung ASN dan PPPK PW.
     */
    public function up(): void
    {
        Schema::table('pegawai', function (Blueprint $table) {
            $table->string('status_kepegawaian', 20)->default('PNS')->change();
        });
    }

    public function down(): void
    {
        Schema::table('pegawai', function (Blueprint $table) {
            $table->enum('status_kepegawaian', ['PNS', 'PPPK'])->default('PNS')->change();
        });
    }
};
