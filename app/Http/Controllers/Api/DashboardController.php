<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Anggota;
use App\Models\Pinjaman;
use App\Models\Produk;
use App\Models\UsulanStok;
use App\Models\Simpanan;
use App\Models\Angsuran;
use App\Models\DetailJurnal;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    use ApiResponse;

    public function index(): JsonResponse
    {
        try {
            // 1. Financial Stats (Real-time from Ledger)
            // Menghitung saldo Kas Koperasi saat ini berdasarkan Jurnal (Debit - Kredit)
            $saldoKas = DetailJurnal::whereHas('akun', function($q) {
                $q->where('nama_akun', 'like', '%Kas%');
            })->selectRaw('SUM(debit) - SUM(kredit) as total')->value('total') ?? 0;

            // Piutang berjalan: sisa pinjaman terbaru per pinjaman (dari angsuran Verified terakhir),
            // atau jumlah_pinjaman jika belum ada angsuran terverifikasi.
            $lastVerifiedSub = Angsuran::selectRaw('id_pinjaman, MAX(id_angsuran) as last_id')
                ->where('status', 'Verified')
                ->groupBy('id_pinjaman');

            $piutangBerjalan = Pinjaman::query()
                ->where('pinjamans.status', 'Approved')
                ->leftJoinSub($lastVerifiedSub, 'lv', function ($join) {
                    $join->on('pinjamans.id_pinjaman', '=', 'lv.id_pinjaman');
                })
                ->leftJoin('angsurans as a', 'a.id_angsuran', '=', 'lv.last_id')
                ->selectRaw('SUM(COALESCE(a.sisa_pinjaman, pinjamans.jumlah_pinjaman)) as total')
                ->value('total') ?? 0;

            $stats = [
                'total_anggota'    => Anggota::count(),
                'kas_koperasi'     => (float) $saldoKas,
                'total_simpanan'   => (float) Simpanan::sum('jumlah'),
                'piutang_berjalan' => (float) $piutangBerjalan,
                'aset_produk'      => (float) Produk::selectRaw('SUM(harga_beli * stok) as total')->value('total') ?? 0,
            ];

            // 2. Performance Tracking (Bulan Ini)
            $performance = [
                'angsuran_masuk_bulan_ini' => (float) Angsuran::whereMonth('tanggal_bayar', now()->month)
                    ->whereYear('tanggal_bayar', now()->year)
                    ->where('status', 'Verified')
                    ->sum('jumlah_bayar'),
                'pinjaman_keluar_bulan_ini' => (float) Pinjaman::whereMonth('tanggal_pengajuan', now()->month)
                    ->whereYear('tanggal_pengajuan', now()->year)
                    ->where('status', 'Approved')
                    ->sum('jumlah_pinjaman'),
            ];

            // 3. Inventory & Alerts
            $alerts = [
                'stok_kritis' => Produk::where('stok', '<', 100)
                    ->orderBy('stok', 'asc')
                    ->take(10)
                    ->get(['nama_produk', 'stok']),
                'total_produk' => Produk::count(),
            ];

            // 4. Action Center (Task List)
            $pendingTasks = [
                'pinjaman_pending'    => Pinjaman::where('status', 'Pending')->count(),
                'usulan_stok_pending' => UsulanStok::where('status', 'Pending')->count(),
                'angsuran_unverified' => Angsuran::where('status', 'Pending')->count(),
            ];

            // 5. Data Chart (Tren Simpanan 6 Bulan Terakhir)
            $chartSimpanan = Simpanan::selectRaw("DATE_FORMAT(tanggal, '%M') as bulan, SUM(jumlah) as total")
                ->groupBy('bulan')
                ->orderBy(DB::raw("MIN(tanggal)"), 'asc')
                ->take(6)
                ->get();

            // 6. Recent Activities
            $recentActivities = Pinjaman::with('anggota')
                ->orderBy('tanggal_pengajuan', 'desc')
                ->take(5)
                ->get();

            return $this->successResponse('Dashboard data synced successfully.', [
                'statistics'      => $stats,
                'performance'     => $performance,
                'inventory'       => $alerts,
                'pending_actions' => $pendingTasks,
                'charts'          => $chartSimpanan,
                'recent_loans'    => $recentActivities
            ]);

        } catch (\Throwable $e) {
            return $this->errorResponse('Gagal memuat dashboard.', $e->getMessage(), 500);
        }
    }
}