<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Pengurus extends Model
{
    protected $table = 'pengurus';
    protected $primaryKey = 'id_pengurus';
    public $timestamps = false;

    protected $fillable = [
        'id_account',
        'nama_pengurus',
        'nip',
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

    public function pinjamans()
    {
        return $this->hasMany(Pinjaman::class, 'id_pengurus_acc', 'id_pengurus');
    }

    public function usulanStoks()
    {
        return $this->hasMany(UsulanStok::class, 'id_pengurus_acc', 'id_pengurus');
    }
}
