<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Gudang extends Model
{
    protected $table = 'gudangs';
    protected $primaryKey = 'id_gudang';
    public $timestamps = false;

    protected $fillable = [
        'id_account',
        'nama_petugas',
        'id_cabang',
    ];

    public function account()
    {
        return $this->belongsTo(Account::class, 'id_account', 'id_account');
    }

    public function cabang()
    {
        return $this->belongsTo(Cabang::class, 'id_cabang', 'id_cabang');
    }

    public function usulanStoks()
    {
        return $this->hasMany(UsulanStok::class, 'id_gudang', 'id_gudang');
    }
}
