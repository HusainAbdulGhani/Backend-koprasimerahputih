<?php

namespace App\Http\Controllers;

use App\Http\Requests\ApprovePinjamanRequest;
use App\Http\Requests\StorePinjamanRequest;
use App\Http\Resources\PinjamanResource;
use App\Models\Angsuran;
use App\Models\Pinjaman;
use App\Services\KoperasiFinanceService;
use App\Services\PinjamanService;
use App\Traits\ApiResponse;
use App\Traits\ResolvesCabangScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PinjamanController extends Controller
{
    use ApiResponse, ResolvesCabangScope;

    public function __construct(private readonly PinjamanService $pinjamanService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $perPage = min(max((int) $request->integer('per_page', 25), 1), 100);
        $query = Pinjaman::query()
            ->select([
                'id_pinjaman',
                'id_anggota',
                'id_pengurus_acc',
                'jumlah_pinjaman',
                'biaya_operasional',
                'tenor',
                'tanggal_pengajuan',
                'status',
            ])
            ->with([
                'anggota:id_anggota,nomor_anggota,nama_anggota,status,id_cabang',
                'anggota.cabang:id_cabang,nama_cabang,lokasi',
            ]);

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        $cabangScope = $this->resolveCabangScope($request);
        if ($cabangScope !== null) {
            $query->whereHas('anggota', fn ($q) => $q->where('id_cabang', $cabangScope));
        }

        if ($request->user()?->role === 'Anggota') {
            $query->where('id_anggota', $request->user()->anggota->id_anggota);
        }

        if ($request->string('status')->toString() === 'Approved') {
            $latestVerifiedSisa = Angsuran::query()
                ->select('sisa_pinjaman')
                ->whereColumn('angsurans.id_pinjaman', 'pinjamans.id_pinjaman')
                ->where('status', 'Verified')
                ->orderByDesc('id_angsuran')
                ->limit(1);

            $query->addSelect([
                'sisa_pinjaman' => $latestVerifiedSisa,
                'verified_installments_count' => Angsuran::query()
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('angsurans.id_pinjaman', 'pinjamans.id_pinjaman')
                    ->where('status', 'Verified'),
                'pending_installments_count' => Angsuran::query()
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('angsurans.id_pinjaman', 'pinjamans.id_pinjaman')
                    ->where('status', 'Pending'),
            ]);

            if ($request->filled('filter_lunas')) {
                $filterLunas = $request->boolean('filter_lunas');
                if ($filterLunas) {
                    $query->whereHas('angsurans', function ($q) {
                        $q->where('status', 'Verified');
                    })->where(function ($sub) {
                        $sub->select('sisa_pinjaman')
                            ->from('angsurans')
                            ->whereColumn('angsurans.id_pinjaman', 'pinjamans.id_pinjaman')
                            ->where('status', 'Verified')
                            ->orderByDesc('id_angsuran')
                            ->limit(1);
                    }, '=', 0);
                } else {
                    $query->where(function ($q) {
                        $q->whereNotExists(function ($sub) {
                            $sub->select(DB::raw(1))
                                ->from('angsurans')
                                ->whereColumn('angsurans.id_pinjaman', 'pinjamans.id_pinjaman')
                                ->where('status', 'Verified');
                        })->orWhere(function ($sub) {
                            $sub->select('sisa_pinjaman')
                                ->from('angsurans')
                                ->whereColumn('angsurans.id_pinjaman', 'pinjamans.id_pinjaman')
                                ->where('status', 'Verified')
                                ->orderByDesc('id_angsuran')
                                ->limit(1);
                        }, '>', 0);
                    });
                }
            }
        }

        $data = $request->boolean('paginate', true)
            ? $query->orderByDesc('tanggal_pengajuan')->paginate($perPage)
            : $query->orderByDesc('tanggal_pengajuan')->get();

        return $this->successResponse(
            'Daftar pinjaman berhasil diambil.',
            PinjamanResource::collection($data)
        );
    }

    public function store(StorePinjamanRequest $request): JsonResponse
    {
        try {
            $pinjaman = $this->pinjamanService->ajukanPinjaman($request->validated());

            broadcast(new \App\Events\LoanUpdated('created', $pinjaman));

            return $this->successResponse(
                'Pengajuan pinjaman berhasil dibuat. Menunggu persetujuan pengurus.',
                new PinjamanResource($pinjaman),
                201
            );
        } catch (\Throwable $e) {
            return $this->errorResponse('Terjadi kesalahan saat membuat pinjaman.', $e->getMessage(), 422);
        }
    }

    public function approve(ApprovePinjamanRequest $request, int $id_pinjaman): JsonResponse
    {
        $validated = $request->validated();
        $id_pinjaman = (int) $validated['id_pinjaman'];

        DB::beginTransaction();
        try {
            $pinjaman = Pinjaman::lockForUpdate()->with('anggota')->find($id_pinjaman);

            if (! $pinjaman) {
                DB::rollBack();
                return $this->errorResponse('Pinjaman tidak ditemukan.', null, 404);
            }

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

            $cabangScope = $this->resolveCabangScope($request);
            if ($cabangScope !== null && $pinjaman->anggota?->id_cabang !== $cabangScope) {
                DB::rollBack();
                return $this->errorResponse('Pinjaman ini bukan dari cabang Anda.', null, 403);
            }

            $idCabang = (int) $pinjaman->anggota?->id_cabang;
            $kasTersedia = app(KoperasiFinanceService::class)->kasTersedia($idCabang);
            $nominalPinjaman = (float) $pinjaman->jumlah_pinjaman;

            if ($kasTersedia < $nominalPinjaman) {
                DB::rollBack();
                return $this->errorResponse(
                    'Kas cabang tidak cukup untuk mencairkan pinjaman ini.',
                    [
                        'kas_tersedia' => $kasTersedia,
                        'nominal_pinjaman' => $nominalPinjaman,
                    ],
                    422
                );
            }

            $pinjaman->status = 'Approved';
            $pinjaman->id_pengurus_acc = $pengurus->id_pengurus;
            $pinjaman->save();

            app(\App\Services\JurnalService::class)->catatPinjamanDisetujui($pinjaman);

            DB::commit();

            broadcast(new \App\Events\LoanUpdated('approved', $pinjaman));

            return $this->successResponse(
                'Pinjaman berhasil di-ACC.',
                new PinjamanResource($pinjaman->fresh(['anggota', 'pengurusAcc'])),
                200
            );
        } catch (\Throwable $e) {
            DB::rollBack();
            return $this->errorResponse('Terjadi kesalahan saat ACC pinjaman.', $e->getMessage(), 500);
        }
    }

    public function reject(ApprovePinjamanRequest $request, int $id_pinjaman): JsonResponse
    {
        $validated = $request->validated();
        $id_pinjaman = (int) $validated['id_pinjaman'];

        DB::beginTransaction();
        try {
            $pinjaman = Pinjaman::lockForUpdate()->with('anggota')->find($id_pinjaman);

            if (! $pinjaman) {
                DB::rollBack();
                return $this->errorResponse('Pinjaman tidak ditemukan.', null, 404);
            }

            if ($pinjaman->status !== 'Pending') {
                DB::rollBack();
                return $this->errorResponse(
                    'Pinjaman hanya bisa ditolak dari status Pending.',
                    new PinjamanResource($pinjaman),
                    422
                );
            }

            $cabangScope = $this->resolveCabangScope($request);
            if ($cabangScope !== null && $pinjaman->anggota?->id_cabang !== $cabangScope) {
                DB::rollBack();
                return $this->errorResponse('Pinjaman ini bukan dari cabang Anda.', null, 403);
            }

            $pinjaman->status = 'Rejected';
            $pinjaman->save();

            DB::commit();

            broadcast(new \App\Events\LoanUpdated('rejected', $pinjaman));

            return $this->successResponse(
                'Pinjaman berhasil ditolak.',
                new PinjamanResource($pinjaman->fresh(['anggota', 'pengurusAcc'])),
                200
            );
        } catch (\Throwable $e) {
            DB::rollBack();
            return $this->errorResponse('Terjadi kesalahan saat menolak pinjaman.', $e->getMessage(), 500);
        }
    }

    public function showStatus(int $id_pinjaman): JsonResponse
    {
        $pinjaman = Pinjaman::with(['anggota', 'pengurusAcc'])->find($id_pinjaman);

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

    public function destroy(int $id_pinjaman): JsonResponse
    {
        $pinjaman = Pinjaman::find($id_pinjaman);

        if (! $pinjaman) {
            return $this->errorResponse('Data pinjaman tidak ditemukan.', null, 404);
        }

        broadcast(new \App\Events\LoanUpdated('deleted', $pinjaman));

        $pinjaman->delete();

        return $this->successResponse('Pinjaman berhasil dihapus.', null, 200);
    }

    public function counts(Request $request): JsonResponse
    {
        $queryPending = Pinjaman::query()->where('status', 'Pending');
        $queryApproved = Pinjaman::query()->where('status', 'Approved');

        $cabangScope = $this->resolveCabangScope($request);
        if ($cabangScope !== null) {
            $queryPending->whereHas('anggota', fn ($q) => $q->where('id_cabang', $cabangScope));
            $queryApproved->whereHas('anggota', fn ($q) => $q->where('id_cabang', $cabangScope));
        }

        $pendingCount = $queryPending->count();

        $latestVerifiedSisa = Angsuran::query()
            ->select('sisa_pinjaman')
            ->whereColumn('angsurans.id_pinjaman', 'pinjamans.id_pinjaman')
            ->where('status', 'Verified')
            ->orderByDesc('id_angsuran')
            ->limit(1);

        $completedCount = (clone $queryApproved)->whereHas('angsurans', function ($q) {
            $q->where('status', 'Verified');
        })->where(function ($sub) {
            $sub->select('sisa_pinjaman')
                ->from('angsurans')
                ->whereColumn('angsurans.id_pinjaman', 'pinjamans.id_pinjaman')
                ->where('status', 'Verified')
                ->orderByDesc('id_angsuran')
                ->limit(1);
        }, '=', 0)->count();

        $activeCount = (clone $queryApproved)->where(function ($q) {
            $q->whereNotExists(function ($sub) {
                $sub->select(DB::raw(1))
                    ->from('angsurans')
                    ->whereColumn('angsurans.id_pinjaman', 'pinjamans.id_pinjaman')
                    ->where('status', 'Verified');
            })->orWhere(function ($sub) {
                $sub->select('sisa_pinjaman')
                    ->from('angsurans')
                    ->whereColumn('angsurans.id_pinjaman', 'pinjamans.id_pinjaman')
                    ->where('status', 'Verified')
                    ->orderByDesc('id_angsuran')
                    ->limit(1);
            }, '>', 0);
        })->count();

        $savingsQuery = \App\Models\Anggota::query();
        if ($cabangScope !== null) {
            $savingsQuery->where('id_cabang', $cabangScope);
        }
        $savingsCount = $savingsQuery->count();

        return $this->successResponse('Jumlah pinjaman dan simpanan berhasil diambil.', [
            'pending_loans' => $pendingCount,
            'active_loans' => $activeCount,
            'completed_loans' => $completedCount,
            'savings' => $savingsCount,
        ]);
    }
}
