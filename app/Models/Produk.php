<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Produk extends Model
{
    protected $table = 'produks';
    protected $primaryKey = 'id_produk';
    public $timestamps = false;

    protected $fillable = [
        'nama_produk',
        'harga_beli',
        'harga_jual',
        'stok',
    ];

    public function detailTransaksi()
    {
        return $this->hasMany(DetailTransaksi::class, 'id_produk', 'id_produk');
    }

    public function usulanStoks()
    {
        return $this->hasMany(UsulanStok::class, 'id_produk', 'id_produk');
    }
}
