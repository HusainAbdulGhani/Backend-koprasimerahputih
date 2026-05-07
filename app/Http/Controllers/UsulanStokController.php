<?php

namespace App\Http\Controllers;

use App\Http\Requests\ApproveUsulanRequest;
use App\Models\UsulanStok;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class UsulanStokController extends Controller
{
    use ApiResponse;

    public function approveUsulan(ApproveUsulanRequest $request, int $id_usulan): JsonResponse
    {
        $validated = $request->validated();
        $id_usulan = (int) $validated['id_usulan'];

        DB::beginTransaction();

        try {
            $usulan = UsulanStok::with('produk')->lockForUpdate()->find($id_usulan);

            if ($usulan->status !== 'Pending') {
                DB::rollBack();

                return $this->errorResponse(
                    'Usulan hanya dapat di-approve dari status Pending ke ACC.',
                    $usulan,
                    422
                );
            }

            $usulan->status = 'ACC';
            $usulan->save();

            $produk = $usulan->produk;
            $produk->stok += (int) $usulan->jumlah;
            $produk->save();

            DB::commit();

            return $this->successResponse(
                'Usulan stok berhasil di-ACC dan stok produk diperbarui.',
                $usulan->fresh()->load('produk'),
                200
            );
        } catch (\Throwable $e) {
            DB::rollBack();

            return $this->errorResponse('Terjadi kesalahan saat approve usulan stok.', $e->getMessage(), 500);
        }
    }
}
