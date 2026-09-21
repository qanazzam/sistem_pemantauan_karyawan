<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PegawaiController;
use App\Http\Controllers\UptdController;

Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

Route::get('/pegawai', [PegawaiController::class, 'index'])->name('pegawai.index');
Route::get('/pegawai/{pegawai}', [PegawaiController::class, 'show'])->name('pegawai.show');

Route::get('/uptd', [UptdController::class, 'index'])->name('uptd.index');
Route::get('/uptd/{uptd}', [UptdController::class, 'show'])->name('uptd.show');
