<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DetailJurnal extends Model
{
    protected $table = 'detail_jurnals';
    protected $primaryKey = 'id_detail_jurnal';
    public $timestamps = false;

    protected $fillable = [
        'id_jurnal',
        'id_akun',
        'debit',
        'kredit',
    ];

    public function jurnal()
    {
        return $this->belongsTo(Jurnal::class, 'id_jurnal', 'id_jurnal');
    }

    public function akun()
    {
        return $this->belongsTo(Akun::class, 'id_akun', 'id_akun');
    }
}
