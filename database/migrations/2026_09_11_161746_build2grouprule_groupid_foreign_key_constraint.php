<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::statement('ALTER TABLE build2grouprule ALTER COLUMN groupid DROP DEFAULT');

        DB::delete('
            DELETE FROM build2grouprule
            WHERE NOT EXISTS (
                SELECT 1
                FROM buildgroup
                WHERE buildgroup.id = build2grouprule.groupid
            )
        ');

        DB::statement('ALTER TABLE build2grouprule ADD FOREIGN KEY (groupid) REFERENCES buildgroup (id) ON DELETE CASCADE');
    }

    public function down(): void
    {
    }
};
