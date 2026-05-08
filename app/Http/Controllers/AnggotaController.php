<?php

namespace App\Http\Controllers;

use App\Models\Anggota;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AnggotaController extends Controller
{
    // Ambil semua anggota + data akun & cabang
    public function index()
    {
        $anggota = Anggota::with(['account', 'cabang'])->get();
        return response()->json([
            'success' => true,
            'message' => 'Daftar Anggota Koperasi',
            'data'    => $anggota
        ]);
    }

    // Simpan anggota baru
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id_account'    => 'required|exists:accounts,id_account',
            'nama_anggota'  => 'required|string|max:255',
            'alamat'        => 'required',
            'no_hp'         => 'required|max:15',
            'email'         => 'required|email|unique:anggotas,email',
            'id_cabang'     => 'required|exists:cabangs,id_cabang',
            'status'        => 'required|in:Aktif,Nonaktif',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors'  => $validator->errors()
            ], 422);
        }

        // Karena timestamps = false, pastikan tanggal_daftar terisi manual
        $data = $request->all();
        $data['tanggal_daftar'] = $request->tanggal_daftar ?? now()->format('Y-m-d');

        $anggota = Anggota::create($data);

        return response()->json([
            'success' => true,
            'message' => 'Anggota berhasil ditambahkan',
            'data'    => $anggota
        ], 201);
    }

    // Lihat detail anggota + history simpanan & pinjamannya
    public function show($id)
    {
        // Pake findOrFail biar otomatis 404 kalau ga ada
        $anggota = Anggota::with(['account', 'cabang', 'simpanans', 'pinjamans', 'transaksiPos'])
                          ->where('id_anggota', $id)
                          ->first();

        if (!$anggota) {
            return response()->json(['success' => false, 'message' => 'Anggota tidak ditemukan'], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => $anggota
        ]);
    }

    // Update Data Anggota
    public function update(Request $request, $id)
    {
        $anggota = Anggota::where('id_anggota', $id)->first();
        if (!$anggota) return response()->json(['message' => 'Not Found'], 404);

        $anggota->update($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Data anggota berhasil diupdate',
            'data'    => $anggota
        ]);
    }
}