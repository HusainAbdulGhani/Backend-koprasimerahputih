<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('anggotas', function (Blueprint $table) {
            $table->index(['id_cabang', 'status'], 'anggotas_cabang_status_index');
        });

        Schema::table('simpanans', function (Blueprint $table) {
            $table->index(['id_anggota', 'status', 'tanggal'], 'simpanans_member_status_date_index');
        });

        Schema::table('pinjamans', function (Blueprint $table) {
            $table->index(['id_anggota', 'status', 'tanggal_pengajuan'], 'pinjamans_member_status_date_index');
        });

        Schema::table('angsurans', function (Blueprint $table) {
            $table->index(['id_pinjaman', 'status', 'tanggal_bayar'], 'angsurans_loan_status_date_index');
        });

        Schema::table('produks', function (Blueprint $table) {
            $table->index(['id_cabang', 'stok'], 'produks_cabang_stock_index');
        });

        Schema::table('usulan_stoks', function (Blueprint $table) {
            $table->index(['id_cabang', 'status', 'tanggal_usulan'], 'usulan_stoks_cabang_status_date_index');
        });
    }

    public function down(): void
    {
        Schema::table('usulan_stoks', fn (Blueprint $table) => $table->dropIndex('usulan_stoks_cabang_status_date_index'));
        Schema::table('produks', fn (Blueprint $table) => $table->dropIndex('produks_cabang_stock_index'));
        Schema::table('angsurans', fn (Blueprint $table) => $table->dropIndex('angsurans_loan_status_date_index'));
        Schema::table('pinjamans', fn (Blueprint $table) => $table->dropIndex('pinjamans_member_status_date_index'));
        Schema::table('simpanans', fn (Blueprint $table) => $table->dropIndex('simpanans_member_status_date_index'));
        Schema::table('anggotas', fn (Blueprint $table) => $table->dropIndex('anggotas_cabang_status_index'));
    }
};
