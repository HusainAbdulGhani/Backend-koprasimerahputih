<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Kasir extends Model
{
    protected $table = 'kasirs';
    protected $primaryKey = 'id_kasir';
    public $timestamps = false;

    protected $fillable = [
        'id_account',
        'nama_kasir',
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

    public function transaksiPos()
    {
        return $this->hasMany(TransaksiPos::class, 'id_kasir', 'id_kasir');
    }
}
