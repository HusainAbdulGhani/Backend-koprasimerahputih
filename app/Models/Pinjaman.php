<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Pinjaman extends Model
{
    protected $table = 'pinjamans';
    protected $primaryKey = 'id_pinjaman';
    public $timestamps = false;

    protected $fillable = [
        'id_anggota',
        'id_pengurus_acc',
        'jumlah_pinjaman',
        'biaya_operasional',
        'tenor',
        'tanggal_pengajuan',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'tanggal_pengajuan' => 'date',
        ];
    }

    public function anggota()
    {
        return $this->belongsTo(Anggota::class, 'id_anggota', 'id_anggota');
    }

    public function pengurusAcc()
    {
        return $this->belongsTo(Pengurus::class, 'id_pengurus_acc', 'id_pengurus');
    }

    public function angsurans()
    {
        return $this->hasMany(Angsuran::class, 'id_pinjaman', 'id_pinjaman');
    }
}
