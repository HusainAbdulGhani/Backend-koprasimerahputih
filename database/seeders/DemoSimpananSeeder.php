<?php

namespace Database\Seeders;

use App\Models\Anggota;
use App\Models\Simpanan;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class DemoSimpananSeeder extends Seeder
{
    public function run(): void
    {
        $anggota = Anggota::first();
        if (!$anggota) {
            $this->command->warn('Anggota tidak ditemukan. Jalankan DatabaseSeeder terlebih dahulu.');
            return;
        }

        // Tambah Riwayat Simpanan Wajib & Sukarela selama 3 bulan terakhir
        $start = Carbon::now()->subMonths(3);
        
        for ($i = 0; $i < 3; $i++) {
            $tanggal = $start->copy()->addMonths($i)->toDateString();
            
            // Simpanan Wajib Bulanan
            Simpanan::create([
                'id_anggota' => $anggota->id_anggota,
                'jenis_simpanan' => 'Wajib',
                'jumlah' => 50000,
                'tanggal' => $tanggal,
                'status' => 'Verified',
            ]);

            // Simpanan Sukarela
            Simpanan::create([
                'id_anggota' => $anggota->id_anggota,
                'jenis_simpanan' => 'Sukarela',
                'jumlah' => rand(10, 50) * 10000,
                'tanggal' => $tanggal,
                'status' => 'Verified',
            ]);
        }
        
        // Simpanan Pending
        Simpanan::create([
            'id_anggota' => $anggota->id_anggota,
            'jenis_simpanan' => 'Sukarela',
            'jumlah' => 100000,
            'tanggal' => now()->toDateString(),
            'status' => 'Pending',
        ]);
        
        // Simpanan Ditolak
        Simpanan::create([
            'id_anggota' => $anggota->id_anggota,
            'jenis_simpanan' => 'Sukarela',
            'jumlah' => 200000,
            'tanggal' => now()->subDays(5)->toDateString(),
            'status' => 'Rejected',
        ]);

        $this->command->info('Demo Simpanan berhasil di-seed.');
    }
}
