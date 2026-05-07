<?php

namespace App\Services;

use App\Models\Angsuran;

class AngsuranService
{
    public function bayarAngsuran(array $data): Angsuran
    {
        $angsuran = Angsuran::find($data['id_angsuran']);
        if (!$angsuran) {
            throw new \Exception('Angsuran tidak ditemukan');
        }
        $angsuran->jumlah_bayar = $data['jumlah_bayar'];
        $angsuran->tanggal_bayar = $data['tanggal_bayar'];
        $angsuran->save();
        return $angsuran;
        Return Response:: sucseess('Angsuran berhasil dibayar', $angsuran, 200);
    }
}