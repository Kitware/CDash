<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::statement('ALTER TABLE build ALTER COLUMN parentid DROP NOT NULL');

        DB::statement('ALTER TABLE build ALTER COLUMN parentid DROP DEFAULT');

        DB::statement('ALTER TABLE build ALTER COLUMN parentid TYPE bigint');

        DB::update('
            UPDATE build b1
            SET parentid = NULL
            WHERE NOT EXISTS (
                SELECT 1
                FROM build b2
                WHERE b2.id = b1.parentid
            )
        ');

        DB::statement('ALTER TABLE build ADD FOREIGN KEY (parentid) REFERENCES build (id) ON DELETE CASCADE');
    }

    public function down(): void
    {
    }
};
