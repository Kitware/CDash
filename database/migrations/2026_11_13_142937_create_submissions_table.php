<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // We drop the type first in case the database has been truncated previously.
        DB::statement('DROP TYPE IF EXISTS submissionstatus');
        DB::statement("CREATE TYPE submissionstatus AS ENUM ('QUEUED', 'PROCESSING', 'DONE', 'FAILED')");

        DB::statement('
            CREATE TABLE submissions (
                id uuid PRIMARY KEY,
                projectid integer REFERENCES project(id) ON DELETE CASCADE NOT NULL,
                buildid bigint REFERENCES build(id) ON DELETE SET NULL,
                type text NOT NULL,
                filename text,
                path text NOT NULL,
                status submissionstatus NOT NULL,
                attempts integer NOT NULL DEFAULT 0,
                error text,
                received_at timestamp(3) with time zone NOT NULL,
                next_attempt_at timestamp(3) with time zone,
                started_at timestamp(3) with time zone,
                finished_at timestamp(3) with time zone
            )
        ');

        DB::statement('CREATE INDEX ON submissions (buildid)');
        DB::statement('CREATE INDEX ON submissions (finished_at)');
    }

    public function down(): void
    {
    }
};
