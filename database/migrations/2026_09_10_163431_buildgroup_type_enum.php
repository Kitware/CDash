<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // We drop the type first in case the database has been truncated previously.
        DB::statement('DROP TYPE IF EXISTS buildgrouptype');
        DB::statement("CREATE TYPE buildgrouptype AS ENUM ('Daily', 'Latest')");

        DB::statement('ALTER TABLE buildgroup ALTER COLUMN type DROP DEFAULT');

        DB::statement("
            ALTER TABLE buildgroup
            ALTER COLUMN \"type\" TYPE buildgrouptype
            USING CASE \"type\"
                WHEN 'Daily' THEN 'Daily'::buildgrouptype
                WHEN 'Latest' THEN 'Latest'::buildgrouptype
                ELSE 'Daily'::buildgrouptype
            END
        ");

        DB::statement("ALTER TABLE buildgroup ALTER COLUMN type SET DEFAULT 'Daily'::buildgrouptype");
    }

    public function down(): void
    {
    }
};
