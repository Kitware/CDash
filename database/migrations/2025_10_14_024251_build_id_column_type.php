<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // Postgres doesn't allow columns used in views to be changed, so we have to drop and recreate.
        DB::statement('DROP VIEW coverageview');

        DB::statement('ALTER TABLE build2configure ALTER COLUMN buildid TYPE bigint');
        DB::statement('ALTER TABLE build2group ALTER COLUMN buildid TYPE bigint');
        DB::statement('ALTER TABLE build2note ALTER COLUMN buildid TYPE bigint');
        DB::statement('ALTER TABLE build2update ALTER COLUMN buildid TYPE bigint');
        DB::statement('ALTER TABLE build2uploadfile ALTER COLUMN buildid TYPE bigint');
        DB::statement('ALTER TABLE buildcommands ALTER COLUMN buildid TYPE bigint');
        DB::statement('ALTER TABLE targets ALTER COLUMN buildid TYPE bigint');
        DB::statement('ALTER TABLE buildemail ALTER COLUMN buildid TYPE bigint');
        DB::statement('ALTER TABLE builderror ALTER COLUMN buildid TYPE bigint');
        DB::statement('ALTER TABLE builderrordiff ALTER COLUMN buildid TYPE bigint');
        DB::statement('ALTER TABLE buildfailure ALTER COLUMN buildid TYPE bigint');
        DB::statement('ALTER TABLE buildfile ALTER COLUMN buildid TYPE bigint');
        DB::statement('ALTER TABLE buildproperties ALTER COLUMN buildid TYPE bigint');
        DB::statement('ALTER TABLE buildtesttime ALTER COLUMN buildid TYPE bigint');
        DB::statement('ALTER TABLE comments ALTER COLUMN buildid TYPE bigint');
        DB::statement('ALTER TABLE configureerrordiff ALTER COLUMN buildid TYPE bigint');
        DB::statement('ALTER TABLE coverage ALTER COLUMN buildid TYPE bigint');
        DB::statement('ALTER TABLE coveragesummary ALTER COLUMN buildid TYPE bigint');
        DB::statement('ALTER TABLE dynamicanalysis ALTER COLUMN buildid TYPE bigint');
        DB::statement('ALTER TABLE dynamicanalysissummary ALTER COLUMN buildid TYPE bigint');
        DB::statement('ALTER TABLE label2build ALTER COLUMN buildid TYPE bigint');
        DB::statement('ALTER TABLE coveragefilelog ALTER COLUMN buildid TYPE bigint');
        DB::statement('ALTER TABLE build2test ALTER COLUMN buildid TYPE bigint');
        DB::statement('ALTER TABLE pending_submissions ALTER COLUMN buildid TYPE bigint');
        DB::statement('ALTER TABLE related_builds ALTER COLUMN buildid TYPE bigint');
        DB::statement('ALTER TABLE subproject2build ALTER COLUMN buildid TYPE bigint');
        DB::statement('ALTER TABLE summaryemail ALTER COLUMN buildid TYPE bigint');
        DB::statement('ALTER TABLE testdiff ALTER COLUMN buildid TYPE bigint');
        DB::statement('ALTER TABLE build ALTER COLUMN id TYPE bigint');
        DB::statement('ALTER SEQUENCE build_id_seq AS bigint');

        // Recreate view using the definition in 2025_07_25_131613_coverage_view.php
        DB::statement('
            CREATE VIEW coverageview AS
                SELECT
                    coverage.id,
                    coverage.buildid,
                    coverage.covered,
                    coverage.loctested,
                    coverage.locuntested,
                    coverage.branchestested,
                    coverage.branchesuntested,
                    coverage.functionstested,
                    coverage.functionsuntested,
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
