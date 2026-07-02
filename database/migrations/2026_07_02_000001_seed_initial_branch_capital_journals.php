<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $nominal = (float) config('koperasi.kas_awal_cabang', 50000000);
        if ($nominal <= 0) {
            return;
        }

        DB::table('akuns')->updateOrInsert(
            ['nama_akun' => 'Kas'],
            ['jenis' => 'Aset']
        );
        DB::table('akuns')->updateOrInsert(
            ['nama_akun' => 'Modal Awal Koperasi'],
            ['jenis' => 'Modal']
        );

        $kasId = DB::table('akuns')->where('nama_akun', 'Kas')->value('id_akun');
        $modalId = DB::table('akuns')->where('nama_akun', 'Modal Awal Koperasi')->value('id_akun');

        foreach (DB::table('cabangs')->select('id_cabang')->get() as $cabang) {
            $keterangan = 'Modal Awal Koperasi Cabang #'.$cabang->id_cabang;

            if (DB::table('jurnals')->where('id_cabang', $cabang->id_cabang)->where('keterangan', $keterangan)->exists()) {
                continue;
            }

            $idJurnal = DB::table('jurnals')->insertGetId([
                'tanggal' => now()->toDateString(),
                'keterangan' => $keterangan,
                'id_cabang' => $cabang->id_cabang,
            ]);

            DB::table('detail_jurnals')->insert([
                [
                    'id_jurnal' => $idJurnal,
                    'id_akun' => $kasId,
                    'debit' => $nominal,
                    'kredit' => 0,
                ],
                [
                    'id_jurnal' => $idJurnal,
                    'id_akun' => $modalId,
                    'debit' => 0,
                    'kredit' => $nominal,
                ],
            ]);
        }
    }

    public function down(): void
    {
        $jurnalIds = DB::table('jurnals')
            ->where('keterangan', 'like', 'Modal Awal Koperasi Cabang #%')
            ->pluck('id_jurnal');

        if ($jurnalIds->isEmpty()) {
            return;
        }

        DB::table('detail_jurnals')->whereIn('id_jurnal', $jurnalIds)->delete();
        DB::table('jurnals')->whereIn('id_jurnal', $jurnalIds)->delete();
    }
};
