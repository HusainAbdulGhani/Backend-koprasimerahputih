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

    /**
     * Jurnal otomatis penjualan POS + HPP.
     * - Kas (D) vs Penjualan (K) sebesar total_bayar
     * - HPP (D) vs Persediaan Barang (K) sebesar total harga_beli item
     */
    public function catatTransaksiPosDanHpp(TransaksiPos $transaksiPos): void
    {
        $transaksiPos->loadMissing(['kasir', 'detailTransaksi.produk']);

        $kasir = $transaksiPos->kasir;
        if (! $kasir) {
            throw new RuntimeException('Kasir pada transaksi POS tidak ditemukan.');
        }

        // 1) Penjualan (Kas vs Penjualan)
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

        // 2) HPP (HPP vs Persediaan)
        $hpp = 0.0;
        foreach ($transaksiPos->detailTransaksi as $detail) {
            $produk = $detail->produk;
            if (! $produk) {
                continue;
            }

            $hpp += ((float) $produk->harga_beli) * ((int) $detail->jumlah);
        }

        if ($hpp > 0) {
            $this->buatJurnalDoubleEntry(
                idCabang: $kasir->id_cabang,
                tanggal: $transaksiPos->tanggal_jam,
                keterangan: 'HPP POS #'.$transaksiPos->id_transaksi,
                nominal: (float) $hpp,
                debitNamaAkun: 'HPP',
                debitJenis: 'Beban',
                kreditNamaAkun: 'Persediaan Barang',
                kreditJenis: 'Aset'
            );
        }
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

    public function catatPinjamanDisetujui(\App\Models\Pinjaman $pinjaman): void
    {
        // Ambil data anggota dari relasi pinjaman
        $anggota = $pinjaman->anggota;
        
        if (!$anggota) {
            throw new \RuntimeException('Data anggota tidak ditemukan pada pinjaman ini.');
        }

        $this->buatJurnalDoubleEntry(
            idCabang: $anggota->id_cabang,
            tanggal: now()->format('Y-m-d'), // Sesuaikan jika tabel pinjaman punya kolom tanggal sendiri
            keterangan: 'Pencairan Pinjaman #' . $pinjaman->id_pinjaman . ' - ' . $anggota->nama_anggota,
            nominal: (float) $pinjaman->jumlah_pinjaman,
            debitNamaAkun: 'Piutang',
            debitJenis: 'Aset',
            kreditNamaAkun: 'Kas',
            kreditJenis: 'Aset'
        );
    }
    public function catatAngsuranMasuk(\App\Models\Angsuran $angsuran): void
    {
        // Load relasi secara paksa biar nggak null
        $angsuran->loadMissing(['pinjaman.anggota']);

        $pinjaman = $angsuran->pinjaman;
        
        if (!$pinjaman) {
            throw new \RuntimeException("Data pinjaman untuk angsuran #{$angsuran->id_angsuran} tidak ditemukan.");
        }

        $anggota = $pinjaman->anggota;

        if (!$anggota) {
            throw new \RuntimeException("Data anggota untuk pinjaman #{$pinjaman->id_pinjaman} tidak ditemukan.");
        }

        $this->buatJurnalDoubleEntry(
            idCabang: $anggota->id_cabang,
            tanggal: $angsuran->tanggal_bayar,
            keterangan: 'Penerimaan Angsuran #' . $angsuran->id_angsuran . ' - ' . $anggota->nama_anggota,
            nominal: (float) $angsuran->jumlah_bayar,
            debitNamaAkun: 'Kas',
            debitJenis: 'Aset',
            kreditNamaAkun: 'Piutang',
            kreditJenis: 'Aset'
        );
    }

    /**
     * Catat penerimaan angsuran untuk nominal tertentu (pokok).
     * Dipakai saat pembayaran dipisah: pokok vs fee operasional.
     */
    public function catatAngsuranMasukNominal(\App\Models\Angsuran $angsuran, float $nominalPokok): void
    {
        if ($nominalPokok <= 0) {
            return;
        }

        $angsuran->loadMissing(['pinjaman.anggota']);
        $pinjaman = $angsuran->pinjaman;
        if (! $pinjaman) {
            throw new RuntimeException("Data pinjaman untuk angsuran #{$angsuran->id_angsuran} tidak ditemukan.");
        }

        $anggota = $pinjaman->anggota;
        if (! $anggota) {
            throw new RuntimeException("Data anggota untuk pinjaman #{$pinjaman->id_pinjaman} tidak ditemukan.");
        }

        $this->buatJurnalDoubleEntry(
            idCabang: $anggota->id_cabang,
            tanggal: $angsuran->tanggal_bayar,
            keterangan: 'Penerimaan Pokok Angsuran #' . $angsuran->id_angsuran . ' - ' . $anggota->nama_anggota,
            nominal: (float) $nominalPokok,
            debitNamaAkun: 'Kas',
            debitJenis: 'Aset',
            kreditNamaAkun: 'Piutang',
            kreditJenis: 'Aset'
        );
    }

    /**
     * Catat fee 2% sebagai pendapatan/biaya operasional koperasi.
     * Debit: Kas, Kredit: Pendapatan Biaya Operasional
     */
    public function catatBiayaOperasionalAngsuran(\App\Models\Angsuran $angsuran, float $nominalFee): void
    {
        if ($nominalFee <= 0) {
            return;
        }

        $angsuran->loadMissing(['pinjaman.anggota']);
        $pinjaman = $angsuran->pinjaman;
        if (! $pinjaman) {
            throw new RuntimeException("Data pinjaman untuk angsuran #{$angsuran->id_angsuran} tidak ditemukan.");
        }

        $anggota = $pinjaman->anggota;
        if (! $anggota) {
            throw new RuntimeException("Data anggota untuk pinjaman #{$pinjaman->id_pinjaman} tidak ditemukan.");
        }

        $this->buatJurnalDoubleEntry(
            idCabang: $anggota->id_cabang,
            tanggal: $angsuran->tanggal_bayar,
            keterangan: 'Fee Operasional Angsuran #' . $angsuran->id_angsuran . ' - ' . $anggota->nama_anggota,
            nominal: (float) $nominalFee,
            debitNamaAkun: 'Kas',
            debitJenis: 'Aset',
            kreditNamaAkun: 'Pendapatan Biaya Operasional',
            kreditJenis: 'Pendapatan'
        );
    }
}
