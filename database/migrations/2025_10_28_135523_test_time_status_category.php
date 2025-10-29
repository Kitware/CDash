<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // We drop the type first in case the database has been truncated previously.
        DB::statement('DROP TYPE IF EXISTS testtimestatuscategory');
        DB::statement("CREATE TYPE testtimestatuscategory AS ENUM ('PASSED', 'FAILED')");

        // VIRTUAL columns aren't supported in postgres <18, and don't support user-defined types in 18+ anyway.
        DB::statement("
            ALTER TABLE build2test
            ADD COLUMN timestatuscategory testtimestatuscategory
            GENERATED ALWAYS AS (
                CASE
                    WHEN timestatus = 0 THEN 'PASSED'::testtimestatuscategory
                    ELSE 'FAILED'::testtimestatuscategory
                END
            ) STORED NOT NULl
        ");
    }

    public function down(): void
    {
    }
};
