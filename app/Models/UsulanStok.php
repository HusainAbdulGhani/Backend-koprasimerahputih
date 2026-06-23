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
        'kode_usulan',
        'id_gudang',
        'id_supplier',
        'id_cabang',
        'jumlah',
        'harga_beli',
        'harga_jual',
        'status',
        'id_pengurus_acc', 
        'tanggal_usulan',
        'status_pengiriman',
        'tanggal_approved',
        'tanggal_diterima',
        'alasan_penolakan',
    ];

    protected function casts(): array
    {
        return [
            'tanggal_usulan' => 'date',
            'tanggal_approved' => 'datetime',
            'tanggal_diterima' => 'datetime',
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

    public function cabang()
    {
        return $this->belongsTo(Cabang::class, 'id_cabang', 'id_cabang');
    }
}
