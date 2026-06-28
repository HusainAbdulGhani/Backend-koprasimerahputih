<?php

namespace App\Http\Controllers;

use App\Services\SupplierService;
use App\Traits\ApiResponse;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class SupplierController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly SupplierService $supplierService
    ) {}

    /**
     * List Supplier dengan fitur search
     */
    public function index(Request $request): JsonResponse
    {
        $suppliers = $this->supplierService->getAllSuppliers($request->query('search'));
        return $this->successResponse('Daftar supplier berhasil diambil.', $suppliers);
    }

    /**
     * Simpan Supplier Baru
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nama_supplier' => 'required|string|max:255',
            'alamat'        => 'nullable|string',
        ]);

        $supplier = $this->supplierService->createSupplier($validated);
        return $this->successResponse('Supplier berhasil ditambahkan.', $supplier, 201);
    }

    /**
     * Detail Supplier + Load Relasi (Produk & Usulan)
     */
    public function show(int $id): JsonResponse
    {
        // Pake find() di sini, pengecekan null di bawahnya
        $supplier = Supplier::with(['produks', 'usulanStoks'])->find($id);
        
        if (!$supplier) {
            return $this->errorResponse('Supplier tidak ditemukan.', null, 404);
        }

        return $this->successResponse('Detail supplier berhasil diambil.', $supplier);
    }

    /**
     * Update Data Supplier
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'nama_supplier' => 'sometimes|required|string|max:255',
            'alamat'        => 'sometimes|nullable|string',
        ]);

        try {
            $supplier = $this->supplierService->updateSupplier($id, $validated);
            return $this->successResponse('Data supplier berhasil diperbarui.', $supplier);
        } catch (\Exception $e) {
            return $this->errorResponse('Gagal memperbarui supplier.', $e->getMessage(), 404);
        }
    }

    /**
     * Hapus Supplier
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $supplier = Supplier::findOrFail($id);
            
            // Cek jika supplier punya produk, mungkin jangan boleh dihapus dulu
            if ($supplier->produks()->count() > 0) {
                return $this->errorResponse('Tidak bisa menghapus supplier yang masih memiliki produk.', null, 422);
            }

            $supplier->delete();
            return $this->successResponse('Supplier berhasil dihapus.', null);
        } catch (\Exception $e) {
            return $this->errorResponse('Supplier tidak ditemukan.', null, 404);
        }
    }

    public function bulkDelete(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:suppliers,id_supplier',
        ]);

        $suppliersWithProducts = Supplier::query()
            ->whereIn('id_supplier', $validated['ids'])
            ->whereHas('produks')
            ->pluck('nama_supplier')
            ->values();

        if ($suppliersWithProducts->isNotEmpty()) {
            return $this->errorResponse(
                'Tidak bisa menghapus supplier yang masih memiliki produk.',
                ['suppliers' => $suppliersWithProducts],
                422
            );
        }

        $deleted = Supplier::query()->whereIn('id_supplier', $validated['ids'])->delete();

        return $this->successResponse('Supplier terpilih berhasil dihapus.', [
            'deleted' => $deleted,
        ]);
    }
}
