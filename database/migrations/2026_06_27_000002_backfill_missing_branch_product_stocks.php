<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $branches = DB::table('cabangs')->pluck('id_cabang');
        if ($branches->isEmpty()) {
            return;
        }

        DB::table('produks')
            ->select(['id_produk'])
            ->orderBy('id_produk')
            ->chunkById(200, function ($products) use ($branches) {
                $now = now();
                $rows = $products->flatMap(function ($product) use ($branches, $now) {
                    return $branches->map(fn ($branchId) => [
                        'id_cabang' => $branchId,
                        'id_produk' => $product->id_produk,
                        'stok' => 0,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                })->values()->all();

                DB::table('branch_product_stocks')->insertOrIgnore($rows);
            }, 'id_produk');
    }

    public function down(): void
    {
        DB::table('branch_product_stocks')->where('stok', 0)->delete();
    }
};
