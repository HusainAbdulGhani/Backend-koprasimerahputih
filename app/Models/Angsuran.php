<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Angsuran extends Model
{
    protected $table = 'angsurans';
    protected $primaryKey = 'id_angsuran';
    public $timestamps = false;

    protected $fillable = [
        'id_pinjaman',
        'jumlah_bayar',
        'tanggal_bayar',
        'sisa_pinjaman',
        'bukti_transfer', 
        'status',
    ];

    protected function casts(): array
    {
        return [
            'tanggal_bayar' => 'date',
        ];
    }

    public function pinjaman()
    {
        return $this->belongsTo(Pinjaman::class, 'id_pinjaman', 'id_pinjaman');
    }
}
