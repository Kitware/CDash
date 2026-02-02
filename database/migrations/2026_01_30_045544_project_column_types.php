<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::statement('ALTER TABLE project ALTER COLUMN homeurl DROP NOT NULL');
        DB::statement('ALTER TABLE project ALTER COLUMN homeurl DROP DEFAULT');
        DB::statement('ALTER TABLE project ALTER COLUMN homeurl TYPE text');
        DB::update("UPDATE project SET homeurl = NULL WHERE homeurl = ''");

        DB::statement('ALTER TABLE project ALTER COLUMN cvsurl DROP NOT NULL');
        DB::statement('ALTER TABLE project ALTER COLUMN cvsurl DROP DEFAULT');
        DB::statement('ALTER TABLE project ALTER COLUMN cvsurl TYPE text');
        DB::update("UPDATE project SET cvsurl = NULL WHERE cvsurl = ''");

        DB::statement('ALTER TABLE project ALTER COLUMN bugtrackerurl DROP NOT NULL');
        DB::statement('ALTER TABLE project ALTER COLUMN bugtrackerurl DROP DEFAULT');
        DB::statement('ALTER TABLE project ALTER COLUMN bugtrackerurl TYPE text');
        DB::update("UPDATE project SET bugtrackerurl = NULL WHERE bugtrackerurl = ''");

        DB::statement('ALTER TABLE project ALTER COLUMN bugtrackernewissueurl DROP NOT NULL');
        DB::statement('ALTER TABLE project ALTER COLUMN bugtrackernewissueurl DROP DEFAULT');
        DB::statement('ALTER TABLE project ALTER COLUMN bugtrackernewissueurl TYPE text');
        DB::update("UPDATE project SET bugtrackernewissueurl = NULL WHERE bugtrackernewissueurl = ''");

        DB::statement('ALTER TABLE project ALTER COLUMN documentationurl DROP NOT NULL');
        DB::statement('ALTER TABLE project ALTER COLUMN documentationurl DROP DEFAULT');
        DB::statement('ALTER TABLE project ALTER COLUMN documentationurl TYPE text');
        DB::update("UPDATE project SET documentationurl = NULL WHERE documentationurl = ''");

        DB::statement('ALTER TABLE project ALTER COLUMN testingdataurl DROP NOT NULL');
        DB::statement('ALTER TABLE project ALTER COLUMN testingdataurl DROP DEFAULT');
        DB::statement('ALTER TABLE project ALTER COLUMN testingdataurl TYPE text');
        DB::update("UPDATE project SET testingdataurl = NULL WHERE testingdataurl = ''");

        DB::update('UPDATE project SET testtimestd = 4 WHERE testtimestd IS NULL');
        DB::statement('ALTER TABLE project ALTER COLUMN testtimestd SET NOT NULL');

        DB::update('UPDATE project SET testtimestdthreshold = 1 WHERE testtimestdthreshold IS NULL');
        DB::statement('ALTER TABLE project ALTER COLUMN testtimestdthreshold SET NOT NULL');

        DB::update('UPDATE project SET testtimemaxstatus = 3 WHERE testtimemaxstatus IS NULL');
        DB::statement('ALTER TABLE project ALTER COLUMN testtimemaxstatus SET NOT NULL');

        DB::update('UPDATE project SET emailmaxitems = 5 WHERE emailmaxitems IS NULL');
        DB::statement('ALTER TABLE project ALTER COLUMN emailmaxitems SET NOT NULL');

        DB::update('UPDATE project SET emailmaxchars = 255 WHERE emailmaxchars IS NULL');
        DB::statement('ALTER TABLE project ALTER COLUMN emailmaxchars SET NOT NULL');

        DB::update('UPDATE project SET autoremovetimeframe = 0 WHERE autoremovetimeframe IS NULL');
        DB::statement('ALTER TABLE project ALTER COLUMN autoremovetimeframe SET NOT NULL');

        DB::update('UPDATE project SET autoremovemaxbuilds = 300 WHERE autoremovemaxbuilds IS NULL');
        DB::statement('ALTER TABLE project ALTER COLUMN autoremovemaxbuilds SET NOT NULL');

        DB::update('UPDATE project SET uploadquota = 0 WHERE uploadquota IS NULL');
        DB::statement('ALTER TABLE project ALTER COLUMN uploadquota SET NOT NULL');

        DB::statement('ALTER TABLE project ALTER COLUMN ldapfilter TYPE text');

        DB::statement('ALTER TABLE project ALTER COLUMN banner TYPE text');

        // We drop the type first in case the database has been truncated previously.
        DB::statement('DROP TYPE IF EXISTS cvsviewertype');
        DB::statement("CREATE TYPE cvsviewertype AS ENUM ('github', 'gitlab')");
        DB::statement("
            ALTER TABLE project
            ALTER COLUMN cvsviewertype
            TYPE cvsviewertype USING
                CASE
                    WHEN cvsviewertype = 'github' THEN 'github'::cvsviewertype
                    WHEN cvsviewertype = 'gitlab' THEN 'gitlab'::cvsviewertype
                    ELSE NULL
                END
        ");

        // We drop the type first in case the database has been truncated previously.
        DB::statement('DROP TYPE IF EXISTS bugtrackertype');
        DB::statement("CREATE TYPE bugtrackertype AS ENUM ('GitHub', 'Buganizer', 'JIRA')");
        DB::statement("
            ALTER TABLE project
            ALTER COLUMN bugtrackertype
            TYPE bugtrackertype USING
                CASE
                    WHEN bugtrackertype = 'GitHub' THEN 'GitHub'::bugtrackertype
                    WHEN bugtrackertype = 'Buganizer' THEN 'Buganizer'::bugtrackertype
                    WHEN bugtrackertype = 'JIRA' THEN 'JIRA'::bugtrackertype
                    ELSE NULL
                END
        ");
    }

    public function down(): void
    {
    }
};
