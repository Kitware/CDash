<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::statement('
            CREATE TABLE IF NOT EXISTS label2coverage (
                labelid bigint REFERENCES label(id) ON DELETE CASCADE NOT NULL,
                coverageid bigint REFERENCES coverage(id) ON DELETE CASCADE NOT NULL
            )
        ');

        DB::insert('
            INSERT INTO label2coverage(
                labelid,
                coverageid
            )
            SELECT
                label2coveragefile.labelid AS labelid,
                coverage.id AS coverageid
            FROM label2coveragefile
            INNER JOIN coverage ON (
                label2coveragefile.buildid = coverage.buildid
                AND label2coveragefile.coveragefileid = coverage.fileid
            )
        ');
        DB::statement('DROP TABLE label2coveragefile');

        DB::statement('CREATE UNIQUE INDEX ON label2coverage (labelid, coverageid)');
        DB::statement('CREATE UNIQUE INDEX ON label2coverage (coverageid, labelid)');
    }

    public function down(): void
    {
    }
};
