<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE usulan_stoks DROP FOREIGN KEY usulan_stoks_id_pengurus_acc_foreign');
        DB::statement('ALTER TABLE usulan_stoks MODIFY id_pengurus_acc BIGINT UNSIGNED NULL');
        DB::statement('ALTER TABLE usulan_stoks ADD CONSTRAINT usulan_stoks_id_pengurus_acc_foreign FOREIGN KEY (id_pengurus_acc) REFERENCES pengurus(id_pengurus) ON DELETE CASCADE');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE usulan_stoks DROP FOREIGN KEY usulan_stoks_id_pengurus_acc_foreign');
        DB::statement('ALTER TABLE usulan_stoks MODIFY id_pengurus_acc BIGINT UNSIGNED NOT NULL');
        DB::statement('ALTER TABLE usulan_stoks ADD CONSTRAINT usulan_stoks_id_pengurus_acc_foreign FOREIGN KEY (id_pengurus_acc) REFERENCES pengurus(id_pengurus) ON DELETE CASCADE');
    }
};

