<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Account extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $table = 'accounts';
    protected $primaryKey = 'id_account';
    public $timestamps = false;

    protected $fillable = [
        'username',
        'password',
        'role',
        'email',
    ];

    protected $hidden = [
        'password',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
        ];
    }

    public function admin()
    {
        return $this->hasOne(Admin::class, 'id_account', 'id_account');
    }

    public function pengurus()
    {
        return $this->hasOne(Pengurus::class, 'id_account', 'id_account');
    }

    public function kasir()
    {
        return $this->hasOne(Kasir::class, 'id_account', 'id_account');
    }

    public function gudang()
    {
        return $this->hasOne(Gudang::class, 'id_account', 'id_account');
    }

    public function anggota()
    {
        return $this->hasOne(Anggota::class, 'id_account', 'id_account');
    }

    public function roles()
    {
        return $this->belongsToMany(Role::class, 'account_role', 'id_account', 'id_role')
            ->withPivot('is_default');
    }

    public function availableRoles(): array
    {
        if ($this->relationLoaded('roles')) {
            $roles = $this->roles->pluck('name')->all();
        } else {
            $roles = $this->roles()->pluck('name')->all();
        }

        if (empty($roles) && $this->role) {
            $roles = [$this->role];
        }

        return array_values(array_unique($roles));
    }

    public function hasRole(string $role): bool
    {
        return in_array($role, $this->availableRoles(), true);
    }

    public function resolveActiveRole(?string $requestedRole = null): string
    {
        if ($requestedRole && $this->hasRole($requestedRole)) {
            return $requestedRole;
        }

        return $this->role;
    }

    public function syncRoles(array $roles): void
    {
        $roleIds = Role::query()->whereIn('name', $roles)->pluck('id_role', 'name');
        $syncData = [];
        foreach (array_unique($roles) as $role) {
            if (! isset($roleIds[$role])) {
                continue;
            }

            $syncData[$roleIds[$role]] = ['is_default' => $role === $this->role];
        }

        $this->roles()->sync($syncData);
    }
}
