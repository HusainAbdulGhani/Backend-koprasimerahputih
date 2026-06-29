<?php

use App\Models\Anggota;
use App\Services\SimpananPolicyService;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $policy = app(SimpananPolicyService::class);

        Anggota::query()
            ->where('status', 'Aktif')
            ->chunkById(100, function ($members) use ($policy) {
                foreach ($members as $member) {
                    $policy->ensureSimpananAwal($member);
                }
            }, 'id_anggota');
    }

    public function down(): void
    {
        //
    }
};
