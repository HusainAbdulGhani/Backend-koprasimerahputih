<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RiwayatPoin extends Model
{
    protected $table = 'riwayat_poin';
    protected $primaryKey = 'id_riwayat';
    public $timestamps = false;

    protected $fillable = [
        'id_anggota',
        'id_transaksi',
        'tipe',
        'poin',
        'nilai_rupiah',
        'tanggal_jam',
        'keterangan',
    ];

    protected function casts(): array
    {
        return [
            'tanggal_jam' => 'datetime',
        ];
    }

    public function anggota()
    {
        return $this->belongsTo(Anggota::class, 'id_anggota', 'id_anggota');
    }

    public function transaksiPos()
    {
        return $this->belongsTo(TransaksiPos::class, 'id_transaksi', 'id_transaksi');
    }
}
