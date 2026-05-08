<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
{
    protected $table = 'suppliers';
    protected $primaryKey = 'id_supplier';
    public $timestamps = false;

    protected $fillable = [
        'nama_supplier',
        'alamat',
    ];

    public function usulanStoks()
    {
        return $this->hasMany(UsulanStok::class, 'id_supplier', 'id_supplier');
    }
    public function produks()
    {
        return $this->hasMany(Produk::class, 'id_supplier', 'id_supplier');
    }
}
