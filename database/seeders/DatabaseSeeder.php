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
use App\Models\BranchProductStock;
use App\Services\JurnalService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        DB::beginTransaction();

        try {
            // Kosongkan seluruh tabel terkait (Truncate)
            Schema::disableForeignKeyConstraints();
            DB::table('detail_jurnals')->truncate();
            DB::table('jurnals')->truncate();
            DB::table('akuns')->truncate();
            DB::table('detail_transaksi')->truncate();
            DB::table('transaksi_pos')->truncate();
            DB::table('usulan_stoks')->truncate();
            DB::table('branch_product_stocks')->truncate();
            DB::table('produks')->truncate();
            DB::table('suppliers')->truncate();
            DB::table('angsurans')->truncate();
            DB::table('pinjamans')->truncate();
            DB::table('simpanans')->truncate();
            DB::table('riwayat_poin')->truncate();
            DB::table('anggotas')->truncate();
            DB::table('gudangs')->truncate();
            DB::table('kasirs')->truncate();
            DB::table('pengurus')->truncate();
            DB::table('admins')->truncate();
            DB::table('account_role')->truncate();
            DB::table('roles')->truncate();
            DB::table('accounts')->truncate();
            DB::table('cabangs')->truncate();
            Schema::enableForeignKeyConstraints();

            // Re-seed Roles
            foreach (['Admin', 'Pengurus', 'Kasir', 'Gudang', 'Anggota'] as $role) {
                DB::table('roles')->updateOrInsert(
                    ['name' => $role],
                    ['label' => $role]
                );
            }

            // Cabang List
            $regions = [
                'Bandung' => ['Barat', 'Timur', 'Utara', 'Selatan', 'Pusat'],
                'Jakarta' => ['Barat', 'Timur', 'Utara', 'Selatan', 'Pusat'],
                'Bekasi' => ['Barat', 'Timur', 'Utara', 'Selatan', 'Pusat'],
                'Serang' => ['Barat', 'Timur', 'Pusat'],
                'Cilegon' => ['Barat', 'Timur'],
            ];

            $cabangList = [];
            foreach ($regions as $kota => $subs) {
                foreach ($subs as $sub) {
                    $cabangList[] = [
                        'nama_cabang' => $kota . ' ' . $sub,
                        'kota' => $kota,
                        'lokasi' => 'Toko Koperasi Merah Putih - ' . $kota . ' ' . $sub,
                    ];
                }
            }

            foreach ($cabangList as $row) {
                Cabang::firstOrCreate(
                    ['nama_cabang' => $row['nama_cabang']],
                    ['kota' => $row['kota'], 'lokasi' => $row['lokasi']]
                );
            }

            // COA
            $coa = [
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

            // Modal Awal
            $modalAwal = (float) config('koperasi.kas_awal_cabang', 50000000);
            foreach (Cabang::all() as $cb) {
                $keterangan = 'Modal Awal Koperasi Cabang #' . $cb->id_cabang;
                app(JurnalService::class)->catatModalAwalCabang((int) $cb->id_cabang, $modalAwal, $keterangan);
            }

            // Admin
            $accAdmin = Account::firstOrCreate(
                ['username' => 'admin_husain'],
                ['password' => Hash::make('password123'), 'role' => 'Admin']
            );
            $accAdmin->syncRoles(['Admin']);
            Admin::firstOrCreate(
                ['id_account' => $accAdmin->id_account],
                ['nama_admin' => 'Admin Koperasi']
            );

            $cabang = Cabang::where('nama_cabang', 'Bandung Barat')->first() ?? Cabang::first();

            // Pengurus Utama
            $accPengurus = Account::firstOrCreate(
                ['username' => 'pengurus_koperasi'],
                ['password' => Hash::make('password123'), 'role' => 'Pengurus']
            );
            $accPengurus->syncRoles(['Pengurus']);
            $pengurus = Pengurus::firstOrCreate(
                ['id_account' => $accPengurus->id_account],
                [
                    'nama_pengurus' => 'Dewi Lestari',
                    'nip' => 'PG-001',
                    'id_cabang' => $cabang->id_cabang,
                ]
            );

            // Pengurus Cabang Lainnya
            $pengurusNames = [
                'Dewi Lestari', 'Ahmad Fauzan', 'Rina Marlina', 'Satria Nugraha', 'Maya Kartika',
                'Fajar Ramadhan', 'Nadia Putri', 'Yusuf Maulana', 'Intan Permata', 'Rizky Pratama',
                'Budi Setiawan', 'Indah Permatasari', 'Hendrawan', 'Sari Utami', 'Dedi Wijaya',
                'Taufik Hidayat', 'Lia Natalia', 'Eko Prasetyo', 'Mega Utami', 'Bambang Pamungkas'
            ];
            $pengurusIndex = 1;
            foreach (Cabang::where('id_cabang', '!=', $cabang->id_cabang)->orderBy('id_cabang')->get() as $cb) {
                $slug = strtolower(str_replace(' ', '_', $cb->nama_cabang));
                $accCabangPengurus = Account::firstOrCreate(
                    ['username' => 'pengurus_' . $slug],
                    ['password' => Hash::make('password123'), 'role' => 'Pengurus']
                );
                $accCabangPengurus->syncRoles(['Pengurus']);

                Pengurus::firstOrCreate(
                    ['id_account' => $accCabangPengurus->id_account],
                    [
                        'nama_pengurus' => $pengurusNames[$pengurusIndex % count($pengurusNames)],
                        'nip' => 'PG-' . str_pad((string) ($pengurusIndex + 1), 3, '0', STR_PAD_LEFT),
                        'id_cabang' => $cb->id_cabang,
                    ]
                );
                $pengurusIndex++;
            }

            // Gudang
            $accGudang = Account::firstOrCreate(
                ['username' => 'gudang_koperasi'],
                ['password' => Hash::make('password123'), 'role' => 'Gudang']
            );
            $accGudang->syncRoles(['Gudang']);
            $gudang = Gudang::firstOrCreate(
                ['id_account' => $accGudang->id_account],
                [
                    'nama_petugas' => 'Rudi Gudang',
                    'id_cabang' => $cabang->id_cabang,
                ]
            );

            // Kasir (2 Kasir per Cabang)
            $kasirPerCabang = (int) config('koperasi.kasir_per_cabang', 2);
            $kasirNames = [
                'Andi Saputra', 'Siti Amelia', 'Budi Santoso', 'Nabila Azzahra', 'Dimas Prakoso',
                'Putri Maharani', 'Rizal Hakim', 'Laras Wulandari', 'Yoga Firmansyah', 'Citra Anjani',
                'Teguh Wicaksono', 'Aulia Rahma', 'Heri Setiawan', 'Diana Putri', 'Fandi Ahmad',
                'Wulan Sari', 'Roni Wijaya', 'Gita Lestari', 'Deni Ramadhan', 'Santi Astuti',
                'Asep Sunandar', 'Neng Siti', 'Cecep Supriatna', 'Euis Marlina', 'Ujang Komarudin'
            ];
            $kasirIndex = 0;
            foreach (Cabang::all() as $cb) {
                for ($k = 1; $k <= $kasirPerCabang; $k++) {
                    $slug = strtolower(str_replace(' ', '_', $cb->nama_cabang));
                    $username = 'kasir_' . $slug . '_' . $k;

                    $accKasir = Account::firstOrCreate(
                        ['username' => $username],
                        ['password' => Hash::make('password123'), 'role' => 'Kasir']
                    );
                    $accKasir->syncRoles(['Kasir']);

                    Kasir::firstOrCreate(
                        ['id_account' => $accKasir->id_account],
                        [
                            'nama_kasir' => $kasirNames[$kasirIndex % count($kasirNames)],
                            'id_cabang' => $cb->id_cabang,
                        ]
                    );
                    $kasirIndex++;
                }
            }

            // Anggota
            $accAnggota = Account::firstOrCreate(
                ['username' => 'anggota_koperasi'],
                ['password' => Hash::make('password123'), 'role' => 'Anggota']
            );
            $accAnggota->syncRoles(['Anggota']);
            $anggota = Anggota::firstOrCreate(
                ['email' => 'asep@example.com'],
                [
                    'id_account' => $accAnggota->id_account,
                    'nomor_anggota' => 'AGT-' . $cabang->id_cabang . '-000001',
                    'nama_anggota' => 'Asep Anggota',
                    'alamat' => 'Bandung Barat',
                    'no_hp' => '082119300188',
                    'tanggal_daftar' => now(),
                    'status' => 'Aktif',
                    'id_cabang' => $cabang->id_cabang,
                ]
            );

            // Supplier & Produk (Min. 10 Supplier, 5 Produk per Supplier)
            $supplierData = [
                [
                    'nama_supplier' => 'PT Sumber Sembako Sejahtera',
                    'alamat' => 'Jl. Raya Utama No. 10, Bandung',
                    'kategori' => 'Bahan Pokok',
                    'produk' => [
                        ['nama' => 'Beras Merah Putih 5kg', 'harga_beli' => 60000, 'harga_jual' => 75000, 'stok' => 150],
                        ['nama' => 'Gula Pasir Premium 1kg', 'harga_beli' => 12500, 'harga_jual' => 15000, 'stok' => 100],
                        ['nama' => 'Garam Dapur Yodium 500g', 'harga_beli' => 3000, 'harga_jual' => 4500, 'stok' => 80],
                        ['nama' => 'Minyak Goreng Sawit 1L', 'harga_beli' => 14000, 'harga_jual' => 17000, 'stok' => 120],
                        ['nama' => 'Tepung Terigu Serbaguna 1kg', 'harga_beli' => 9500, 'harga_jual' => 12000, 'stok' => 110],
                    ]
                ],
                [
                    'nama_supplier' => 'CV Berkat Minyak Nusantara',
                    'alamat' => 'Jl. Industri No. 25, Jakarta',
                    'kategori' => 'Minyak & Mentega',
                    'produk' => [
                        ['nama' => 'Minyak Kelapa Murni 1L', 'harga_beli' => 28000, 'harga_jual' => 35000, 'stok' => 60],
                        ['nama' => 'Mentega Gurih Khas 250g', 'harga_beli' => 8000, 'harga_jual' => 10500, 'stok' => 100],
                        ['nama' => 'Margarin Serbaguna 200g', 'harga_beli' => 4500, 'harga_jual' => 6000, 'stok' => 150],
                        ['nama' => 'Minyak Jagung Sehat 1L', 'harga_beli' => 32000, 'harga_jual' => 40000, 'stok' => 50],
                        ['nama' => 'Minyak Wijen Wangi 250ml', 'harga_beli' => 18000, 'harga_jual' => 24000, 'stok' => 40],
                    ]
                ],
                [
                    'nama_supplier' => 'PT Pangan Makmur Abadi',
                    'alamat' => 'Kawasan Industri Jababeka, Bekasi',
                    'kategori' => 'Makanan Instan',
                    'produk' => [
                        ['nama' => 'Mie Instan Rasa Kaldu Ayam', 'harga_beli' => 2200, 'harga_jual' => 3000, 'stok' => 500],
                        ['nama' => 'Kecap Manis Kental 520ml', 'harga_beli' => 15000, 'harga_jual' => 19000, 'stok' => 120],
                        ['nama' => 'Saus Sambal Pedas 340ml', 'harga_beli' => 11000, 'harga_jual' => 14500, 'stok' => 100],
                        ['nama' => 'Sarden Saus Tomat 155g', 'harga_beli' => 7500, 'harga_jual' => 9500, 'stok' => 200],
                        ['nama' => 'Kornet Sapi Kaleng 340g', 'harga_beli' => 22000, 'harga_jual' => 28000, 'stok' => 80],
                    ]
                ],
                [
                    'nama_supplier' => 'UD Sinar Jaya Telur',
                    'alamat' => 'Jl. Pasar Baru No. 4, Serang',
                    'kategori' => 'Produk Ternak',
                    'produk' => [
                        ['nama' => 'Telur Ayam Negeri 1kg', 'harga_beli' => 22000, 'harga_jual' => 26000, 'stok' => 200],
                        ['nama' => 'Telur Bebek Asin 6 Butir', 'harga_beli' => 15000, 'harga_jual' => 19000, 'stok' => 50],
                        ['nama' => 'Telur Puyuh Pilihan 500g', 'harga_beli' => 18000, 'harga_jual' => 22000, 'stok' => 40],
                        ['nama' => 'Telur Ayam Kampung 10 Butir', 'harga_beli' => 20000, 'harga_jual' => 25000, 'stok' => 60],
                        ['nama' => 'Telur Ayam Omega 10 Butir', 'harga_beli' => 25000, 'harga_jual' => 31000, 'stok' => 70],
                    ]
                ],
                [
                    'nama_supplier' => 'PT Selera Rempah Indonesia',
                    'alamat' => 'Jl. Jenderal Sudirman No. 8, Cilegon',
                    'kategori' => 'Rempah-Rempah',
                    'produk' => [
                        ['nama' => 'Lada Putih Bubuk 100g', 'harga_beli' => 9000, 'harga_jual' => 12000, 'stok' => 100],
                        ['nama' => 'Ketumbar Bubuk Halus 100g', 'harga_beli' => 5000, 'harga_jual' => 7500, 'stok' => 100],
                        ['nama' => 'Kayu Manis Batang 50g', 'harga_beli' => 8000, 'harga_jual' => 11000, 'stok' => 50],
                        ['nama' => 'Bawang Merah Kupas 500g', 'harga_beli' => 18000, 'harga_jual' => 24000, 'stok' => 80],
                        ['nama' => 'Bawang Putih Kupas 500g', 'harga_beli' => 15000, 'harga_jual' => 20000, 'stok' => 90],
                    ]
                ],
                [
                    'nama_supplier' => 'CV Madu dan Susu Asli',
                    'alamat' => 'Jl. Lembang No. 42, Bandung',
                    'kategori' => 'Susu & Madu',
                    'produk' => [
                        ['nama' => 'Susu Kental Manis 370g', 'harga_beli' => 9000, 'harga_jual' => 11500, 'stok' => 150],
                        ['nama' => 'Susu UHT Full Cream 1L', 'harga_beli' => 14000, 'harga_jual' => 18000, 'stok' => 100],
                        ['nama' => 'Madu Hutan Murni 250ml', 'harga_beli' => 35000, 'harga_jual' => 45000, 'stok' => 50],
                        ['nama' => 'Mentega Premium Lembut 200g', 'harga_beli' => 12000, 'harga_jual' => 16000, 'stok' => 80],
                        ['nama' => 'Susu Bubuk Cokelat 400g', 'harga_beli' => 32000, 'harga_jual' => 40000, 'stok' => 70],
                    ]
                ],
                [
                    'nama_supplier' => 'PT Beras Cianjur Utama',
                    'alamat' => 'Jl. Pasar Induk Cipinang, Jakarta',
                    'kategori' => 'Beras',
                    'produk' => [
                        ['nama' => 'Beras Pandan Wangi 5kg', 'harga_beli' => 70000, 'harga_jual' => 88000, 'stok' => 120],
                        ['nama' => 'Beras Ketan Putih 1kg', 'harga_beli' => 14000, 'harga_jual' => 18500, 'stok' => 80],
                        ['nama' => 'Beras Premium Cianjur 10kg', 'harga_beli' => 135000, 'harga_jual' => 165000, 'stok' => 90],
                        ['nama' => 'Beras Hitam Organik 1kg', 'harga_beli' => 22000, 'harga_jual' => 29000, 'stok' => 40],
                        ['nama' => 'Beras Basmati Pilihan 1kg', 'harga_beli' => 28000, 'harga_jual' => 37000, 'stok' => 50],
                    ]
                ],
                [
                    'nama_supplier' => 'UD Sabun & Kebersihan Lestari',
                    'alamat' => 'Jl. Industri No. 12, Bekasi',
                    'kategori' => 'Kebersihan & Mandi',
                    'produk' => [
                        ['nama' => 'Sabun Mandi Cair 450ml', 'harga_beli' => 18000, 'harga_jual' => 23000, 'stok' => 100],
                        ['nama' => 'Shampoo Anti Ketombe 170ml', 'harga_beli' => 15000, 'harga_jual' => 19500, 'stok' => 100],
                        ['nama' => 'Sabun Cuci Piring Cair 700ml', 'harga_beli' => 11000, 'harga_jual' => 14000, 'stok' => 150],
                        ['nama' => 'Detergen Bubuk Harum 800g', 'harga_beli' => 16000, 'harga_jual' => 21000, 'stok' => 120],
                        ['nama' => 'Pembersih Lantai Wangi 750ml', 'harga_beli' => 9000, 'harga_jual' => 12000, 'stok' => 100],
                    ]
                ],
                [
                    'nama_supplier' => 'PT Kopi & Teh Nusantara',
                    'alamat' => 'Jl. Pahlawan No. 15, Serang',
                    'kategori' => 'Minuman',
                    'produk' => [
                        ['nama' => 'Kopi Bubuk Arabika 250g', 'harga_beli' => 25000, 'harga_jual' => 32500, 'stok' => 80],
                        ['nama' => 'Teh Celup Melati 25 Pcs', 'harga_beli' => 4000, 'harga_jual' => 6000, 'stok' => 200],
                        ['nama' => 'Kopi Hitam Robusta 250g', 'harga_beli' => 18000, 'harga_jual' => 24000, 'stok' => 100],
                        ['nama' => 'Teh Hijau Organik 50g', 'harga_beli' => 12000, 'harga_jual' => 16500, 'stok' => 60],
                        ['nama' => 'Kopi Susu Instan 10 Sachet', 'harga_beli' => 10000, 'harga_jual' => 13500, 'stok' => 150],
                    ]
                ],
                [
                    'nama_supplier' => 'CV Tepung Terigu Nusantara',
                    'alamat' => 'Jl. Margonda Raya No. 4, Cilegon',
                    'kategori' => 'Bahan Kue & Tepung',
                    'produk' => [
                        ['nama' => 'Tepung Terigu Protein Tinggi 1kg', 'harga_beli' => 11000, 'harga_jual' => 14000, 'stok' => 120],
                        ['nama' => 'Tepung Tapioka Pilihan 1kg', 'harga_beli' => 9000, 'harga_jual' => 12000, 'stok' => 100],
                        ['nama' => 'Tepung Beras Halus 500g', 'harga_beli' => 5000, 'harga_jual' => 7000, 'stok' => 100],
                        ['nama' => 'Tepung Maizena Murni 250g', 'harga_beli' => 6000, 'harga_jual' => 8500, 'stok' => 80],
                        ['nama' => 'Tepung Bumbu Serbaguna 200g', 'harga_beli' => 3500, 'harga_jual' => 5000, 'stok' => 150],
                    ]
                ]
            ];

            $allProducts = [];
            foreach ($supplierData as $sup) {
                $supplierObj = Supplier::firstOrCreate(
                    ['nama_supplier' => $sup['nama_supplier']],
                    ['alamat' => $sup['alamat']]
                );

                foreach ($sup['produk'] as $p) {
                    $prod = Produk::firstOrCreate(
                        ['nama_produk' => $p['nama'], 'id_cabang' => $cabang->id_cabang],
                        [
                            'id_supplier' => $supplierObj->id_supplier,
                            'harga_beli' => $p['harga_beli'],
                            'harga_jual' => $p['harga_jual'],
                            'stok' => $p['stok'],
                            'kategori' => $sup['kategori'],
                        ]
                    );
                    $allProducts[] = $prod;
                }
            }

            // Sinkronisasi Stok Cabang (BranchProductStock)
            foreach (Cabang::all() as $cb) {
                foreach ($allProducts as $produk) {
                    BranchProductStock::updateOrCreate(
                        [
                            'id_cabang' => $cb->id_cabang,
                            'id_produk' => $produk->id_produk,
                        ],
                        [
                            'stok' => (int) $cb->id_cabang === (int) $produk->id_cabang ? (int) $produk->stok : 0,
                        ]
                    );
                }
            }

            // Simpanan Pokok & Wajib Anggota
            Simpanan::firstOrCreate([
                'id_anggota' => $anggota->id_anggota,
                'jenis_simpanan' => 'Pokok',
            ], [
                'jumlah' => 100000,
                'tanggal' => now()->toDateString(),
                'status' => 'Verified',
            ]);

            Simpanan::firstOrCreate([
                'id_anggota' => $anggota->id_anggota,
                'jenis_simpanan' => 'Wajib',
            ], [
                'jumlah' => 50000,
                'tanggal' => now()->toDateString(),
                'status' => 'Verified',
            ]);

            // Pinjaman Contoh
            Pinjaman::firstOrCreate(
                [
                    'id_anggota' => $anggota->id_anggota,
                    'jumlah_pinjaman' => 2000000,
                    'tanggal_pengajuan' => now()->toDateString(),
                ],
                [
                    'id_pengurus_acc' => null,
                    'biaya_operasional' => 2000000 * 0.02,
                    'tenor' => '12',
                    'status' => 'Pending',
                ]
            );

            // Usulan Stok Contoh
            if (count($allProducts) > 0) {
                $sampleProduct = $allProducts[0];
                $sampleSupplier = Supplier::find($sampleProduct->id_supplier);

                UsulanStok::firstOrCreate(
                    [
                        'id_produk' => $sampleProduct->id_produk,
                        'id_gudang' => $gudang->id_gudang,
                        'id_supplier' => $sampleSupplier->id_supplier,
                        'id_cabang' => $cabang->id_cabang,
                    ],
                    [
                        'id_pengurus_acc' => null,
                        'jumlah' => 200,
                        'harga_beli' => (float) $sampleProduct->harga_beli,
                        'status' => 'Pending',
                        'tanggal_usulan' => now()->toDateString(),
                    ]
                );
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }
}
