<?php

namespace App\Http\Controllers;

use App\Models\Simpanan;
use App\Models\Anggota;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SimpananController extends Controller
{
    // 1. Lihat semua riwayat simpanan
    public function index(Request $request)
    {
        $query = Simpanan::with('anggota');

        // Bisa filter per anggota atau per jenis lewat query string
        if ($request->has('id_anggota')) {
            $query->where('id_anggota', $request->id_anggota);
        }
        
        if ($request->has('jenis')) {
            $query->where('jenis_simpanan', $request->jenis);
        }

        $data = $query->orderBy('tanggal', 'desc')->get();

        return response()->json([
            'success' => true,
            'data'    => $data
        ]);
    }

    // 2. Input Setoran Simpanan
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id_anggota'     => 'required|exists:anggotas,id_anggota',
            'jenis_simpanan' => 'required|in:Pokok,Wajib,Sukarela',
            'jumlah'         => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        // Karena timestamps = false, tanggal wajib diisi manual
        $simpanan = Simpanan::create([
            'id_anggota'     => $request->id_anggota,
            'jenis_simpanan' => $request->jenis_simpanan,
            'jumlah'         => $request->jumlah,
            'tanggal'        => $request->tanggal ?? now()->format('Y-m-d'), 
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Simpanan berhasil dicatat!',
            'data'    => $simpanan
        ], 201);
    }

    // 3. Cek Saldo per Anggota (Penting buat Frontend)
    public function cekSaldo($id_anggota)
    {
        $anggota = Anggota::find($id_anggota);
        if (!$anggota) {
            return response()->json(['message' => 'Anggota tidak ditemukan'], 404);
        }

        // Hitung total saldo dari semua jenis simpanan
        $totalSaldo = Simpanan::where('id_anggota', $id_anggota)->sum('jumlah');

        // Ambil rincian per jenis (Pokok berapa, Wajib berapa)
        $rincian = Simpanan::where('id_anggota', $id_anggota)
                    ->selectRaw('jenis_simpanan, SUM(jumlah) as subtotal')
                    ->groupBy('jenis_simpanan')
                    ->get();

        return response()->json([
            'success' => true,
            'nama_anggota' => $anggota->nama_anggota,
            'total_saldo'  => $totalSaldo,
            'rincian'      => $rincian
        ]);
    }
}