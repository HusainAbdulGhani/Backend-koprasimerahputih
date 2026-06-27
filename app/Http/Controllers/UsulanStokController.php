<?php

namespace App\Http\Controllers;

use App\Http\Requests\ApproveUsulanRequest;
use App\Models\BranchProductStock;
use App\Models\Cabang;
use App\Models\Gudang;
use App\Models\UsulanStok;
use App\Traits\ApiResponse;
use App\Traits\ResolvesCabangScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class UsulanStokController extends Controller
{
    use ApiResponse, ResolvesCabangScope;

    public function index(Request $request): JsonResponse
    {
        $limit = min(max((int) $request->integer('limit', 100), 1), 500);
        $query = UsulanStok::query()
            ->select([
                'id_usulan',
                'kode_usulan',
                'id_produk',
                'id_gudang',
                'id_supplier',
                'id_cabang',
                'id_pengurus_acc',
                'jumlah',
                'harga_beli',
                'harga_jual',
                'status',
                'status_pengiriman',
                'tanggal_usulan',
                'tanggal_approved',
                'tanggal_diterima',
                'alasan_penolakan',
            ])
            ->with([
                'produk:id_produk,nama_produk',
                'gudang:id_gudang,nama_petugas,id_cabang',
                'supplier:id_supplier,nama_supplier',
                'cabang:id_cabang,nama_cabang',
                'pengurusAcc:id_pengurus,nama_pengurus',
            ]);
        if ($request->filled('status')) $query->where('status', $request->string('status'));
        if ($request->filled('exclude_status')) $query->where('status', '!=', $request->string('exclude_status'));
        $scope = $this->resolveCabangScope($request);
        if ($scope !== null) $query->where('id_cabang', $scope);

        $items = $query->orderByDesc('id_usulan')->limit($limit)->get();
        $groups = $items->groupBy(fn (UsulanStok $item) => $item->kode_usulan ?: 'LEGACY-'.$item->id_usulan)
            ->map(function ($rows, $kode) {
                $first = $rows->first();
                return [
                    'id_usulan' => $first->id_usulan,
                    'kode_usulan' => $kode,
                    'id_cabang' => $first->id_cabang,
                    'cabang' => $first->cabang,
                    'gudang' => $first->gudang,
                    'pengurus_acc' => $first->pengurusAcc,
                    'status' => $first->status,
                    'status_pengiriman' => $first->status_pengiriman,
                    'tanggal_usulan' => $first->tanggal_usulan,
                    'tanggal_approved' => $first->tanggal_approved,
                    'tanggal_diterima' => $first->tanggal_diterima,
                    'alasan_penolakan' => $first->alasan_penolakan,
                    'total_estimasi' => (float) $rows->sum(fn ($row) => $row->jumlah * $row->harga_beli),
                    'items' => $rows->values(),
                ];
            })->values();

        return $this->successResponse('Daftar usulan stok berhasil diambil.', $groups);
    }

    public function store(Request $request): JsonResponse
    {
        if ($request->filled('id_produk')) {
            $request->merge(['items' => [[
                'id_produk' => $request->id_produk, 'id_supplier' => $request->id_supplier,
                'jumlah' => $request->jumlah, 'harga_beli' => $request->harga_beli,
            ]]]);
        }
        $validator = Validator::make($request->all(), [
            'items' => 'required|array|min:1|max:30',
            'id_cabang' => 'nullable|exists:cabangs,id_cabang',
            'items.*.id_produk' => 'required|exists:produks,id_produk',
            'items.*.id_supplier' => 'required|exists:suppliers,id_supplier',
            'items.*.jumlah' => 'required|integer|min:1',
            'items.*.harga_beli' => 'required|numeric|min:0',
        ]);
        if ($validator->fails()) return $this->errorResponse('Validasi gagal', $validator->errors(), 422);

        $gudang = Gudang::where('id_account', $request->user()->id_account)->first();
        if (! $gudang) return $this->errorResponse('Anda tidak terdaftar sebagai petugas gudang.', null, 403);

        $targetCabangId = (int) ($request->input('id_cabang') ?: $gudang->id_cabang);
        if (! Cabang::where('id_cabang', $targetCabangId)->exists()) {
            return $this->errorResponse('Cabang target tidak valid.', null, 422);
        }

        $kode = 'UP-'.now()->format('Ymd').'-'.Str::upper(Str::random(6));
        $usulan = DB::transaction(function () use ($request, $gudang, $kode, $targetCabangId) {
            return collect($request->input('items'))->map(fn ($item) => UsulanStok::create([
                'kode_usulan' => $kode, 'id_produk' => $item['id_produk'], 'id_gudang' => $gudang->id_gudang,
                'id_supplier' => $item['id_supplier'], 'id_cabang' => $targetCabangId,
                'id_pengurus_acc' => null, 'jumlah' => $item['jumlah'], 'harga_beli' => $item['harga_beli'],
                'status' => 'Pending', 'tanggal_usulan' => now()->toDateString(),
            ]));
        });
        return $this->successResponse('Usulan pembelian berhasil diajukan.', $usulan->load(['produk', 'supplier', 'cabang']), 201);
    }

    public function approveUsulan(ApproveUsulanRequest $request, int $id_usulan): JsonResponse
    {
        return $this->process($request, $id_usulan, true);
    }

    public function rejectUsulan(Request $request, int $id_usulan): JsonResponse
    {
        return $this->process($request, $id_usulan, false);
    }

    public function completeDelivery(Request $request, int $id_usulan): JsonResponse
    {
        return DB::transaction(function () use ($request, $id_usulan) {
            $first = UsulanStok::lockForUpdate()->findOrFail($id_usulan);
            $scope = $this->resolveCabangScope($request);
            if ($scope !== null && (int) $first->id_cabang !== $scope) {
                return $this->errorResponse('Pengiriman ini bukan untuk cabang Anda.', null, 403);
            }

            $rows = UsulanStok::where('kode_usulan', $first->kode_usulan)->lockForUpdate()->get();
            if ($rows->contains(fn ($row) => $row->status !== 'Approved')) {
                return $this->errorResponse('Hanya usulan approved yang bisa diselesaikan.', null, 422);
            }
            if ($rows->contains(fn ($row) => $row->status_pengiriman === 'DELIVERED')) {
                return $this->successResponse('Pengiriman sudah selesai sebelumnya.', $rows->load(['produk', 'supplier', 'cabang']));
            }
            if ($rows->contains(fn ($row) => $row->status_pengiriman !== 'IN_TRANSIT')) {
                return $this->errorResponse('Pengiriman belum dalam status IN_TRANSIT.', null, 422);
            }

            foreach ($rows as $row) {
                $stock = BranchProductStock::firstOrCreate(
                    ['id_cabang' => $row->id_cabang, 'id_produk' => $row->id_produk],
                    ['stok' => 0]
                );
                $stock->increment('stok', (int) $row->jumlah);

                if ($row->harga_jual) {
                    $row->produk()->update(['harga_jual' => $row->harga_jual]);
                }
                $row->produk()->update(['harga_beli' => $row->harga_beli]);

                $row->status_pengiriman = 'DELIVERED';
                $row->tanggal_diterima = now();
                $row->save();
            }

            return $this->successResponse('Pengiriman selesai dan stok cabang berhasil ditambahkan.', $rows->load(['produk', 'supplier', 'cabang']));
        });
    }

    private function process(Request $request, int $idUsulan, bool $approve): JsonResponse
    {
        return DB::transaction(function () use ($request, $idUsulan, $approve) {
            $first = UsulanStok::lockForUpdate()->findOrFail($idUsulan);
            $scope = $this->resolveCabangScope($request);
            if ($scope !== null && (int) $first->id_cabang !== $scope) return $this->errorResponse('Usulan ini bukan dari cabang Anda.', null, 403);
            $rows = UsulanStok::where('kode_usulan', $first->kode_usulan)->lockForUpdate()->get();
            if ($rows->contains(fn ($row) => $row->status !== 'Pending')) return $this->errorResponse('Usulan ini sudah diproses.', null, 422);
            $pengurus = $request->user()?->pengurus;
            if (! $pengurus && $request->user()?->role !== 'Admin') return $this->errorResponse('Akun pengurus tidak valid.', null, 403);

            foreach ($rows as $row) {
                $row->id_pengurus_acc = $pengurus?->id_pengurus;
                $row->status = $approve ? 'Approved' : 'Rejected';
                $row->status_pengiriman = $approve ? 'IN_TRANSIT' : null;
                $row->tanggal_approved = $approve ? now() : null;
                $row->alasan_penolakan = $approve ? null : $request->input('alasan_penolakan');
                if ($approve && $request->filled('harga_jual')) $row->harga_jual = $request->input('harga_jual');
                $row->save();
            }
            return $this->successResponse($approve ? 'Usulan disetujui dan pesanan masuk proses pengiriman.' : 'Usulan pembelian ditolak.', $rows->load(['produk', 'supplier', 'cabang']));
        });
    }

}
