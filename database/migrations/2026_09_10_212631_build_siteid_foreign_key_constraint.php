<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::statement('ALTER TABLE build ALTER COLUMN siteid DROP NOT NULL');

        DB::statement('ALTER TABLE build ALTER COLUMN siteid DROP DEFAULT');

        DB::update('
            UPDATE build
            SET siteid = NULL
            WHERE NOT EXISTS (
                SELECT 1
                FROM site
                WHERE site.id = build.siteid
            )
        ');

        DB::statement('ALTER TABLE build ADD FOREIGN KEY (siteid) REFERENCES site (id) ON DELETE SET NULL');
    }

    public function down(): void
    {
    }
};
