<?php

namespace App\Services;

use App\Models\Akun;
use App\Models\DetailJurnal;
use App\Models\Jurnal;
use App\Models\Simpanan;
use App\Models\TransaksiPos;
use RuntimeException;

class JurnalService
{
    public function catatTransaksiPos(TransaksiPos $transaksiPos): void
    {
        $kasir = $transaksiPos->kasir;
        if (! $kasir) {
            throw new RuntimeException('Kasir pada transaksi POS tidak ditemukan.');
        }

        $this->buatJurnalDoubleEntry(
            idCabang: $kasir->id_cabang,
            tanggal: $transaksiPos->tanggal_jam,
            keterangan: 'Penjualan POS #'.$transaksiPos->id_transaksi,
            nominal: (float) $transaksiPos->total_bayar,
            debitNamaAkun: 'Kas',
            debitJenis: 'Aset',
            kreditNamaAkun: 'Penjualan',
            kreditJenis: 'Pendapatan'
        );
    }

    public function catatSimpananMasuk(Simpanan $simpanan): void
    {
        $anggota = $simpanan->anggota;
        if (! $anggota) {
            throw new RuntimeException('Anggota pada simpanan tidak ditemukan.');
        }

        $this->buatJurnalDoubleEntry(
            idCabang: $anggota->id_cabang,
            tanggal: $simpanan->tanggal,
            keterangan: 'Simpanan Anggota #'.$simpanan->id_simpanan,
            nominal: (float) $simpanan->jumlah,
            debitNamaAkun: 'Kas',
            debitJenis: 'Aset',
            kreditNamaAkun: 'Simpanan Anggota',
            kreditJenis: 'Kewajiban'
        );
    }

    private function buatJurnalDoubleEntry(
        int $idCabang,
        mixed $tanggal,
        string $keterangan,
        float $nominal,
        string $debitNamaAkun,
        string $debitJenis,
        string $kreditNamaAkun,
        string $kreditJenis
    ): void {
        $akunKasOrDebit = $this->ambilAtauBuatAkun($debitNamaAkun, $debitJenis);
        $akunPendapatanOrKredit = $this->ambilAtauBuatAkun($kreditNamaAkun, $kreditJenis);

        $jurnal = Jurnal::create([
            'tanggal' => $tanggal,
            'keterangan' => $keterangan,
            'id_cabang' => $idCabang,
        ]);

        DetailJurnal::create([
            'id_jurnal' => $jurnal->id_jurnal,
            'id_akun' => $akunKasOrDebit->id_akun,
            'debit' => $nominal,
            'kredit' => 0,
        ]);

        DetailJurnal::create([
            'id_jurnal' => $jurnal->id_jurnal,
            'id_akun' => $akunPendapatanOrKredit->id_akun,
            'debit' => 0,
            'kredit' => $nominal,
        ]);
    }

    private function ambilAtauBuatAkun(string $namaAkun, string $jenis): Akun
    {
        return Akun::firstOrCreate(
            ['nama_akun' => $namaAkun],
            ['jenis' => $jenis]
        );
    }
}
