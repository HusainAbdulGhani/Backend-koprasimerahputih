<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Memulai Demo Seeder (pastikan DatabaseSeeder sudah dijalankan sebelumnya)...');

        $this->call([
            DemoSimpananSeeder::class,
            DemoPinjamanSeeder::class,
            DemoUsulanStokSeeder::class,
            DemoTransaksiPosSeeder::class,
        ]);

        $this->command->info('Demo Seeder selesai dijalankan. Database siap untuk demo!');
    }
}
