<?php

namespace App\Http\Controllers;

use App\Http\Requests\ApprovePinjamanRequest;
use App\Http\Requests\StorePinjamanRequest;
use App\Http\Resources\PinjamanResource;
use App\Models\Pinjaman;
use App\Services\PinjamanService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class PinjamanController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly PinjamanService $pinjamanService)
    {
    }

    public function store(StorePinjamanRequest $request): JsonResponse
    {
        try {
            $pinjaman = $this->pinjamanService->ajukanPinjaman($request->validated());

            return $this->successResponse(
                message: 'Pengajuan pinjaman berhasil dibuat.',
                data: new PinjamanResource($pinjaman),
                code: 201
            );
        } catch (\Throwable $e) {
            return $this->errorResponse('Terjadi kesalahan saat membuat pinjaman.', $e->getMessage(), 500);
        }
    }

    public function approve(ApprovePinjamanRequest $request, int $id_pinjaman): JsonResponse
    {
        $validated = $request->validated();
        $id_pinjaman = (int) $validated['id_pinjaman'];

        DB::beginTransaction();
        try {
            $pinjaman = Pinjaman::lockForUpdate()->find($id_pinjaman);

            if ($pinjaman->status !== 'Pending') {
                DB::rollBack();
                return $this->errorResponse(
                    'Pinjaman hanya bisa di-ACC dari status Pending.',
                    new PinjamanResource($pinjaman),
                    422
                );
            }

            $pengurus = $request->user()?->pengurus;
            if (! $pengurus) {
                DB::rollBack();
                return $this->errorResponse('Akun pengurus tidak valid.', null, 422);
            }

            $pinjaman->status = 'Approved';
            $pinjaman->id_pengurus_acc = $pengurus->id_pengurus;
            $pinjaman->save();

            DB::commit();

            return $this->successResponse(
                'Pinjaman berhasil di-ACC.',
                new PinjamanResource($pinjaman->fresh()),
                200
            );
        } catch (\Throwable $e) {
            DB::rollBack();
            return $this->errorResponse('Terjadi kesalahan saat ACC pinjaman.', $e->getMessage(), 500);
        }
    }

    public function showStatus(int $id_pinjaman): JsonResponse
    {
        $pinjaman = Pinjaman::find($id_pinjaman);

        if (! $pinjaman) {
            return $this->errorResponse('Data pinjaman tidak ditemukan.', null, 404);
        }

        $user = request()->user();
        if ($user?->role === 'Anggota') {
            $anggota = $user->anggota;
            if (! $anggota || $pinjaman->id_anggota !== $anggota->id_anggota) {
                return $this->errorResponse('Anda tidak punya akses ke pinjaman ini.', null, 403);
            }
        }

        return $this->successResponse(
            'Status pinjaman berhasil diambil.',
            new PinjamanResource($pinjaman),
            200
        );
    }
    public function destroy($id_pinjaman): JsonResponse
{
    $pinjaman = Pinjaman::find($id_pinjaman);

    if (!$pinjaman) {
        return $this->errorResponse('Data pinjaman tidak ditemukan.', null, 404);
    }

    $pinjaman->delete();

    return $this->successResponse('Pinjaman berhasil dihapus.', null, 200);
}
}
