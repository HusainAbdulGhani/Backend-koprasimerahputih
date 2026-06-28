<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('produks', function (Blueprint $table) {
            if (! Schema::hasColumn('produks', 'kategori')) {
                $table->string('kategori', 100)->nullable()->after('nama_produk')->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('produks', function (Blueprint $table) {
            if (Schema::hasColumn('produks', 'kategori')) {
                $table->dropIndex(['kategori']);
                $table->dropColumn('kategori');
            }
        });
    }
};
