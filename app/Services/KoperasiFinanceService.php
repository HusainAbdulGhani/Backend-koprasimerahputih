<?php

namespace App\Services;

use App\Models\Angsuran;
use App\Models\DetailJurnal;
use App\Models\Pinjaman;

class KoperasiFinanceService
{
    public function kasTersedia(?int $idCabang = null): float
    {
        return $this->saldoAkun('Kas', $idCabang);
    }

    public function totalPinjamanAktif(?int $idCabang = null): float
    {
        $latestVerifiedSisa = Angsuran::query()
            ->select('sisa_pinjaman')
            ->whereColumn('angsurans.id_pinjaman', 'pinjamans.id_pinjaman')
            ->where('status', 'Verified')
            ->orderByDesc('id_angsuran')
            ->limit(1);

        $query = Pinjaman::query()
            ->where('status', 'Approved')
            ->addSelect([
                'id_pinjaman',
                'jumlah_pinjaman',
                'sisa_pinjaman_terakhir' => $latestVerifiedSisa,
            ]);

        if ($idCabang !== null) {
            $query->whereHas('anggota', fn ($q) => $q->where('id_cabang', $idCabang));
        }

        return (float) $query->get()->sum(function (Pinjaman $pinjaman) {
            $sisa = $pinjaman->sisa_pinjaman_terakhir;

            return (float) ($sisa === null ? $pinjaman->jumlah_pinjaman : $sisa);
        });
    }

    public function totalPinjamanDisetujui(?int $idCabang = null): float
    {
        $query = Pinjaman::query()->where('status', 'Approved');

        if ($idCabang !== null) {
            $query->whereHas('anggota', fn ($q) => $q->where('id_cabang', $idCabang));
        }

        return (float) $query->sum('jumlah_pinjaman');
    }

    public function saldoAkun(string $namaAkun, ?int $idCabang = null): float
    {
        $row = DetailJurnal::query()
            ->join('akuns', 'akuns.id_akun', '=', 'detail_jurnals.id_akun')
            ->join('jurnals', 'jurnals.id_jurnal', '=', 'detail_jurnals.id_jurnal')
            ->where('akuns.nama_akun', $namaAkun)
            ->when($idCabang !== null, fn ($query) => $query->where('jurnals.id_cabang', $idCabang))
            ->selectRaw('COALESCE(SUM(detail_jurnals.debit), 0) as debit, COALESCE(SUM(detail_jurnals.kredit), 0) as kredit')
            ->first();

        return (float) (($row?->debit ?? 0) - ($row?->kredit ?? 0));
    }
}
