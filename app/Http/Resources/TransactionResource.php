<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $details = $this->whenLoaded('detailTransaksi', function () {
            return $this->detailTransaksi->map(function ($detail) {
                $produk = $detail->relationLoaded('produk') ? $detail->produk : null;
                $lineTotal = (float) $detail->harga_satuan * (int) $detail->jumlah;

                return [
                    'id_detail' => $detail->id_detail,
                    'id_produk' => $detail->id_produk,
                    'nama_produk' => $produk?->nama_produk,
                    'jumlah' => (int) $detail->jumlah,
                    'harga_satuan' => (float) $detail->harga_satuan,
                    'harga_satuan_rupiah' => 'Rp '.number_format((float) $detail->harga_satuan, 0, ',', '.'),
                    'total' => $lineTotal,
                    'total_rupiah' => 'Rp '.number_format($lineTotal, 0, ',', '.'),
                ];
            })->values();
        }, collect());

        $subTotal = 0.0;
        if ($details instanceof \Illuminate\Support\Collection) {
            $subTotal = (float) $details->sum('total');
        } elseif (is_array($details)) {
            $subTotal = (float) array_sum(array_map(fn ($d) => (float) ($d['total'] ?? 0), $details));
        }

        return [
            'id_transaksi' => $this->id_transaksi,
            'id_kasir' => $this->id_kasir,
            'id_anggota' => $this->id_anggota,
            'tanggal_jam' => optional($this->tanggal_jam)->format('Y-m-d H:i:s'),
            'kasir' => $this->whenLoaded('kasir', function () {
                $cabang = $this->kasir?->cabang;

                return [
                    'id_kasir' => $this->kasir?->id_kasir,
                    'nama_kasir' => $this->kasir?->nama_kasir,
                    'cabang' => $cabang ? [
                        'id_cabang' => $cabang->id_cabang,
                        'nama_cabang' => $cabang->nama_cabang,
                        'alamat_toko' => $cabang->lokasi,
                    ] : null,
                ];
            }),
            'sub_total' => $subTotal,
            'sub_total_rupiah' => 'Rp '.number_format($subTotal, 0, ',', '.'),
            'total_bayar' => (float) $this->total_bayar,
            'total_bayar_rupiah' => 'Rp '.number_format((float) $this->total_bayar, 0, ',', '.'),
            'ppn' => (float) $this->ppn,
            'details' => $details instanceof \Illuminate\Support\Collection ? $details->values() : $details,
        ];
    }
}
