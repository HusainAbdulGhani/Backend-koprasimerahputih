<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\PinjamanController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\UsulanStokController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\AngsuranController;
use App\Http\Controllers\AnggotaController;
use App\Http\Controllers\SimpananController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// 1. ROUTE PUBLIC
Route::post('/login', [AuthController::class, 'login']);

// 2. ROUTE PROTECTED
Route::middleware('auth:sanctum')->group(function () {
    
    Route::get('/me', function (Request $request) {
        return response()->json($request->user());
    });

    // Dashboard (Bisa diakses semua role yang login)
    Route::get('/dashboard', [DashboardController::class, 'index']);

    // --- FITUR ANGSURAN (SELF-SERVICE) ---
    // Anggota upload bukti
    Route::middleware('role:Anggota')->group(function () {
        Route::post('/angsurans', [AngsuranController::class, 'store']);
        Route::post('/pinjamans', [PinjamanController::class, 'store']);
        Route::get('/angsurans/history',[AngsuranController::class, 'history']);
        Route::get('/angsurans/{id_pinjaman}/sisa',[AngsuranController::class, 'checkSisa']);
    });

    // Admin Verifikasi Angsuran & Hapus Pinjaman
    Route::middleware('role:Admin')->group(function () {
        Route::delete('/pinjamans/{id_pinjaman}', [PinjamanController::class, 'destroy']);
    });

    // Modul Simpanan
    Route::middleware('role:Admin,Pengurus,Kasir,')->group(function () {
        Route::get('/simpanans', [SimpananController::class, 'index']);
        Route::post('/simpanans', [SimpananController::class, 'store']);
    });

    // --- FITUR PINJAMAN & TRANSAKSI ---
    // Cek Status (Semua Role terkait)
    Route::middleware('role:Anggota,Pengurus,Admin')->group(function () {
        Route::get('/pinjamans/{id_pinjaman}/status', [PinjamanController::class, 'showStatus']);
        Route::get('/simpanans/saldo/{id_anggota}', [SimpananController::class, 'cekSaldo']);
    });

    // Kasir (Belanja/Checkout)
    Route::middleware('role:Kasir')->group(function () {
        Route::post('/checkout', [TransactionController::class, 'checkout']);
    });

    // Pengurus & Admin (Approval)
    Route::middleware('role:Pengurus,Admin')->group(function () {
        Route::apiResource('anggota', AnggotaController::class);
        Route::patch('/angsurans/{id_angsuran}/verify', [AngsuranController::class, 'verify']);
        Route::patch('/pinjamans/{id_pinjaman}/approve', [PinjamanController::class, 'approve']);
        Route::patch('/usulan-stoks/{id_usulan}/approve', [UsulanStokController::class, 'approveUsulan']);
    });

    Route::post('/logout', [AuthController::class, 'logout']);
});