<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE build ADD COLUMN testnotrunwarning smallint DEFAULT '-1'::smallint");

        // Builds which reported test results have no warnings until proven otherwise.
        DB::update('UPDATE build SET testnotrunwarning = 0 WHERE testnotrun >= 0');

        DB::update("
            UPDATE build
            SET testnotrunwarning = counted.total
            FROM (
                SELECT buildid, COUNT(*) AS total
                FROM build2test
                WHERE status = 'notrun'
                    AND (details IS NULL OR details != 'Disabled')
                GROUP BY buildid
            ) AS counted
            WHERE build.id = counted.buildid
        ");

        // Parent builds aggregate the results of their children.
        DB::update("
            UPDATE build
            SET testnotrunwarning = GREATEST(build.testnotrunwarning, 0) + counted.total
            FROM (
                SELECT child.parentid, COUNT(*) AS total
                FROM build2test
                INNER JOIN build AS child ON child.id = build2test.buildid
                WHERE child.parentid > 0
                    AND build2test.status = 'notrun'
                    AND (build2test.details IS NULL OR build2test.details != 'Disabled')
                GROUP BY child.parentid
            ) AS counted
            WHERE build.id = counted.parentid
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
    }
};
