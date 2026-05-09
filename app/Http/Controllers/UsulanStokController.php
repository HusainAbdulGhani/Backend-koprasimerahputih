<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\UsulanStok;
use App\Models\Gudang;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class UsulanStokController extends Controller
{
    use ApiResponse;

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id_produk'   => 'required|exists:produks,id_produk',
            'id_supplier' => 'required|exists:suppliers,id_supplier',
            'jumlah'      => 'required|integer|min:1',
            'harga_beli'  => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validasi gagal', $validator->errors(), 422);
        }

        // Ambil profil gudang berdasarkan akun yang sedang login
        $petugas = Gudang::where('id_account', Auth::user()->id_account)->first();

        if (!$petugas) {
            return $this->errorResponse('Anda tidak terdaftar sebagai petugas gudang.', null, 403);
        }

        $usulan = UsulanStok::create([
            'id_produk'      => $request->id_produk,
            'id_gudang'      => $petugas->id_gudang,
            'id_supplier'    => $request->id_supplier,
            'id_cabang'      => $petugas->id_cabang, // Otomatis ambil cabang petugas
            'id_pengurus_acc'=> null, // Baru diisi saat pengurus melakukan ACC
            'jumlah'         => $request->jumlah,
            'harga_beli'     => $request->harga_beli,
            'status'         => 'Pending',
            'tanggal_usulan' => now(),
        ]);

        return $this->successResponse('Usulan stok berhasil diajukan.', $usulan, 201);
    }

    /**
     * 2. APPROVE USULAN (Oleh Pengurus)
     */
    public function approveUsulan(Request $request, int $id_usulan): JsonResponse
    {
        DB::beginTransaction();

        try {
            // Lock record agar tidak ada double approval
            $usulan = UsulanStok::lockForUpdate()->with('produk')->find($id_usulan);

            if (!$usulan) {
                return $this->errorResponse('Usulan tidak ditemukan.', null, 404);
            }

            if ($usulan->status !== 'Pending') {
                return $this->errorResponse('Hanya usulan "Pending" yang bisa disetujui.', null, 422);
            }

            // Update status dan catat siapa pengurus yang ACC (sesuai ERD)
            $pengurus = $request->user()?->pengurus;
            if (! $pengurus) {
                DB::rollBack();
                return $this->errorResponse('Akun pengurus tidak valid.', null, 422);
            }

            $usulan->status = 'ACC';
            $usulan->id_pengurus_acc = $pengurus->id_pengurus;
            $usulan->save();

            // Tambah stok produk secara otomatis (Sesuai Dokumen)
            $produk = $usulan->produk;
            if (!$produk) {
                DB::rollBack();
                return $this->errorResponse('Produk untuk usulan ini tidak ditemukan.', null, 422);
            }

            $produk->stok = (int) ($produk->stok ?? 0);
            $produk->stok += (int) $usulan->jumlah;
            $produk->save();

            DB::commit();

            return $this->successResponse(
                'Usulan stok berhasil di-ACC dan stok produk diperbarui.',
                $usulan->fresh()->load('produk')
            );
        } catch (\Throwable $e) {
            DB::rollBack();
            return $this->errorResponse('Terjadi kesalahan.', $e->getMessage(), 500);
        }
    }
}