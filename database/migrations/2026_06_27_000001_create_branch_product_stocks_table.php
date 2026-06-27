<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branch_product_stocks', function (Blueprint $table) {
            $table->id('id_branch_product_stock');
            $table->unsignedBigInteger('id_cabang');
            $table->unsignedBigInteger('id_produk');
            $table->integer('stok')->default(0);
            $table->timestamps();

            $table->unique(['id_cabang', 'id_produk'], 'branch_product_unique');
            $table->index(['id_produk', 'id_cabang']);
            $table->foreign('id_cabang')->references('id_cabang')->on('cabangs')->cascadeOnDelete();
            $table->foreign('id_produk')->references('id_produk')->on('produks')->cascadeOnDelete();
        });

        $branches = DB::table('cabangs')->pluck('id_cabang');
        if ($branches->isEmpty()) {
            return;
        }

        DB::table('produks')
            ->select(['id_produk', 'id_cabang', 'stok'])
            ->orderBy('id_produk')
            ->chunkById(200, function ($products) use ($branches) {
                $now = now();
                $rows = $products->flatMap(function ($product) use ($branches, $now) {
                    return $branches->map(fn ($branchId) => [
                        'id_cabang' => $branchId,
                        'id_produk' => $product->id_produk,
                        'stok' => (int) $branchId === (int) $product->id_cabang ? (int) ($product->stok ?? 0) : 0,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                })->values()->all();

                DB::table('branch_product_stocks')->upsert(
                    $rows,
                    ['id_cabang', 'id_produk'],
                    ['stok', 'updated_at']
                );
            }, 'id_produk');
    }

    public function down(): void
    {
        Schema::dropIfExists('branch_product_stocks');
    }
};
