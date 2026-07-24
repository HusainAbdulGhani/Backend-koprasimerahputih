<?php

namespace Database\Seeders;

use App\Models\Kasir;
use App\Models\Anggota;
use App\Models\Produk;
use App\Services\TransactionService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class DemoTransaksiPosSeeder extends Seeder
{
    public function run(): void
    {
        $kasir = Kasir::first();
        $anggota = Anggota::first();
        
        if (!$kasir) {
            $this->command->warn('Kasir tidak ditemukan.');
            return;
        }

        // Ambil beberapa produk dari cabang kasir ini
        $produks = Produk::where('id_cabang', $kasir->id_cabang)->inRandomOrder()->limit(5)->get();
        
        if ($produks->isEmpty()) {
            $this->command->warn('Produk tidak ditemukan di cabang kasir ini.');
            return;
        }

        $transactionService = app(TransactionService::class);
        $totalTransaksi = 15; // Buat 15 transaksi

        for ($i = 0; $i < $totalTransaksi; $i++) {
            // Pilih 1 - 3 produk secara acak untuk dibeli
            $items = [];
            $numItems = rand(1, 3);
            $selectedProduks = $produks->random($numItems);
            
            foreach ($selectedProduks as $prod) {
                $items[] = [
                    'id_produk' => $prod->id_produk,
                    'jumlah' => rand(1, 3), // Beli 1 sampai 3 pcs
                ];
            }

            // Transaksi menggunakan anggota jika genap, kasir saja jika ganjil
            $useAnggota = ($i % 2 === 0) && $anggota;
            
            $data = [
                'id_kasir' => $kasir->id_kasir,
                'id_anggota' => $useAnggota ? $anggota->id_anggota : null,
                'items' => $items,
                'tanggal_jam' => Carbon::now()->subDays(rand(0, 30))->subHours(rand(1, 10)),
                'poin_yang_diredeem' => 0,
            ];

            // Coba redeem poin kadang-kadang jika poin cukup
            if ($useAnggota && $anggota && $anggota->poin >= 100 && rand(1, 10) > 8) {
                $data['poin_yang_diredeem'] = 100; // Redeem 100 poin
            }

            try {
                $transactionService->checkout($data);
                
                // Refresh anggota poin in memory
                if ($useAnggota) {
                    $anggota->refresh();
                }
            } catch (\Exception $e) {
                Log::warning('Gagal seed transaksi POS: ' . $e->getMessage());
            }
        }

        $this->command->info("Berhasil mencoba membuat {$totalTransaksi} Transaksi POS (lihat log jika ada yang gagal karena stok).");
    }
}
