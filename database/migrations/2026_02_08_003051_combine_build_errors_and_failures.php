<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::statement('ALTER TABLE buildfailure RENAME TO builderrors');

        // Add builderror columns which don't already exist in buildfailure (now named builderrors)
        DB::statement('ALTER TABLE builderrors ADD COLUMN logline integer');
        DB::statement('ALTER TABLE builderrors ADD COLUMN sourceline integer');
        DB::statement('ALTER TABLE builderrors ADD COLUMN repeatcount integer');

        // Drop not-null constraints on buildfailure-specific columns
        DB::statement('ALTER TABLE builderrors ALTER COLUMN workingdirectory DROP NOT NULL');
        DB::statement('ALTER TABLE builderrors ALTER COLUMN exitcondition DROP NOT NULL');
        DB::statement('ALTER TABLE builderrors ALTER COLUMN language DROP NOT NULL');
        DB::statement('ALTER TABLE builderrors ALTER COLUMN targetname DROP NOT NULL');
        DB::statement('ALTER TABLE builderrors ALTER COLUMN targetname DROP NOT NULL');
        DB::statement('ALTER TABLE builderrors ALTER COLUMN outputfile DROP NOT NULL');
        DB::statement('ALTER TABLE builderrors ALTER COLUMN outputtype DROP NOT NULL');

        // By copying builderror into what was previously called buildfailure, we preserve the
        // buildfailure IDs and create new ones for builderror records.
        DB::insert('
            INSERT INTO builderrors (
                buildid,
                type,
                logline,
                stderror,
                sourcefile,
                sourceline,
                repeatcount,
                newstatus,
                stdoutput
            )
            SELECT
                buildid,
                type,
                logline,
                stderror,
                sourcefile,
                sourceline,
                repeatcount,
                newstatus,
                stdoutput
            FROM builderror
        ');

        DB::statement('DROP TABLE builderror');

        DB::statement('
            CREATE VIEW builderror AS
                SELECT
                    builderrors.id,
                    builderrors.buildid,
                    builderrors.type,
                    builderrors.logline,
                    builderrors.stderror,
                    builderrors.sourcefile,
                    builderrors.sourceline,
                    builderrors.repeatcount,
                    builderrors.newstatus,
                    builderrors.stdoutput
                FROM
                    builderrors
                WHERE
                    builderrors.id IS NOT NULL
                    AND builderrors.buildid IS NOT NULL
                    AND builderrors.type IS NOT NULL
                    AND builderrors.logline IS NOT NULL
                    AND builderrors.stderror IS NOT NULL
                    AND builderrors.sourcefile IS NOT NULL
                    AND builderrors.sourceline IS NOT NULL
                    AND builderrors.repeatcount IS NOT NULL
                    AND builderrors.newstatus IS NOT NULL
                    AND builderrors.stdoutput IS NOT NULL
        ');
        DB::statement('ALTER VIEW builderror ALTER COLUMN logline SET DEFAULT 0');
        DB::statement("ALTER VIEW builderror ALTER COLUMN sourcefile SET DEFAULT ''");
        DB::statement('ALTER VIEW builderror ALTER COLUMN sourceline SET DEFAULT 0');
        DB::statement('ALTER VIEW builderror ALTER COLUMN repeatcount SET DEFAULT 0');

        DB::statement('
            CREATE VIEW buildfailure AS
                SELECT
                    builderrors.id,
                    builderrors.buildid,
                    builderrors.workingdirectory,
                    builderrors.sourcefile,
                    builderrors.newstatus,
                    builderrors.type,
                    builderrors.stdoutput,
                    builderrors.stderror,
                    builderrors.exitcondition,
                    builderrors.language,
                    builderrors.targetname,
                    builderrors.outputfile,
                    builderrors.outputtype
                FROM
                    builderrors
                WHERE
                    builderrors.id IS NOT NULL
                    AND builderrors.buildid IS NOT NULL
                    AND builderrors.workingdirectory IS NOT NULL
                    AND builderrors.sourcefile IS NOT NULL
                    AND builderrors.newstatus IS NOT NULL
                    AND builderrors.type IS NOT NULL
                    AND builderrors.stdoutput IS NOT NULL
                    AND builderrors.stderror IS NOT NULL
                    AND builderrors.exitcondition IS NOT NULL
                    AND builderrors.language IS NOT NULL
                    AND builderrors.targetname IS NOT NULL
                    AND builderrors.outputfile IS NOT NULL
                    AND builderrors.outputtype IS NOT NULL
        ');
    }

    public function down(): void
    {
    }
};
