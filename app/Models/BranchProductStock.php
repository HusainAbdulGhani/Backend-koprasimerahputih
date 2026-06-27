<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BranchProductStock extends Model
{
    protected $table = 'branch_product_stocks';
    protected $primaryKey = 'id_branch_product_stock';

    protected $fillable = [
        'id_cabang',
        'id_produk',
        'stok',
    ];

    public function cabang()
    {
        return $this->belongsTo(Cabang::class, 'id_cabang', 'id_cabang');
    }

    public function produk()
    {
        return $this->belongsTo(Produk::class, 'id_produk', 'id_produk');
    }
}
