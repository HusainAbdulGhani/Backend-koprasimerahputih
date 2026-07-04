<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TransaksiPos extends Model
{
    protected $table = 'transaksi_pos';
    protected $primaryKey = 'id_transaksi';
    public $timestamps = false;

    protected $fillable = [
        'id_kasir',
        'id_anggota',
        'tanggal_jam',
        'total_bayar',
        'ppn',
        'poin_earned',
        'poin_redeemed',
        'potongan_poin',
    ];

    protected function casts(): array
    {
        return [
            'tanggal_jam' => 'datetime',
        ];
    }

    public function kasir()
    {
        return $this->belongsTo(Kasir::class, 'id_kasir', 'id_kasir');
    }

    public function anggota()
    {
        return $this->belongsTo(Anggota::class, 'id_anggota', 'id_anggota');
    }

    public function detailTransaksi()
    {
        return $this->hasMany(DetailTransaksi::class, 'id_transaksi', 'id_transaksi');
    }

    public function riwayatPoin()
    {
        return $this->hasMany(RiwayatPoin::class, 'id_transaksi', 'id_transaksi');
    }
}
