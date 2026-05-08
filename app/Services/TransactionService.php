<?php

namespace App\Services;

use App\Models\DetailTransaksi;
use App\Models\Produk;
use App\Models\TransaksiPos;
use App\Services\JurnalService;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class TransactionService
{
    /**
     * @return array{transaksi: TransaksiPos, details: array<int, DetailTransaksi>, warnings: array<int, string>}
     */
    public function checkout(array $data): array
    {
        $warnings = [];
        $details = [];

        DB::beginTransaction();

        try {
            $items = $data['items'] ?? [];
            $subTotal = 0.0;

            if (empty($items)) {
                throw new RuntimeException('Items transaksi tidak boleh kosong.');
            }

            foreach ($items as $item) {
                $produk = Produk::where('id_produk', $item['id_produk'])->lockForUpdate()->first();

                if (! $produk) {
                    throw new RuntimeException('Produk dengan id '.$item['id_produk'].' tidak ditemukan.');
                }

                $jumlah = (int) $item['jumlah'];
                if ($jumlah <= 0) {
                    throw new RuntimeException('Jumlah item harus lebih dari 0.');
                }

                if ($produk->stok < $jumlah) {
                    throw new RuntimeException('Stok produk '.$produk->nama_produk.' tidak mencukupi.');
                }

                $produk->stok -= $jumlah;
                $produk->save();

                if ($produk->stok < 100) {
                    $warnings[] = 'Stok menipis: '.$produk->nama_produk;
                }

                $hargaSatuan = (float) $produk->harga_jual;
                $subTotal += $hargaSatuan * $jumlah;

                $details[] = [
                    'id_produk' => $produk->id_produk,
                    'jumlah' => $jumlah,
                    'harga_satuan' => $hargaSatuan,
                ];
            }

            $ppn = isset($data['ppn']) ? (float) $data['ppn'] : 0.0;
            $totalBayar = $subTotal + $ppn;

            $transaksi = TransaksiPos::create([
                'id_kasir' => $data['id_kasir'],
                'id_anggota' => $data['id_anggota'] ?? null,
                'tanggal_jam' => $data['tanggal_jam'] ?? now(),
                'total_bayar' => $totalBayar,
                'ppn' => $ppn,
            ]);

            $savedDetails = [];
            foreach ($details as $detail) {
                $savedDetails[] = DetailTransaksi::create([
                    'id_transaksi' => $transaksi->id_transaksi,
                    'id_produk' => $detail['id_produk'],
                    'jumlah' => $detail['jumlah'],
                    'harga_satuan' => $detail['harga_satuan'],
                ]);
            }

            // Jurnal otomatis POS + HPP (wajib setelah detail transaksi tersimpan)
            $transaksiForJurnal = $transaksi->fresh()->load(['kasir', 'detailTransaksi.produk']);
            app(JurnalService::class)->catatTransaksiPosDanHpp($transaksiForJurnal);

            DB::commit();

            return [
                'transaksi' => $transaksi->load(['kasir.cabang', 'detailTransaksi.produk']),
                'details' => $savedDetails,
                'warnings' => $warnings,
            ];
        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }
    }
}
