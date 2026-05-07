<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UsulanStok extends Model
{
    protected $table = 'usulan_stoks';
    protected $primaryKey = 'id_usulan';
    public $timestamps = false;

    protected $fillable = [
        'id_produk',
        'id_gudang',
        'id_pengurus_acc',
        'id_supplier',
        'jumlah',
        'status',
        'tanggal_usulan',
    ];

    protected function casts(): array
    {
        return [
            'tanggal_usulan' => 'date',
        ];
    }

    public function produk()
    {
        return $this->belongsTo(Produk::class, 'id_produk', 'id_produk');
    }

    public function gudang()
    {
        return $this->belongsTo(Gudang::class, 'id_gudang', 'id_gudang');
    }

    public function pengurusAcc()
    {
        return $this->belongsTo(Pengurus::class, 'id_pengurus_acc', 'id_pengurus');
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class, 'id_supplier', 'id_supplier');
    }
}
