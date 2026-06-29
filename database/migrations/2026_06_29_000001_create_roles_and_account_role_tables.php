<?php

use App\Models\Account;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $roleNames = ['Admin', 'Pengurus', 'Kasir', 'Gudang', 'Anggota'];

    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id('id_role');
            $table->string('name', 30)->unique();
            $table->string('label', 50);
        });

        Schema::create('account_role', function (Blueprint $table) {
            $table->unsignedBigInteger('id_account');
            $table->unsignedBigInteger('id_role');
            $table->boolean('is_default')->default(false);
            $table->primary(['id_account', 'id_role']);
            $table->foreign('id_account')->references('id_account')->on('accounts')->cascadeOnDelete();
            $table->foreign('id_role')->references('id_role')->on('roles')->cascadeOnDelete();
        });

        foreach ($this->roleNames as $role) {
            DB::table('roles')->updateOrInsert(
                ['name' => $role],
                ['label' => $role]
            );
        }

        $roleIds = DB::table('roles')->pluck('id_role', 'name');
        $accounts = Account::query()
            ->with(['admin', 'pengurus', 'kasir', 'gudang', 'anggota'])
            ->get();

        foreach ($accounts as $account) {
            $accountRoles = [$account->role];

            foreach (array_unique($accountRoles) as $roleName) {
                if (! isset($roleIds[$roleName])) {
                    continue;
                }

                DB::table('account_role')->updateOrInsert(
                    ['id_account' => $account->id_account, 'id_role' => $roleIds[$roleName]],
                    ['is_default' => $roleName === $account->role]
                );
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('account_role');
        Schema::dropIfExists('roles');
    }
};
