<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('usulan_stoks', function (Blueprint $table) {
            $table->string('kode_usulan')->nullable()->after('id_usulan');
            $table->string('status_pengiriman')->nullable()->after('status');
            $table->timestamp('tanggal_approved')->nullable()->after('tanggal_usulan');
            $table->timestamp('tanggal_diterima')->nullable()->after('tanggal_approved');
            $table->string('alasan_penolakan')->nullable()->after('status_pengiriman');
            $table->index('kode_usulan');
        });

        DB::statement("ALTER TABLE usulan_stoks MODIFY status ENUM('Pending', 'Approved', 'Rejected') NOT NULL DEFAULT 'Pending'");
        DB::table('usulan_stoks')->whereNull('kode_usulan')->update(['kode_usulan' => DB::raw("CONCAT('LEGACY-', id_usulan)")]);
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE usulan_stoks MODIFY status ENUM('Pending', 'ACC') NOT NULL DEFAULT 'Pending'");
        Schema::table('usulan_stoks', function (Blueprint $table) {
            $table->dropIndex(['kode_usulan']);
            $table->dropColumn(['kode_usulan', 'status_pengiriman', 'tanggal_approved', 'tanggal_diterima', 'alasan_penolakan']);
        });
    }
};
