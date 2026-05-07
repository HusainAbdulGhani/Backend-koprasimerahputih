<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Admin extends Model
{
    protected $table = 'admins';
    protected $primaryKey = 'id_admin';
    public $timestamps = false;

    protected $fillable = [
        'id_account',
        'nama_admin',
    ];

    public function account()
    {
        return $this->belongsTo(Account::class, 'id_account', 'id_account');
    }
}
