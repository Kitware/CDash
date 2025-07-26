<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
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
