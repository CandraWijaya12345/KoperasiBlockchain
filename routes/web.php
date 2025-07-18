<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\KoperasiController;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/total-anggota', [KoperasiController::class, 'getTotalAnggota']);

// Route untuk mendapatkan info umum koperasi
Route::get('/info', [KoperasiController::class, 'getContractInfo']);

// Route untuk mendapatkan detail anggota berdasarkan alamatnya
Route::get('/anggota/{alamat}', [KoperasiController::class, 'getAnggotaDetail']);

// Route contoh untuk mendapatkan semua pinjaman dari database lokal
Route::get('/pinjaman/semua', [KoperasiController::class, 'getAllPinjaman']);