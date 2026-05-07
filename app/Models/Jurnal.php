<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Jurnal extends Model
{
    protected $table = 'jurnals';
    protected $primaryKey = 'id_jurnal';
    public $timestamps = false;

    protected $fillable = [
        'tanggal',
        'keterangan',
        'id_cabang',
    ];

    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
        ];
    }

    public function cabang()
    {
        return $this->belongsTo(Cabang::class, 'id_cabang', 'id_cabang');
    }

    public function detailJurnals()
    {
        return $this->hasMany(DetailJurnal::class, 'id_jurnal', 'id_jurnal');
    }
}
