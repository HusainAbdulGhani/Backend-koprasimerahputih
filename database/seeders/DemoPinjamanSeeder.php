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
            'biaya_operasional' => 1200000 * 0.02,
            'tenor' => '6',
            'tanggal_pengajuan' => Carbon::now()->subMonths(7)->toDateString(),
            'status' => 'Lunas',
        ]);
        
        for ($i = 1; $i <= 6; $i++) {
            Angsuran::create([
                'id_pinjaman' => $pinjamanLunas->id_pinjaman,
                'angsuran_ke' => $i,
                'jumlah_bayar' => 200000,
                'sisa_pinjaman' => 1200000 - (200000 * $i),
                'tanggal_bayar' => Carbon::now()->subMonths(7 - $i)->toDateString(),
                'status' => 'Verified',
                'id_pengurus_acc' => $pengurus->id_pengurus,
            ]);
        }

        // 2. Pinjaman Ditolak
        Pinjaman::create([
            'id_anggota' => $anggota->id_anggota,
            'id_pengurus_acc' => $pengurus->id_pengurus,
            'jumlah_pinjaman' => 10000000,
            'biaya_operasional' => 10000000 * 0.02,
            'tenor' => '12',
            'tanggal_pengajuan' => Carbon::now()->subMonths(1)->toDateString(),
            'status' => 'Rejected',
        ]);

        // 3. Pinjaman Berjalan (Approved)
        $pinjamanBerjalan = Pinjaman::create([
            'id_anggota' => $anggota->id_anggota,
            'id_pengurus_acc' => $pengurus->id_pengurus,
            'jumlah_pinjaman' => 6000000,
            'biaya_operasional' => 6000000 * 0.02,
            'tenor' => '12',
            'tanggal_pengajuan' => Carbon::now()->subMonths(3)->toDateString(),
            'status' => 'Approved',
        ]);
        
        // Sudah bayar 2 bulan
        for ($i = 1; $i <= 2; $i++) {
            Angsuran::create([
                'id_pinjaman' => $pinjamanBerjalan->id_pinjaman,
                'angsuran_ke' => $i,
                'jumlah_bayar' => 500000,
                'sisa_pinjaman' => 6000000 - (500000 * $i),
                'tanggal_bayar' => Carbon::now()->subMonths(3 - $i)->toDateString(),
                'status' => 'Verified',
                'id_pengurus_acc' => $pengurus->id_pengurus,
            ]);
        }
        
        // Angsuran Pending (Menunggu Verifikasi)
        Angsuran::create([
            'id_pinjaman' => $pinjamanBerjalan->id_pinjaman,
            'angsuran_ke' => 3,
            'jumlah_bayar' => 500000,
            'sisa_pinjaman' => 6000000 - (500000 * 3),
            'tanggal_bayar' => Carbon::now()->toDateString(),
            'status' => 'Pending',
            'id_pengurus_acc' => null,
        ]);
        
        $this->command->info('Demo Pinjaman & Angsuran berhasil di-seed.');
    }
}
