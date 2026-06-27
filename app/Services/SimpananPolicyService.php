<?php

namespace App\Services;

use App\Models\Anggota;
use App\Models\Simpanan;

class SimpananPolicyService
{
    public function simpananPokokAwal(): float
    {
        return (float) config('koperasi.simpanan_pokok_awal', 100000);
    }

    public function simpananWajibAwal(): float
    {
        return (float) config('koperasi.simpanan_wajib_bulanan', 50000);
    }

    public function hasSimpananPokok(int $idAnggota): bool
    {
        return Simpanan::query()
            ->where('id_anggota', $idAnggota)
            ->where('jenis_simpanan', 'Pokok')
            ->whereIn('status', ['Pending', 'Verified'])
            ->exists();
    }

    public function hasSimpananWajib(int $idAnggota): bool
    {
        return Simpanan::query()
            ->where('id_anggota', $idAnggota)
            ->where('jenis_simpanan', 'Wajib')
            ->whereIn('status', ['Pending', 'Verified'])
            ->exists();
    }

    public function ensureSimpananAwal(Anggota $anggota): void
    {
        // 1. Ensure Simpanan Pokok
        if (!$this->hasSimpananPokok((int) $anggota->id_anggota)) {
            Simpanan::create([
                'id_anggota' => $anggota->id_anggota,
                'jenis_simpanan' => 'Pokok',
                'jumlah' => $this->simpananPokokAwal(),
                'tanggal' => now()->toDateString(),
                'status' => 'Verified',
            ]);
        }

        // 2. Ensure Simpanan Wajib
        if (!$this->hasSimpananWajib((int) $anggota->id_anggota)) {
            Simpanan::create([
                'id_anggota' => $anggota->id_anggota,
                'jenis_simpanan' => 'Wajib',
                'jumlah' => $this->simpananWajibAwal(),
                'tanggal' => now()->toDateString(),
                'status' => 'Verified',
            ]);
        }
    }
}
