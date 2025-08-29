<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // We drop the type first in case the database has been truncated previously.
        DB::statement('DROP TYPE IF EXISTS teststatus');
        DB::statement("CREATE TYPE teststatus AS ENUM ('failed', 'notrun', 'passed')");
        DB::statement('ALTER TABLE build2test ALTER COLUMN status DROP DEFAULT');
        DB::statement("
            ALTER TABLE build2test
            ALTER COLUMN status
            TYPE teststatus USING
                CASE
                    WHEN status = 'failed' THEN 'failed'::teststatus
                    WHEN status = 'notrun' THEN 'notrun'::teststatus
                    WHEN status = 'passed' THEN 'passed'::teststatus
                    ELSE 'passed'::teststatus
                END
        ");
        DB::statement('ALTER TABLE build2test ALTER COLUMN status SET NOT NULL');
    }

    public function down(): void
    {
    }
};
