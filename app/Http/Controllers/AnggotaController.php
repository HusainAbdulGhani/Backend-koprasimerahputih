<?php

namespace App\Http\Controllers;

use App\Http\Requests\ActivateAnggotaRequest;
use App\Models\Anggota;
use App\Models\Simpanan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
            'status'        => 'sometimes|in:Calon,Aktif',
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
        $data['status'] = $data['status'] ?? 'Calon';

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

    /**
     * Aktivasi calon anggota (Admin only).
     * - status: Calon -> Aktif
     * - generate nomor_anggota
     * - trigger simpanan pokok pertama
     */
    public function activate(ActivateAnggotaRequest $request, int $id_anggota)
    {
        $anggota = Anggota::with(['account', 'cabang'])->find($id_anggota);
        if (! $anggota) {
            return response()->json(['success' => false, 'message' => 'Anggota tidak ditemukan'], 404);
        }

        if ($anggota->status !== 'Calon') {
            return response()->json([
                'success' => false,
                'message' => 'Hanya anggota berstatus Calon yang bisa diaktifkan.',
                'data' => $anggota,
            ], 422);
        }

        return DB::transaction(function () use ($request, $anggota) {
            // Format nomor anggota: AGT-{ID_CABANG}-{ID_ANGGOTA_PAD}
            $nomor = 'AGT-'.(int) $anggota->id_cabang.'-'.str_pad((string) $anggota->id_anggota, 6, '0', STR_PAD_LEFT);

            // Safety: kalau sudah pernah kebentuk (harusnya belum), pastiin unique
            if (Anggota::where('nomor_anggota', $nomor)->where('id_anggota', '!=', $anggota->id_anggota)->exists()) {
                $nomor = $nomor.'-'.now()->format('His');
            }

            $anggota->nomor_anggota = $nomor;
            $anggota->status = 'Aktif';
            $anggota->tanggal_daftar = $anggota->tanggal_daftar ?? now()->toDateString();
            $anggota->save();

            // Trigger simpanan pokok pertama
            $validated = $request->validated();
            $simpanan = Simpanan::create([
                'id_anggota' => $anggota->id_anggota,
                'jenis_simpanan' => 'Pokok',
                'jumlah' => (float) $validated['simpanan_pokok'],
                'tanggal' => $validated['tanggal'] ?? now()->toDateString(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Anggota berhasil diaktifkan dan simpanan pokok tercatat.',
                'data' => [
                    'anggota' => $anggota->fresh(),
                    'simpanan_pokok' => $simpanan,
                ],
            ], 200);
        });
    }
}