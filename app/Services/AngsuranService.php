<?php

namespace App\Services;

use App\Models\Angsuran;
use App\Models\Pinjaman;
use Illuminate\Support\Facades\DB;
use Exception;

class AngsuranService
{
    public function bayarAngsuran(array $data): Angsuran
    {
        // Pake Transaction biar aman (Atomic)
        return DB::transaction(function () use ($data) {
            
            // 1. Cari data angsuran yang mau dibayar
            $angsuran = Angsuran::find($data['id_angsuran']);
            if (!$angsuran) {
                throw new Exception('Data angsuran tidak ditemukan');
            }

            // 2. Ambil data pinjaman terkait (pake Lock biar ga bentrok)
            $pinjaman = Pinjaman::where('id_pinjaman', $angsuran->id_pinjaman)->lockForUpdate()->first();
            if (! $pinjaman) {
                throw new Exception('Data pinjaman tidak ditemukan');
            }

            // 2b. Hitung sisa pinjaman saat ini dari angsuran verified terakhir
            $lastVerified = Angsuran::where('id_pinjaman', $pinjaman->id_pinjaman)
                ->where('status', 'Verified')
                ->orderByDesc('id_angsuran')
                ->first();
            $saldoSaatIni = $lastVerified ? (float) $lastVerified->sisa_pinjaman : (float) $pinjaman->jumlah_pinjaman;

            if ($saldoSaatIni <= 0) {
                throw new Exception('Pinjaman sudah lunas, tidak bisa menerima pembayaran lagi');
            }
            
            // 3. Update data angsuran
            $jumlahBayar = (float) $data['jumlah_bayar'];
            if ($jumlahBayar <= 0) {
                throw new Exception('Jumlah bayar harus lebih dari 0');
            }

            // 4. Biaya operasional = 2% dari total pinjaman yang diajukan (sesuai dokumen)
            // Cara eksekusi: setiap pembayaran angsuran akan dialokasikan untuk melunasi fee terlebih dahulu
            // sampai total fee terkumpul = biaya_operasional, sisanya baru mengurangi pokok.
            $totalFee = round(((float) $pinjaman->jumlah_pinjaman) * 0.02, 2);

            $feeSudahDibayar = (float) Angsuran::where('id_pinjaman', $pinjaman->id_pinjaman)
                ->where('status', 'Verified')
                ->sum('fee_bayar');
            $feeSisa = max(0.0, $totalFee - $feeSudahDibayar);

            $feeBayar = min($feeSisa, $jumlahBayar);
            $pokokBayar = $jumlahBayar - $feeBayar;

            // 5. Validasi overpay: total yang boleh dibayar = sisa pokok + sisa fee
            if ($jumlahBayar > ($saldoSaatIni + $feeSisa)) {
                throw new Exception('Pembayaran melebihi total kewajiban (pokok + biaya operasional).');
            }

            $sisaBaru = $saldoSaatIni - $pokokBayar;

            $angsuran->jumlah_bayar = $jumlahBayar;
            $angsuran->pokok_bayar = $pokokBayar;
            $angsuran->fee_bayar = $feeBayar;
            $angsuran->tanggal_bayar = $data['tanggal_bayar'] ?? now()->format('Y-m-d');
            $angsuran->status = 'Verified'; // Otomatis verified atau butuh approve?
            $angsuran->sisa_pinjaman = $sisaBaru;
            $angsuran->save();

            // 6. Pastikan pinjaman menyimpan nilai fee total (bukan akumulasi per bayar)
            if ((float) ($pinjaman->biaya_operasional ?? 0) !== $totalFee) {
                $pinjaman->biaya_operasional = $totalFee;
                $pinjaman->save();
            }

            // 7. LOGIC BISNIS: Cek kalau sudah lunas (status bisa berbeda tergantung enum DB)
            if ($sisaBaru <= 0) {
                // Best effort: kalau DB mendukung status 'Lunas', ini akan sukses
                // Kalau tidak, transaksi tetap valid karena sisa_pinjaman sudah 0 di tabel angsurans
                try {
                    $pinjaman->update(['status' => 'Lunas']);
                } catch (\Throwable $e) {
                    // ignore
                }
            }

            // 8. AUTOMATION: Catat ke Jurnal Akuntansi
            // Pisah jurnal pokok (Kas vs Piutang) dan fee (Kas vs Pendapatan/Biaya Operasional)
            /** @var JurnalService $jurnal */
            $jurnal = app(JurnalService::class);
            if ($pokokBayar > 0) {
                $jurnal->catatAngsuranMasukNominal($angsuran, $pokokBayar);
            }
            if ($feeBayar > 0) {
                $jurnal->catatBiayaOperasionalAngsuran($angsuran, $feeBayar);
            }

            return $angsuran;
        });
    }
}