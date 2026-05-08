<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('usulan_stoks', function (Blueprint $table) {
            if (! Schema::hasColumn('usulan_stoks', 'id_cabang')) {
                $table->unsignedBigInteger('id_cabang')->nullable()->after('id_supplier');
                $table->foreign('id_cabang')->references('id_cabang')->on('cabangs')->nullOnDelete();
            }

            if (! Schema::hasColumn('usulan_stoks', 'harga_beli')) {
                $table->double('harga_beli')->default(0)->after('jumlah');
            }
        });
    }

    public function down(): void
    {
        Schema::table('usulan_stoks', function (Blueprint $table) {
            if (Schema::hasColumn('usulan_stoks', 'id_cabang')) {
                $table->dropForeign(['id_cabang']);
                $table->dropColumn('id_cabang');
            }

            if (Schema::hasColumn('usulan_stoks', 'harga_beli')) {
                $table->dropColumn('harga_beli');
            }
        });
    }
};

