<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('angsurans', function (Blueprint $table) {
            $table->double('pokok_bayar')->default(0)->after('jumlah_bayar');
            $table->double('fee_bayar')->default(0)->after('pokok_bayar');
        });
    }

    public function down(): void
    {
        Schema::table('angsurans', function (Blueprint $table) {
            $table->dropColumn(['pokok_bayar', 'fee_bayar']);
        });
    }
};

