<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('produks', function (Blueprint $table) {
            if (! Schema::hasColumn('produks', 'image_url')) {
                $table->string('image_url')->nullable()->after('nama_produk');
            }
        });
    }

    public function down(): void
    {
        Schema::table('produks', function (Blueprint $table) {
            if (Schema::hasColumn('produks', 'image_url')) {
                $table->dropColumn('image_url');
            }
        });
    }
};
