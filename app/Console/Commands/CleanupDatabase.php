<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CleanupDatabase extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'db:cleanup';

    /**
     * The console command description.
     */
    protected $description = 'Delete orphaned records from the CDash database';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        DB::delete("DELETE FROM banner WHERE projectid != 0 AND projectid NOT IN (SELECT id FROM project)");
        self::delete_unused_rows('build', 'projectid', 'project');
        self::delete_unused_rows('dailyupdate', 'projectid', 'project');

        self::delete_unused_rows('buildfailuredetails', 'id', 'buildfailure', 'detailsid');
        self::delete_unused_rows('configure', 'id', 'build2configure', 'configureid');
        self::delete_unused_rows('configureerror', 'configureid', 'configure');
        self::delete_unused_rows('dailyupdatefile', 'dailyupdateid', 'dailyupdate');
        self::delete_unused_rows('note', 'id', 'build2note', 'noteid');
        self::delete_unused_rows('testoutput', 'id', 'build2test', 'outputid');
        self::delete_unused_rows('updatefile', 'updateid', 'buildupdate');
        self::delete_unused_rows('uploadfile', 'id', 'build2uploadfile', 'fileid');

        self::delete_unused_rows('subproject2subproject', 'subprojectid', 'subproject');

        self::delete_unused_rows('coveragefile', 'id', 'coverage', 'fileid');
        self::delete_unused_rows('coveragefile2user', 'fileid', 'coveragefile');

        self::delete_unused_rows('test2image', 'outputid', 'testoutput');
        self::delete_unused_rows('image', 'id', 'test2image', 'imgid');
    }

    /** Delete unused rows */
    private static function delete_unused_rows(string $table, string $field, string $targettable, string $selectfield = 'id'): void
    {
        DB::delete("DELETE FROM $table WHERE $field NOT IN (SELECT $selectfield AS $field FROM $targettable)");
    }
}
