<?php

namespace Database\Seeders;

use App\Models\Anggota;
use App\Models\Pengurus;
use App\Models\Pinjaman;
use App\Models\Angsuran;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class DemoPinjamanSeeder extends Seeder
{
    public function run(): void
    {
        $anggota = Anggota::first();
        $pengurus = Pengurus::first();
        
        if (!$anggota || !$pengurus) {
            $this->command->warn('Anggota atau Pengurus tidak ditemukan.');
            return;
        }

        // 1. Pinjaman Lunas
        $pinjamanLunas = Pinjaman::create([
            'id_anggota' => $anggota->id_anggota,
            'id_pengurus_acc' => $pengurus->id_pengurus,
            'jumlah_pinjaman' => 1200000,
            'biaya_operasional' => 24000,
            'tenor' => '6',
            'tanggal_pengajuan' => Carbon::now()->subMonths(7)->toDateString(),
            'status' => 'Approved',
        ]);
        
        $sisaLunas = 1200000;
        $feeRemainingLunas = 24000;
        
        for ($i = 1; $i <= 6; $i++) {
            $jumlahBayar = 204000;
            $feeBayar = min($feeRemainingLunas, $jumlahBayar);
            $pokokBayar = $jumlahBayar - $feeBayar;
            
            $feeRemainingLunas -= $feeBayar;
            $sisaLunas -= $pokokBayar;
            
            Angsuran::create([
                'id_pinjaman' => $pinjamanLunas->id_pinjaman,
                'jumlah_bayar' => $jumlahBayar,
                'pokok_bayar' => $pokokBayar,
                'fee_bayar' => $feeBayar,
                'sisa_pinjaman' => $sisaLunas,
                'tanggal_bayar' => Carbon::now()->subMonths(7 - $i)->toDateString(),
                'status' => 'Verified',
            ]);
        }

        // 2. Pinjaman Ditolak
        Pinjaman::create([
            'id_anggota' => $anggota->id_anggota,
            'id_pengurus_acc' => $pengurus->id_pengurus,
            'jumlah_pinjaman' => 10000000,
            'biaya_operasional' => 200000,
            'tenor' => '12',
            'tanggal_pengajuan' => Carbon::now()->subMonths(1)->toDateString(),
            'status' => 'Rejected',
        ]);

        // 3. Pinjaman Berjalan (Approved)
        $pinjamanBerjalan = Pinjaman::create([
            'id_anggota' => $anggota->id_anggota,
            'id_pengurus_acc' => $pengurus->id_pengurus,
            'jumlah_pinjaman' => 6000000,
            'biaya_operasional' => 120000,
            'tenor' => '12',
            'tanggal_pengajuan' => Carbon::now()->subMonths(3)->toDateString(),
            'status' => 'Approved',
        ]);
        
        $sisaBerjalan = 6000000;
        $feeRemainingBerjalan = 120000;
        $jumlahBayarBerjalan = 510000;
        
        // Sudah bayar 2 bulan
        for ($i = 1; $i <= 2; $i++) {
            $feeBayar = min($feeRemainingBerjalan, $jumlahBayarBerjalan);
            $pokokBayar = $jumlahBayarBerjalan - $feeBayar;
            
            $feeRemainingBerjalan -= $feeBayar;
            $sisaBerjalan -= $pokokBayar;
            
            Angsuran::create([
                'id_pinjaman' => $pinjamanBerjalan->id_pinjaman,
                'jumlah_bayar' => $jumlahBayarBerjalan,
                'pokok_bayar' => $pokokBayar,
                'fee_bayar' => $feeBayar,
                'sisa_pinjaman' => $sisaBerjalan,
                'tanggal_bayar' => Carbon::now()->subMonths(3 - $i)->toDateString(),
                'status' => 'Verified',
            ]);
        }
        
        // Angsuran Pending (Menunggu Verifikasi)
        $feeBayar = min($feeRemainingBerjalan, $jumlahBayarBerjalan);
        $pokokBayar = $jumlahBayarBerjalan - $feeBayar;
        $sisaPending = $sisaBerjalan - $pokokBayar;
            
        Angsuran::create([
            'id_pinjaman' => $pinjamanBerjalan->id_pinjaman,
            'jumlah_bayar' => $jumlahBayarBerjalan,
            'pokok_bayar' => $pokokBayar,
            'fee_bayar' => $feeBayar,
            'sisa_pinjaman' => $sisaPending,
            'tanggal_bayar' => Carbon::now()->toDateString(),
            'status' => 'Pending',
        ]);
        
        $this->command->info('Demo Pinjaman & Angsuran berhasil di-seed.');
    }
}
