<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // We drop the type first in case the database has been truncated previously.
        DB::statement('DROP TYPE IF EXISTS teststatus');
        DB::statement("CREATE TYPE teststatus AS ENUM ('failed', 'notrun', 'passed')");
        DB::statement('ALTER TABLE build2test RENAME COLUMN status TO old_status');
        DB::statement('ALTER TABLE build2test ADD COLUMN status teststatus');
        DB::update("UPDATE build2test SET status = old_status::teststatus WHERE old_status IN ('failed', 'notrun', 'passed')");
        DB::update("UPDATE build2test SET status = 'passed' WHERE status IS NULL");
        DB::statement('ALTER TABLE build2test ALTER COLUMN status SET NOT NULL');
        DB::statement('ALTER TABLE build2test DROP COLUMN old_status');
        DB::statement('CREATE INDEX ON build2test (buildid, status)');
    }

    public function down(): void
    {
    }
};
