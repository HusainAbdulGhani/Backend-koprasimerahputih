<?php

namespace Database\Seeders;

use App\Models\Gudang;
use App\Models\Pengurus;
use App\Models\Produk;
use App\Models\UsulanStok;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class DemoUsulanStokSeeder extends Seeder
{
    public function run(): void
    {
        $gudang = Gudang::first();
        $pengurus = Pengurus::first();
        $produk = Produk::first();

        if (!$gudang || !$pengurus || !$produk) {
            $this->command->warn('Gudang, Pengurus, atau Produk tidak ditemukan.');
            return;
        }

        // Usulan Approved
        UsulanStok::create([
            'id_produk' => $produk->id_produk,
            'id_gudang' => $gudang->id_gudang,
            'id_supplier' => $produk->id_supplier,
            'id_cabang' => $gudang->id_cabang,
            'id_pengurus_acc' => $pengurus->id_pengurus,
            'jumlah' => 100,
            'harga_beli' => $produk->harga_beli,
            'status' => 'Approved',
            'tanggal_usulan' => Carbon::now()->subDays(10)->toDateString(),
        ]);

        // Usulan Rejected
        UsulanStok::create([
            'id_produk' => $produk->id_produk,
            'id_gudang' => $gudang->id_gudang,
            'id_supplier' => $produk->id_supplier,
            'id_cabang' => $gudang->id_cabang,
            'id_pengurus_acc' => $pengurus->id_pengurus,
            'jumlah' => 500, // Terlalu banyak
            'harga_beli' => $produk->harga_beli,
            'status' => 'Rejected',
            'tanggal_usulan' => Carbon::now()->subDays(5)->toDateString(),
        ]);
        
        // Usulan Pending 
        // (Di DatabaseSeeder sudah ada 1 usulan pending, kita tambahkan satu lagi untuk produk berbeda jika ada)
        $produkLain = Produk::where('id_produk', '!=', $produk->id_produk)->first();
        if ($produkLain) {
            UsulanStok::create([
                'id_produk' => $produkLain->id_produk,
                'id_gudang' => $gudang->id_gudang,
                'id_supplier' => $produkLain->id_supplier,
                'id_cabang' => $gudang->id_cabang,
                'id_pengurus_acc' => null,
                'jumlah' => 50,
                'harga_beli' => $produkLain->harga_beli,
                'status' => 'Pending',
                'tanggal_usulan' => Carbon::now()->toDateString(),
            ]);
        }

        $this->command->info('Demo Usulan Stok berhasil di-seed.');
    }
}
