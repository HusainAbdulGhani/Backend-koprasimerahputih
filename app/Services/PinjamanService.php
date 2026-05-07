<?php

namespace App\Services;

use App\Models\Pinjaman;

class PinjamanService
{
    public function ajukanPinjaman(array $data): Pinjaman
    {
        $jumlahPinjaman = (float) $data['jumlah_pinjaman'];
        $data['biaya_operasional'] = round($jumlahPinjaman * 0.02, 2);
        $data['status'] = $data['status'] ?? 'Pending';
        $data['tenor'] = (string) $data['tenor'];

        return Pinjaman::create($data);
    }
}
