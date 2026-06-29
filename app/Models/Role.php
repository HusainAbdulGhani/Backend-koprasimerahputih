<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    protected $table = 'roles';
    protected $primaryKey = 'id_role';
    public $timestamps = false;

    protected $fillable = [
        'name',
        'label',
    ];

    public function accounts()
    {
        return $this->belongsToMany(Account::class, 'account_role', 'id_role', 'id_account')
            ->withPivot('is_default');
    }
}
