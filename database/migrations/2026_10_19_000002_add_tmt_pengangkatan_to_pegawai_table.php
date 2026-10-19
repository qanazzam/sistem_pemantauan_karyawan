<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * TMT pengangkatan (CPNS/PPPK) yang dibaca dari digit ke-9 s/d 14 NIP.
     */
    public function up(): void
    {
        Schema::table('pegawai', function (Blueprint $table) {
            $table->date('tmt_pengangkatan')->nullable()->after('status_kepegawaian');
        });
    }

    public function down(): void
    {
        Schema::table('pegawai', function (Blueprint $table) {
            $table->dropColumn('tmt_pengangkatan');
        });
    }
};
