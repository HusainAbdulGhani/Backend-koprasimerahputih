<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Anggota;
use App\Models\Pinjaman;
use App\Models\Produk;
use App\Models\UsulanStok;
use App\Models\Simpanan;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    use ApiResponse;

    public function index(): JsonResponse
    {
        // 1. Ringkasan Angka (Stats Boxes)
        $stats = [
            'total_anggota' => Anggota::count(),
            'total_simpanan' => (float) Simpanan::sum('jumlah'),
            'pinjaman_aktif' => (float) Pinjaman::where('status', 'Approved')->sum('jumlah_pinjaman'),
            'total_produk' => Produk::count(),
        ];

        // 2. Alert & Warning (Stok Kritis)
        $stokKritis = Produk::where('stok', '<', 100)
            ->get(['nama_produk', 'stok']);

        // 3. Antrean Approval (Action Needed)
        $pendingTasks = [
            'pinjaman_pending' => Pinjaman::where('status', 'Pending')->count(),
            'usulan_stok_pending' => UsulanStok::where('status', 'Pending')->count(),
            'pembayaran_pending' => Angsuran::where('status', 'Pending')->count(),
        ];

        // 4. Perbaikan di sini: Pakai orderBy manual biar gak nyari created_at
        $recentActivities = Pinjaman::with('anggota')
            ->orderBy('tanggal_pengajuan', 'desc') 
            ->take(5)
            ->get();

        return $this->successResponse('Dashboard data retrieved successfully.', [
            'statistics' => $stats,
            'inventory_alerts' => $stokKritis,
            'pending_actions' => $pendingTasks,
            'recent_loans' => $recentActivities
        ]);
    }
}