<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CleanDatabase extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'db:clean';

    /**
     * The console command description.
     */
    protected $description = 'Prune unused records from the CDash database';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $num_deleted = DB::delete("DELETE FROM banner WHERE projectid != 0 AND
                NOT EXISTS (SELECT 1 FROM project WHERE project.id = banner.projectid)");
        echo "{$num_deleted} rows deleted from `banner`\n";

        self::delete_unused_rows('dailyupdate', 'projectid', 'project');

        self::delete_unused_rows('buildfailuredetails', 'id', 'buildfailure', 'detailsid');
        self::delete_unused_rows('configure', 'id', 'build2configure', 'configureid');
        self::delete_unused_rows('coveragefile', 'id', 'coverage', 'fileid');
        self::delete_unused_rows('dailyupdatefile', 'dailyupdateid', 'dailyupdate');
        self::delete_unused_rows('note', 'id', 'build2note', 'noteid');
        self::delete_unused_rows('testoutput', 'id', 'build2test', 'outputid');
        self::delete_unused_rows('uploadfile', 'id', 'build2uploadfile', 'fileid');

        $num_deleted = DB::delete("DELETE FROM image WHERE
                NOT EXISTS (SELECT 1 FROM project WHERE project.imageid = image.id) AND
                NOT EXISTS (SELECT 1 FROM test2image WHERE test2image.imgid = image.id)");
        echo "{$num_deleted} rows deleted from `image`\n";
    }

    /** Delete unused rows in batches */
    private static function delete_unused_rows(string $table, string $field, string $targettable, string $selectfield = 'id'): void
    {
        $start = DB::table($table)->min($field);
        $max = DB::table($table)->max($field);
        if (!is_numeric($start) || !is_numeric($max)) {
            echo "Could not determine min and max for `{$field}` on `{$table}`\n";
            return;
        }

        $start = intval($start);
        $max = intval($max);

        $total = $max - $start;
        if ($total < 1) {
            return;
        }
        $num_done = 0;
        $num_deleted = 0;
        $next_report = 10;
        $done = false;
        echo "Deleting unused rows from `{$table}`\n";
        while (!$done) {
            $end = $start + 49999;
            $num_deleted += DB::delete("
                DELETE FROM $table
                WHERE $field BETWEEN $start AND $end
                      AND $field NOT IN (SELECT $selectfield FROM $targettable)");
            $num_done += 50000;
            if ($end >= $max) {
                $done = true;
            } else {
                usleep(1);
                $start += 50000;
                // Calculate percentage of work completed so far.
                $percent = round(($num_done / $total) * 100, -1);
                if ($percent > $next_report) {
                    echo "{$next_report}%\n";
                    $next_report = $next_report + 10;
                }
            }
        }
        echo "{$num_deleted} rows deleted from `{$table}`\n";
    }
}
