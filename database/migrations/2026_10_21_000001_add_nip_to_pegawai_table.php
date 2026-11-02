<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('pegawai', function (Blueprint $table) {
            $table->string('nip', 30)->nullable()->after('nama');
            $table->index('nip');
        });

        // Backfill existing records: jika nik sebelumnya berisi 18 digit (format NIP),
        // salin juga ke kolom nip agar data lama tetap memiliki NIP.
        DB::table('pegawai')
            ->whereNull('nip')
            ->whereNotNull('nik')
            ->whereRaw('LENGTH(nik) = 18')
            ->update([
                'nip' => DB::raw('nik'),
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pegawai', function (Blueprint $table) {
            $table->dropIndex(['nip']);
            $table->dropColumn('nip');
        });
    }
};
