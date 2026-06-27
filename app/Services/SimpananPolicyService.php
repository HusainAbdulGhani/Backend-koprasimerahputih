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

    public function hasSimpananPokok(int $idAnggota): bool
    {
        return Simpanan::query()
            ->where('id_anggota', $idAnggota)
            ->where('jenis_simpanan', 'Pokok')
            ->whereIn('status', ['Pending', 'Verified'])
            ->exists();
    }

    public function ensureSimpananPokokAwal(Anggota $anggota): ?Simpanan
    {
        if ($this->hasSimpananPokok((int) $anggota->id_anggota)) {
            return null;
        }

        return Simpanan::create([
            'id_anggota' => $anggota->id_anggota,
            'jenis_simpanan' => 'Pokok',
            'jumlah' => $this->simpananPokokAwal(),
            'tanggal' => now()->toDateString(),
            'status' => 'Verified',
        ]);
    }
}
