<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cabangs', function (Blueprint $table) {
            $table->id('id_cabang');
            $table->string('nama_cabang');
            $table->string('lokasi');
        });

        Schema::create('admins', function (Blueprint $table) {
            $table->id('id_admin');
            $table->unsignedBigInteger('id_account')->unique();
            $table->string('nama_admin');

            $table->foreign('id_account')->references('id_account')->on('accounts')->cascadeOnDelete();
        });

        Schema::create('pengurus', function (Blueprint $table) {
            $table->id('id_pengurus');
            $table->unsignedBigInteger('id_account')->unique();
            $table->string('nama_pengurus');
            $table->string('nip')->unique();
            $table->unsignedBigInteger('id_cabang');

            $table->foreign('id_account')->references('id_account')->on('accounts')->cascadeOnDelete();
            $table->foreign('id_cabang')->references('id_cabang')->on('cabangs')->cascadeOnDelete();
        });

        Schema::create('kasirs', function (Blueprint $table) {
            $table->id('id_kasir');
            $table->unsignedBigInteger('id_account')->unique();
            $table->string('nama_kasir');
            $table->unsignedBigInteger('id_cabang');

            $table->foreign('id_account')->references('id_account')->on('accounts')->cascadeOnDelete();
            $table->foreign('id_cabang')->references('id_cabang')->on('cabangs')->cascadeOnDelete();
        });

        Schema::create('gudangs', function (Blueprint $table) {
            $table->id('id_gudang');
            $table->unsignedBigInteger('id_account')->unique();
            $table->string('nama_petugas');
            $table->unsignedBigInteger('id_cabang');

            $table->foreign('id_account')->references('id_account')->on('accounts')->cascadeOnDelete();
            $table->foreign('id_cabang')->references('id_cabang')->on('cabangs')->cascadeOnDelete();
        });

        Schema::create('anggotas', function (Blueprint $table) {
            $table->id('id_anggota');
            $table->unsignedBigInteger('id_account')->unique();
            $table->string('nama_anggota');
            $table->string('alamat');
            $table->string('no_hp');
            $table->string('email')->unique();
            $table->date('tanggal_daftar');
            $table->enum('status', ['Calon', 'Aktif'])->default('Calon');
            $table->unsignedBigInteger('id_cabang');

            $table->foreign('id_account')->references('id_account')->on('accounts')->cascadeOnDelete();
            $table->foreign('id_cabang')->references('id_cabang')->on('cabangs')->cascadeOnDelete();
        });

        Schema::create('simpanans', function (Blueprint $table) {
            $table->id('id_simpanan');
            $table->unsignedBigInteger('id_anggota');
            $table->enum('jenis_simpanan', ['Pokok', 'Wajib', 'Sukarela']);
            $table->double('jumlah');
            $table->date('tanggal');

            $table->foreign('id_anggota')->references('id_anggota')->on('anggotas')->cascadeOnDelete();
        });

        Schema::create('pinjamans', function (Blueprint $table) {
            $table->id('id_pinjaman');
            $table->unsignedBigInteger('id_anggota');
            $table->unsignedBigInteger('id_pengurus_acc')->nullable();
            $table->double('jumlah_pinjaman');
            $table->double('biaya_operasional')->default(0);
            $table->enum('tenor', ['6', '12', '18', '24']);
            $table->date('tanggal_pengajuan');
            $table->enum('status', ['Pending', 'Approved', 'Rejected'])->default('Pending');

            $table->foreign('id_anggota')->references('id_anggota')->on('anggotas')->cascadeOnDelete();
            $table->foreign('id_pengurus_acc')->references('id_pengurus')->on('pengurus')->cascadeOnDelete();
        });

        Schema::create('angsurans', function (Blueprint $table) {
            $table->id('id_angsuran');
            $table->unsignedBigInteger('id_pinjaman');
            $table->double('jumlah_bayar');
            $table->date('tanggal_bayar');
            $table->string('bukti_transfer')->nullable();
            $table->enum('status', ['Pending', 'Verified', 'Rejected'])->default('Pending');
            $table->double('sisa_pinjaman');
        
            $table->foreign('id_pinjaman')->references('id_pinjaman')->on('pinjamans')->cascadeOnDelete();
        });

        Schema::create('produks', function (Blueprint $table) {
            $table->id('id_produk');
            $table->string('nama_produk');
            $table->double('harga_beli');
            $table->double('harga_jual');
            $table->integer('stok');
        });

        Schema::create('suppliers', function (Blueprint $table) {
            $table->id('id_supplier');
            $table->string('nama_supplier');
            $table->string('alamat');
        });

        Schema::create('usulan_stoks', function (Blueprint $table) {
            $table->id('id_usulan');
            $table->unsignedBigInteger('id_cabang');
            $table->unsignedBigInteger('id_produk');
            $table->unsignedBigInteger('id_gudang');
            $table->unsignedBigInteger('id_pengurus_acc')->nullable();
            $table->unsignedBigInteger('id_supplier');
            $table->integer('jumlah');
            $table->enum('status', ['Pending', 'ACC'])->default('Pending');
            $table->date('tanggal_usulan');

            $table->foreign('id_produk')->references('id_produk')->on('produks')->cascadeOnDelete();
            $table->foreign('id_gudang')->references('id_gudang')->on('gudangs')->cascadeOnDelete();
            $table->foreign('id_pengurus_acc')->references('id_pengurus')->on('pengurus')->cascadeOnDelete();
            $table->foreign('id_supplier')->references('id_supplier')->on('suppliers')->cascadeOnDelete();
        });

        Schema::create('transaksi_pos', function (Blueprint $table) {
            $table->id('id_transaksi');
            $table->unsignedBigInteger('id_kasir');
            $table->unsignedBigInteger('id_anggota')->nullable();
            $table->dateTime('tanggal_jam');
            $table->double('total_bayar');
            $table->double('ppn')->default(0);

            $table->foreign('id_kasir')->references('id_kasir')->on('kasirs')->cascadeOnDelete();
            $table->foreign('id_anggota')->references('id_anggota')->on('anggotas')->nullOnDelete();
        });

        Schema::create('detail_transaksi', function (Blueprint $table) {
            $table->id('id_detail');
            $table->unsignedBigInteger('id_transaksi');
            $table->unsignedBigInteger('id_produk');
            $table->integer('jumlah');
            $table->double('harga_satuan');

            $table->foreign('id_transaksi')->references('id_transaksi')->on('transaksi_pos')->cascadeOnDelete();
            $table->foreign('id_produk')->references('id_produk')->on('produks')->cascadeOnDelete();
        });

        Schema::create('akuns', function (Blueprint $table) {
            $table->id('id_akun');
            $table->string('nama_akun');
            $table->enum('jenis', ['Aset', 'Kewajiban', 'Modal', 'Pendapatan', 'Beban']);
        });

        Schema::create('jurnals', function (Blueprint $table) {
            $table->id('id_jurnal');
            $table->date('tanggal');
            $table->string('keterangan');
            $table->unsignedBigInteger('id_cabang');

            $table->foreign('id_cabang')->references('id_cabang')->on('cabangs')->cascadeOnDelete();
        });

        Schema::create('detail_jurnals', function (Blueprint $table) {
            $table->id('id_detail_jurnal');
            $table->unsignedBigInteger('id_jurnal');
            $table->unsignedBigInteger('id_akun');
            $table->double('debit')->default(0);
            $table->double('kredit')->default(0);

            $table->foreign('id_jurnal')->references('id_jurnal')->on('jurnals')->cascadeOnDelete();
            $table->foreign('id_akun')->references('id_akun')->on('akuns')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('detail_jurnals');
        Schema::dropIfExists('jurnals');
        Schema::dropIfExists('akuns');
        Schema::dropIfExists('detail_transaksi');
        Schema::dropIfExists('transaksi_pos');
        Schema::dropIfExists('usulan_stoks');
        Schema::dropIfExists('suppliers');
        Schema::dropIfExists('produks');
        Schema::dropIfExists('angsurans');
        Schema::dropIfExists('pinjamans');
        Schema::dropIfExists('simpanans');
        Schema::dropIfExists('anggotas');
        Schema::dropIfExists('gudangs');
        Schema::dropIfExists('kasirs');
        Schema::dropIfExists('pengurus');
        Schema::dropIfExists('admins');
        Schema::dropIfExists('cabangs');
    }
};
