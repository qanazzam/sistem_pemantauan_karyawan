<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('pegawai', function (Blueprint $table) {
            $table->string('golongan', 10)->nullable()->after('status_kepegawaian');
            $table->integer('mkg_tahun')->default(0)->after('golongan');
            $table->integer('mkg_bulan')->default(0)->after('mkg_tahun');
            $table->date('tmt_kgb_terakhir')->nullable()->after('mkg_bulan');
            $table->date('tmt_kgb_berikutnya')->nullable()->after('tmt_kgb_terakhir');
            $table->decimal('gaji_pokok_terakhir', 12, 2)->nullable()->after('tmt_kgb_berikutnya');
            $table->decimal('estimasi_gaji_baru', 12, 2)->nullable()->after('gaji_pokok_terakhir');

            $table->index('tmt_kgb_berikutnya');
            $table->index('golongan');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pegawai', function (Blueprint $table) {
            $table->dropIndex(['tmt_kgb_berikutnya']);
            $table->dropIndex(['golongan']);
            $table->dropColumn([
                'golongan',
                'mkg_tahun',
                'mkg_bulan',
                'tmt_kgb_terakhir',
                'tmt_kgb_berikutnya',
                'gaji_pokok_terakhir',
                'estimasi_gaji_baru',
            ]);
        });
    }
};
