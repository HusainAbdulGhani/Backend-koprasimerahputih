<?php

namespace App\Http\Controllers;

use App\Http\Requests\CheckoutRequest;
use App\Http\Resources\TransactionResource;
use App\Models\Anggota;
use App\Models\TransaksiPos;
use App\Services\TransactionService;
use App\Traits\ApiResponse;
use App\Traits\ResolvesCabangScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TransactionController extends Controller
{
    use ApiResponse, ResolvesCabangScope;

    public function __construct(private readonly TransactionService $transactionService)
    {
    }

    public function checkout(CheckoutRequest $request): JsonResponse
    {
        try {
            $result = $this->transactionService->checkout($request->validated());

            return $this->successResponse(
                message: 'Checkout berhasil diproses.',
                data: [
                    'transaksi' => new TransactionResource($result['transaksi']),
                    'warnings' => $result['warnings'],
                ],
                code: 201
            );
        } catch (\Throwable $e) {
            $code = 500;
            $msg = $e->getMessage();
            if (str_contains($msg, 'tidak mencukupi') || str_contains($msg, 'stok') || str_contains($msg, 'Stok')) {
                $code = 422;
            }
            return $this->errorResponse($msg, null, $code);
        }
    }

    /**
     * Lightweight active member lookup for POS member selection.
     */
    public function members(Request $request): JsonResponse
    {
        $limit = min(max((int) $request->integer('limit', 50), 1), 100);
        $query = Anggota::query()
            ->select(['id_anggota', 'nomor_anggota', 'nama_anggota', 'id_cabang', 'status'])
            ->where('status', 'Aktif');

        $cabangScope = $this->resolveCabangScope($request);
        if ($cabangScope !== null) {
            $query->where('id_cabang', $cabangScope);
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->query('search'));
            $query->where(function ($q) use ($search) {
                $q->where('nama_anggota', 'like', '%'.$search.'%')
                    ->orWhere('nomor_anggota', 'like', '%'.$search.'%');
            });
        }

        $members = $query
            ->orderBy('nama_anggota')
            ->limit($limit)
            ->get()
            ->map(fn (Anggota $anggota) => [
                'id_anggota' => $anggota->id_anggota,
                'nomor_anggota' => $anggota->nomor_anggota,
                'nama_anggota' => $anggota->nama_anggota,
            ]);

        return $this->successResponse('Daftar anggota aktif POS', $members);
    }

    /**
     * Struk / detail transaksi untuk keperluan cetak.
     */
    public function receipt(int $id_transaksi): JsonResponse
    {
        try {
            $transaksi = TransaksiPos::with(['kasir.cabang', 'detailTransaksi.produk'])->find($id_transaksi);
            if (! $transaksi) {
                return $this->errorResponse('Transaksi tidak ditemukan.', null, 404);
            }

            return $this->successResponse(
                message: 'Struk transaksi berhasil diambil.',
                data: new TransactionResource($transaksi),
                code: 200
            );
        } catch (\Throwable $e) {
            return $this->errorResponse('Terjadi kesalahan saat mengambil struk.', $e->getMessage(), 500);
        }
    }
}
