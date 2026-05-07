<?php

namespace App\Http\Controllers;

use App\Models\Angsuran;
use App\Models\Pinjaman;
use Illuminate\Http\Request;
use App\Traits\ApiResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;

class AngsuranController extends Controller
{
    use ApiResponse;

    // 1. Anggota upload pembayaran (Status Pending)
    public function store(Request $request)
    {
        $request->validate([
            'id_pinjaman' => 'required|exists:pinjamans,id_pinjaman',
            'jumlah_bayar' => 'required|numeric',
            'bukti_transfer' => 'required|image|mimes:jpg,png,jpeg|max:2048',
        ]);

        $path = $request->file('bukti_transfer')->store('bukti_pembayaran', 'public');

        $angsuran = Angsuran::create([
            'id_pinjaman' => $request->id_pinjaman,
            'jumlah_bayar' => $request->jumlah_bayar,
            'tanggal_bayar' => now(),
            'bukti_transfer' => $path,
            'status' => 'Pending',
            'sisa_pinjaman' => 0,
        ]);

        return $this->successResponse('Bukti transfer berhasil diupload, mohon tunggu verifikasi.', $angsuran);
    }

    // 2. Admin Verifikasi Pembayaran
    public function verify($id_angsuran)
    {
        return DB::transaction(function () use ($id_angsuran) {
            $angsuran = Angsuran::findOrFail($id_angsuran);
            
            if ($angsuran->status !== 'Pending') {
                return $this->errorResponse('Pembayaran ini sudah diproses.', 400);
            }

            $pinjaman = Pinjaman::findOrFail($angsuran->id_pinjaman);

            $lastVerified = Angsuran::where('id_pinjaman', $pinjaman->id_pinjaman)
                ->where('status', 'Verified')
                ->orderBy('id_angsuran', 'desc')
                ->first();

            $saldoSaatIni = $lastVerified ? $lastVerified->sisa_pinjaman : $pinjaman->jumlah_pinjaman;
            $sisaBaru = $saldoSaatIni - $angsuran->jumlah_bayar;

            $angsuran->update([
                'status' => 'Verified',
                'sisa_pinjaman' => $sisaBaru
            ]);

            if ($sisaBaru <= 0) {
                $pinjaman->update(['status' => 'Lunas']);
            }

            return $this->successResponse('Pembayaran berhasil diverifikasi.');
        });
    }

    // 3. Lihat Riwayat Angsuran & Statusnya
    public function history(Request $request)
    {
        $user = $request->user();
        $query = Angsuran::with('pinjaman');

        if ($user->role === 'Anggota') {
            $query->whereHas('pinjaman', function($q) use ($user) {
                $q->where('id_anggota', $user->anggota->id_anggota);
            });
        }

        $data = $query->orderBy('id_angsuran', 'desc')->get();
        
        $data->map(function ($item) {
            $item->url_bukti = $item->bukti_transfer ? asset('storage/' . $item->bukti_transfer) : null;
            return $item;
        });

        return $this->successResponse('Riwayat angsuran berhasil dimuat.', $data);
    }

    // 4. Cek Sisa Pinjaman Spesifik
    public function checkSisa($id_pinjaman)
    {
        $pinjaman = Pinjaman::findOrFail($id_pinjaman);
        
        $lastVerified = Angsuran::where('id_pinjaman', $id_pinjaman)
            ->where('status', 'Verified')
            ->orderBy('id_angsuran', 'desc')
            ->first();

        $sisa = $lastVerified ? $lastVerified->sisa_pinjaman : $pinjaman->jumlah_pinjaman;

        return $this->successResponse('Informasi saldo pinjaman.', [
            'id_pinjaman' => $id_pinjaman,
            'total_hutang_awal' => (float) $pinjaman->jumlah_pinjaman,
            'sisa_hutang_saat_ini' => (float) $sisa,
            'status_pinjaman' => $pinjaman->status
        ]);
    }
}