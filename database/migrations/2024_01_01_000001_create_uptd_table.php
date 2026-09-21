<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('uptd', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 5)->nullable();
            $table->string('nama_uptd');
            $table->integer('jumlah_pns')->default(0);
            $table->integer('jumlah_pppk')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('uptd');
    }
};
