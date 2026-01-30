<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
    public function up(): void
    {
        DB::statement('ALTER TABLE buildfailure ADD COLUMN type smallint');
        DB::statement('ALTER TABLE buildfailure ADD COLUMN stdoutput text');
        DB::statement('ALTER TABLE buildfailure ADD COLUMN stderror text');
        DB::statement('ALTER TABLE buildfailure ADD COLUMN exitcondition character varying(255)');
        DB::statement('ALTER TABLE buildfailure ADD COLUMN language character varying(64)');
        DB::statement('ALTER TABLE buildfailure ADD COLUMN targetname character varying(512)');
        DB::statement('ALTER TABLE buildfailure ADD COLUMN outputfile character varying(512)');
        DB::statement('ALTER TABLE buildfailure ADD COLUMN outputtype character varying(255)');

        DB::update('
            UPDATE buildfailure
            SET
                type = buildfailuredetails.type,
                stdoutput = buildfailuredetails.stdoutput,
                stderror = buildfailuredetails.stderror,
                exitcondition = buildfailuredetails.exitcondition,
                language = buildfailuredetails.language,
                targetname = buildfailuredetails.targetname,
                outputfile = buildfailuredetails.outputfile,
                outputtype = buildfailuredetails.outputtype
            FROM buildfailuredetails
            WHERE buildfailure.detailsid = buildfailuredetails.id
        ');

        DB::statement('DROP TABLE buildfailuredetails');
        DB::statement('ALTER TABLE buildfailure DROP COLUMN detailsid');

        DB::update('UPDATE buildfailure SET type = 0 WHERE stdoutput IS NULL');
        DB::update("UPDATE buildfailure SET stdoutput = '' WHERE stdoutput IS NULL");
        DB::update("UPDATE buildfailure SET stderror = '' WHERE stderror IS NULL");
        DB::update("UPDATE buildfailure SET exitcondition = '' WHERE exitcondition IS NULL");
        DB::update("UPDATE buildfailure SET language = '' WHERE language IS NULL");
        DB::update("UPDATE buildfailure SET targetname = '' WHERE targetname IS NULL");
        DB::update("UPDATE buildfailure SET outputfile = '' WHERE outputfile IS NULL");
        DB::update("UPDATE buildfailure SET outputtype = '' WHERE outputtype IS NULL");

        DB::statement('ALTER TABLE buildfailure ALTER COLUMN type SET NOT NULL');
        DB::statement('ALTER TABLE buildfailure ALTER COLUMN stdoutput SET NOT NULL');
        DB::statement('ALTER TABLE buildfailure ALTER COLUMN stderror SET NOT NULL');
        DB::statement('ALTER TABLE buildfailure ALTER COLUMN exitcondition SET NOT NULL');
        DB::statement('ALTER TABLE buildfailure ALTER COLUMN language SET NOT NULL');
        DB::statement('ALTER TABLE buildfailure ALTER COLUMN targetname SET NOT NULL');
        DB::statement('ALTER TABLE buildfailure ALTER COLUMN outputfile SET NOT NULL');
        DB::statement('ALTER TABLE buildfailure ALTER COLUMN outputtype SET NOT NULL');
    }

    public function down(): void
    {
    }
};
