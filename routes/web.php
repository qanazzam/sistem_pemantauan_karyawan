<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PegawaiController;
use App\Http\Controllers\UptdController;
use App\Http\Controllers\KgbController;

Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

Route::get('/pegawai', [PegawaiController::class, 'index'])->name('pegawai.index');
Route::get('/pegawai/create', [PegawaiController::class, 'create'])->name('pegawai.create');
Route::post('/pegawai', [PegawaiController::class, 'store'])->name('pegawai.store');
Route::get('/pegawai/import', [PegawaiController::class, 'importForm'])->name('pegawai.import');
Route::post('/pegawai/import', [PegawaiController::class, 'importExcel'])->name('pegawai.import.process');
Route::get('/pegawai-template-excel', [PegawaiController::class, 'downloadTemplate'])->name('pegawai.template');
Route::get('/pegawai/{pegawai}', [PegawaiController::class, 'show'])->name('pegawai.show');
Route::get('/pegawai/{pegawai}/edit', [PegawaiController::class, 'edit'])->name('pegawai.edit');
Route::put('/pegawai/{pegawai}', [PegawaiController::class, 'update'])->name('pegawai.update');
Route::delete('/pegawai/{pegawai}', [PegawaiController::class, 'destroy'])->name('pegawai.destroy');

Route::get('/uptd', [UptdController::class, 'index'])->name('uptd.index');
Route::get('/uptd/{uptd}', [UptdController::class, 'show'])->name('uptd.show');

Route::get('/kgb', [KgbController::class, 'index'])->name('kgb.index');
