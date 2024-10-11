<?php

namespace App\Console\Commands;

use CDash\Database;
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
    protected $description = 'Prune unused records from the CDash database';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        DB::delete("DELETE FROM banner WHERE projectid != 0 AND projectid NOT IN (SELECT id FROM project)");
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

    /** Delete unused rows in batches */
    private static function delete_unused_rows(string $table, string $field, string $targettable, string $selectfield = 'id'): void
    {
        $done = false;
        while (!$done) {
            $records_to_delete = DB::select(
                "SELECT $field FROM $table
                WHERE $field NOT IN
                    (SELECT $selectfield AS $field FROM $targettable) LIMIT 10");
            $ids_to_delete = [];
            foreach ($records_to_delete as $record_to_delete) {
                $ids_to_delete[] = intval($record_to_delete->$field);
            }

            if (count($ids_to_delete) === 0) {
                $done = true;
            } else {
                $prepared_array = Database::getInstance()->createPreparedArray(count($ids_to_delete));
                DB::delete("DELETE FROM $table WHERE $field IN $prepared_array", $ids_to_delete);
            }
        }
    }
}
