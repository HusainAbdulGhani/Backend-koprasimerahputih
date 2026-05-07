<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id_transaksi' => $this->id_transaksi,
            'id_kasir' => $this->id_kasir,
            'id_anggota' => $this->id_anggota,
            'tanggal_jam' => optional($this->tanggal_jam)->format('Y-m-d H:i:s'),
            'total_bayar' => (float) $this->total_bayar,
            'total_bayar_rupiah' => 'Rp '.number_format((float) $this->total_bayar, 0, ',', '.'),
            'ppn' => (float) $this->ppn,
            'details' => $this->whenLoaded('detailTransaksi', function () {
                return $this->detailTransaksi->map(function ($detail) {
                    return [
                        'id_detail' => $detail->id_detail,
                        'id_produk' => $detail->id_produk,
                        'jumlah' => (int) $detail->jumlah,
                        'harga_satuan' => (float) $detail->harga_satuan,
                        'harga_satuan_rupiah' => 'Rp '.number_format((float) $detail->harga_satuan, 0, ',', '.'),
                    ];
                })->values();
            }, []),
        ];
    }
}
