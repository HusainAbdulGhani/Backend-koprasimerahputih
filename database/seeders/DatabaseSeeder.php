<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\Cabang;
use App\Models\Admin;
use App\Models\Pengurus;
use App\Models\Gudang;
use App\Models\Kasir;
use App\Models\Anggota;
use App\Models\Akun;
use App\Models\Produk;
use App\Models\Supplier;
use App\Models\Simpanan;
use App\Models\Pinjaman;
use App\Models\UsulanStok;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Buat Master Cabang sesuai dokumen
        // Bandung 5, Jakarta 5, Bekasi 5, Serang 3, Cilegon 2 = 20 cabang
        $cabangList = [];
        foreach (['Bandung' => 5, 'Jakarta' => 5, 'Bekasi' => 5, 'Serang' => 3, 'Cilegon' => 2] as $kota => $jumlah) {
            for ($i = 1; $i <= $jumlah; $i++) {
                $cabangList[] = [
                    'nama_cabang' => $kota.' '.$i,
                    'lokasi' => 'Kota '.$kota.' - Cabang '.$i,
                ];
            }
        }

        foreach ($cabangList as $row) {
            Cabang::firstOrCreate(
                ['nama_cabang' => $row['nama_cabang']],
                ['lokasi' => $row['lokasi']]
            );
        }

        // Default cabang untuk sample users
        $cabang = Cabang::where('nama_cabang', 'Bandung 1')->first()
            ?? Cabang::first();

        // 2. Buat Akun COA (Laci Akuntansi)
        $coa = [
            // Samakan dengan nama akun yang dipakai di JurnalService
            ['nama_akun' => 'Kas', 'jenis' => 'Aset'],
            ['nama_akun' => 'Penjualan', 'jenis' => 'Pendapatan'],
            ['nama_akun' => 'Piutang', 'jenis' => 'Aset'],
            ['nama_akun' => 'Simpanan Anggota', 'jenis' => 'Kewajiban'],
            ['nama_akun' => 'Pendapatan Biaya Operasional', 'jenis' => 'Pendapatan'],
            ['nama_akun' => 'Persediaan Barang', 'jenis' => 'Aset'],
            ['nama_akun' => 'HPP', 'jenis' => 'Beban'],
        ];
        foreach ($coa as $item) {
            Akun::firstOrCreate(
                ['nama_akun' => $item['nama_akun']],
                ['jenis' => $item['jenis']]
            );
        }

        // 3. Buat User Admin
        $accAdmin = Account::firstOrCreate(
            ['username' => 'admin_husain'],
            [
                'password' => Hash::make('password123'),
                'role' => 'Admin',
            ]
        );
        Admin::firstOrCreate(
            ['id_account' => $accAdmin->id_account],
            ['nama_admin' => 'Husain Abdul Ghani']
        );

        // 4. Buat User Pengurus (untuk approve pinjaman & usulan stok)
        $accPengurus = Account::firstOrCreate(
            ['username' => 'pengurus_koperasi'],
            [
                'password' => Hash::make('password123'),
                'role' => 'Pengurus',
            ]
        );
        $pengurus = Pengurus::firstOrCreate(
            ['id_account' => $accPengurus->id_account],
            [
                'nama_pengurus' => 'Dewi Pengurus',
                'nip' => 'PG-001',
                'id_cabang' => $cabang->id_cabang,
            ]
        );

        // 5. Buat User Gudang (untuk usulan stok)
        $accGudang = Account::firstOrCreate(
            ['username' => 'gudang_koperasi'],
            [
                'password' => Hash::make('password123'),
                'role' => 'Gudang',
            ]
        );
        $gudang = Gudang::firstOrCreate(
            ['id_account' => $accGudang->id_account],
            [
                'nama_petugas' => 'Rudi Gudang',
                'id_cabang' => $cabang->id_cabang,
            ]
        );

        // 6. Buat User Kasir (Untuk Test POS/Checkout)
        $accKasir = Account::firstOrCreate(
            ['username' => 'kasir_koperasi'],
            [
                'password' => Hash::make('password123'),
                'role' => 'Kasir',
            ]
        );
        Kasir::firstOrCreate(
            ['id_account' => $accKasir->id_account],
            [
                'nama_kasir' => 'Budi Kasir',
                'id_cabang' => $cabang->id_cabang,
            ]
        );

        // 7. Buat User Anggota (Untuk Test Simpanan & Pinjaman)
        $accAnggota = Account::firstOrCreate(
            ['username' => 'anggota_koperasi'],
            [
                'password' => Hash::make('password123'),
                'role' => 'Anggota',
            ]
        );
        $anggota = Anggota::firstOrCreate(
            ['email' => 'asep@example.com'],
            [
                'id_account' => $accAnggota->id_account,
                'nama_anggota' => 'Asep Anggota',
                'alamat' => 'Bandung Barat',
                'no_hp' => '082119300188',
                'tanggal_daftar' => now(),
                'status' => 'Aktif',
                'id_cabang' => $cabang->id_cabang,
            ]
        );

        // 8. Buat Produk Contoh
        $produkBeras = Produk::firstOrCreate(
            ['nama_produk' => 'Beras Merah Putih 5kg'],
            [
                'harga_beli' => 60000,
                'harga_jual' => 75000,
                'stok' => 150,
            ]
        );
        
        $produkMinyak = Produk::firstOrCreate(
            ['nama_produk' => 'Minyak Goreng 1L'],
            [
                'harga_beli' => 14000,
                'harga_jual' => 17000,
                'stok' => 50, // Trigger warning stok < 100
            ]
        );

        // 9. Supplier contoh (untuk modul usulan stok)
        $supplier = Supplier::firstOrCreate(
            ['nama_supplier' => 'PT Sumber Sembako Sejahtera'],
            ['alamat' => 'Jl. Raya Utama No. 10, Bandung']
        );

        // 10. Simpanan contoh untuk anggota (akan otomatis buat jurnal via observer)
        Simpanan::firstOrCreate([
            'id_anggota' => $anggota->id_anggota,
            'jenis_simpanan' => 'Wajib',
            'jumlah' => 100000,
            'tanggal' => now()->toDateString(),
        ]);

        // 11. Pinjaman contoh (status Pending) sesuai modul pinjaman
        Pinjaman::firstOrCreate([
            'id_anggota' => $anggota->id_anggota,
            'id_pengurus_acc' => $pengurus->id_pengurus,
            'jumlah_pinjaman' => 2000000,
            'biaya_operasional' => 2000000 * 0.02,
            'tenor' => '12',
            'tanggal_pengajuan' => now()->toDateString(),
            'status' => 'Pending',
        ]);

        // 12. Usulan stok contoh dari gudang ke pengurus
        UsulanStok::firstOrCreate(
            [
                'id_produk' => $produkMinyak->id_produk,
                'id_gudang' => $gudang->id_gudang,
                'id_pengurus_acc' => $pengurus->id_pengurus,
                'id_supplier' => $supplier->id_supplier,
            ],
            [
                'jumlah' => 200,
                'status' => 'Pending',
                'tanggal_usulan' => now()->toDateString(),
            ]
        );
    }
}