<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Akun extends Model
{
    protected $table = 'akuns';
    protected $primaryKey = 'id_akun';
    public $timestamps = false;

    protected $fillable = [
        'nama_akun',
        'jenis',
    ];

    public function detailJurnals()
    {
        return $this->hasMany(DetailJurnal::class, 'id_akun', 'id_akun');
    }
}
