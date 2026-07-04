<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('anggotas', function (Blueprint $table) {
            $table->integer('poin')->default(0)->after('status');
        });

        Schema::table('transaksi_pos', function (Blueprint $table) {
            $table->integer('poin_earned')->default(0);
            $table->integer('poin_redeemed')->default(0);
            $table->double('potongan_poin')->default(0);
        });

        Schema::create('riwayat_poin', function (Blueprint $table) {
            $table->id('id_riwayat');
            $table->unsignedBigInteger('id_anggota');
            $table->unsignedBigInteger('id_transaksi')->nullable();
            $table->enum('tipe', ['earn', 'redeem']);
            $table->integer('poin');
            $table->double('nilai_rupiah')->default(0);
            $table->dateTime('tanggal_jam');
            $table->string('keterangan')->nullable();

            $table->foreign('id_anggota')->references('id_anggota')->on('anggotas')->cascadeOnDelete();
            $table->foreign('id_transaksi')->references('id_transaksi')->on('transaksi_pos')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('riwayat_poin');

        Schema::table('transaksi_pos', function (Blueprint $table) {
            $table->dropColumn(['poin_earned', 'poin_redeemed', 'potongan_poin']);
        });

        Schema::table('anggotas', function (Blueprint $table) {
            $table->dropColumn('poin');
        });
    }
};
