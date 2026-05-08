<?php

namespace App\Http\Controllers;

use App\Models\Produk;
use Illuminate\Http\Request;
use App\Traits\ApiResponse; // Pastikan Trait ini di-import
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class ProdukController extends Controller
{
    use ApiResponse;

    // List semua produk (Bisa diakses Kasir, Admin, Gudang)
    public function index(Request $request): JsonResponse
    {
        // Eager load supplier biar data lengkap
        $query = Produk::with('supplier');

        if ($request->has('search')) {
            $query->where('nama_produk', 'like', '%' . $request->search . '%');
        }

        // Filter berdasarkan supplier jika dibutuhkan
        if ($request->has('id_supplier')) {
            $query->where('id_supplier', $request->id_supplier);
        }

        $produk = $query->orderBy('nama_produk', 'asc')->get();

        // Virtual attribute untuk warning stok < 100 (untuk frontend)
        $produk->transform(function ($item) {
            $item->is_low_stock = ((int) ($item->stok ?? 0)) < 100;
            return $item;
        });

        return $this->successResponse('Daftar Produk Koperasi', $produk);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id_supplier' => 'required|exists:suppliers,id_supplier', // Validasi relasi
            'nama_produk' => 'required|string|max:255',
            'harga_beli'  => 'required|numeric|min:0',
            'harga_jual'  => 'required|numeric|min:0',
            'stok'        => 'required|integer|min:0',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validasi gagal', $validator->errors(), 422);
        }

        if ($request->harga_jual < $request->harga_beli) {
            return $this->errorResponse('Harga jual tidak boleh lebih rendah dari harga beli!', null, 400);
        }

        $produk = Produk::create($request->all());

        return $this->successResponse('Produk berhasil ditambahkan', $produk, 201);
    }

    public function show($id): JsonResponse
    {
        $produk = Produk::with('supplier')->where('id_produk', $id)->first();

        if (!$produk) {
            return $this->errorResponse('Produk tidak ditemukan', null, 404);
        }

        return $this->successResponse('Detail Produk', $produk);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $produk = Produk::where('id_produk', $id)->first();
        if (!$produk) return $this->errorResponse('Produk tidak ditemukan', null, 404);

        $validator = Validator::make($request->all(), [
            'id_supplier' => 'sometimes|exists:suppliers,id_supplier',
            'nama_produk' => 'sometimes|string|max:255',
            'harga_beli'  => 'sometimes|numeric|min:0',
            'harga_jual'  => 'sometimes|numeric|min:0',
            'stok'        => 'sometimes|integer|min:0',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validasi gagal', $validator->errors(), 422);
        }

        $produk->update($request->all());

        return $this->successResponse('Produk berhasil diupdate', $produk);
    }

    public function destroy($id): JsonResponse
    {
        $produk = Produk::where('id_produk', $id)->first();
        if (!$produk) return $this->errorResponse('Produk tidak ditemukan', null, 404);

        $produk->delete();

        return $this->successResponse('Produk berhasil dihapus', null);
    }
}