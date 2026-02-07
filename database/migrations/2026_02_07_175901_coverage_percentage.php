<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::statement('DROP VIEW coverageview');

        DB::statement('
            ALTER TABLE coverage
            ADD COLUMN linepercentage real
            GENERATED ALWAYS AS (
                CASE
                    WHEN loctested + locuntested = 0 THEN 100
                    ELSE (loctested / (loctested + locuntested)::real) * 100
                END
            ) STORED NOT NULL
        ');

        DB::statement('
            ALTER TABLE coverage
            ADD COLUMN branchpercentage real
            GENERATED ALWAYS AS (
                CASE
                    WHEN branchestested + branchesuntested = 0 THEN 100
                    ELSE (branchestested / (branchestested + branchesuntested)::real) * 100
                END
            ) STORED NOT NULL
        ');

        DB::statement('
            ALTER TABLE coverage
            ADD COLUMN functionpercentage real
            GENERATED ALWAYS AS (
                CASE
                    WHEN functionstested + functionsuntested = 0 THEN 100
                    ELSE (functionstested / (functionstested + functionsuntested)::real) * 100
                END
            ) STORED NOT NULL
        ');

        // Recreate view using the definition in 2025_07_25_131613_coverage_view.php, with new percentage cols added.
        DB::statement('
            CREATE VIEW coverageview AS
                SELECT
                    coverage.id,
                    coverage.buildid,
                    coverage.covered,
                    coverage.loctested,
                    coverage.locuntested,
                    coverage.linepercentage,
                    coverage.branchestested,
                    coverage.branchesuntested,
                    coverage.branchpercentage,
                    coverage.functionstested,
                    coverage.functionsuntested,
                    coverage.functionpercentage,
                    coveragefile.fullpath,
                    coveragefile.file,
                    coveragefilelog.log
                FROM
                    coverage
                    LEFT JOIN coveragefile ON (
                        coverage.fileid = coveragefile.id
                    )
                    LEFT JOIN coveragefilelog ON (
                        coverage.buildid = coveragefilelog.buildid
                        AND coverage.fileid = coveragefilelog.fileid
                    )
        ');
    }

    public function down(): void
    {
    }
};
