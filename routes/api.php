<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\PinjamanController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\UsulanStokController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// 1. ROUTE PUBLIC (Gak perlu token buat dapetin token)
Route::post('/login', [AuthController::class, 'login']);

// 2. ROUTE PROTECTED (Semua harus bawa token)
Route::middleware('auth:sanctum')->group(function () {
    
    Route::get('/me', function (Request $request) {
        return response()->json($request->user());
    });

    // Layer Role Anggota
    Route::middleware('role:Anggota')->group(function () {
        Route::post('/pinjamans', [PinjamanController::class, 'store']);
    });

    Route::middleware('role:Anggota,Pengurus,Admin')->group(function () {
        Route::get('/pinjamans/{id_pinjaman}/status', [PinjamanController::class, 'showStatus']);
    });

    // Layer Role Kasir
    Route::middleware('role:Kasir')->group(function () {
        Route::post('/checkout', [TransactionController::class, 'checkout']);
    });

    // Layer Role Pengurus
    Route::middleware('role:Pengurus')->group(function () {
        Route::patch('/pinjamans/{id_pinjaman}/approve', [PinjamanController::class, 'approve']);
        Route::patch('/usulan-stoks/{id_usulan}/approve', [UsulanStokController::class, 'approveUsulan']);
    });
    Route::post('/logout', [AuthController::class, 'logout']);
});