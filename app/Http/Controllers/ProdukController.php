<?php

namespace App\Http\Controllers;

use App\Models\Produk;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ProdukController extends Controller
{
    // List semua produk (Bisa diakses Kasir, Admin, Gudang)
    public function index(Request $request)
    {
        // Fitur Search: Biar kasir bisa cari berdasarkan nama_produk
        $query = Produk::query();

        if ($request->has('search')) {
            $query->where('nama_produk', 'like', '%' . $request->search . '%');
        }

        $produk = $query->get();

        return response()->json([
            'success' => true,
            'message' => 'Daftar Produk Koperasi',
            'data'    => $produk
        ]);
    }

    // Tambah Produk Baru (Gudang/Admin)
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nama_produk' => 'required|string|max:255',
            'harga_beli'  => 'required|numeric|min:0',
            'harga_jual'  => 'required|numeric|min:0',
            'stok'        => 'required|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors'  => $validator->errors()
            ], 422);
        }

        if ($request->harga_jual < $request->harga_beli) {
            return response()->json([
                'success' => false,
                'message' => 'Harga jual tidak boleh lebih rendah dari harga beli!'
            ], 400);
        }

        $produk = Produk::create($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Produk berhasil ditambahkan',
            'data'    => $produk
        ], 201);
    }

    // Detail Produk
    public function show($id)
    {
        $produk = Produk::where('id_produk', $id)->first();

        if (!$produk) {
            return response()->json(['success' => false, 'message' => 'Produk tidak ditemukan'], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => $produk
        ]);
    }

    // Update Produk (Gudang/Admin)
    public function update(Request $request, $id)
    {
        $produk = Produk::where('id_produk', $id)->first();
        if (!$produk) return response()->json(['message' => 'Produk tidak ditemukan'], 404);

        $validator = Validator::make($request->all(), [
            'nama_produk' => 'sometimes|string|max:255',
            'harga_beli'  => 'sometimes|numeric',
            'harga_jual'  => 'sometimes|numeric',
            'stok'        => 'sometimes|integer',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $produk->update($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Produk berhasil diupdate',
            'data'    => $produk
        ]);
    }

    // Hapus Produk
    public function destroy($id)
    {
        $produk = Produk::where('id_produk', $id)->first();
        if (!$produk) return response()->json(['message' => 'Produk tidak ditemukan'], 404);

        $produk->delete();

        return response()->json([
            'success' => true,
            'message' => 'Produk berhasil dihapus'
        ]);
    }
}