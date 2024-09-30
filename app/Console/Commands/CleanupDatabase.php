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
        self::delete_unused_rows('banner', 'projectid', 'project');
        self::delete_unused_rows('blockbuild', 'projectid', 'project');
        self::delete_unused_rows('build', 'projectid', 'project');
        self::delete_unused_rows('buildgroup', 'projectid', 'project');
        self::delete_unused_rows('labelemail', 'projectid', 'project');
        self::delete_unused_rows('project2repositories', 'projectid', 'project');
        self::delete_unused_rows('dailyupdate', 'projectid', 'project');
        self::delete_unused_rows('subproject', 'projectid', 'project');
        self::delete_unused_rows('coveragefilepriority', 'projectid', 'project');
        self::delete_unused_rows('user2project', 'projectid', 'project');
        self::delete_unused_rows('userstatistics', 'projectid', 'project');

        self::delete_unused_rows('build2configure', 'buildid', 'build');
        self::delete_unused_rows('build2note', 'buildid', 'build');
        self::delete_unused_rows('build2test', 'buildid', 'build');
        self::delete_unused_rows('buildemail', 'buildid', 'build');
        self::delete_unused_rows('builderror', 'buildid', 'build');
        self::delete_unused_rows('builderrordiff', 'buildid', 'build');
        self::delete_unused_rows('buildfailure', 'buildid', 'build');
        self::delete_unused_rows('buildfailuredetails', 'id', 'buildfailure', 'detailsid');
        self::delete_unused_rows('buildinformation', 'buildid', 'build');
        self::delete_unused_rows('buildtesttime', 'buildid', 'build');
        self::delete_unused_rows('configure', 'id', 'build2configure', 'configureid');
        self::delete_unused_rows('configureerror', 'configureid', 'configure');
        self::delete_unused_rows('configureerrordiff', 'buildid', 'build');
        self::delete_unused_rows('coverage', 'buildid', 'build');
        self::delete_unused_rows('coveragefilelog', 'buildid', 'build');
        self::delete_unused_rows('coveragesummary', 'buildid', 'build');
        self::delete_unused_rows('coveragesummarydiff', 'buildid', 'build');
        self::delete_unused_rows('dailyupdatefile', 'dailyupdateid', 'dailyupdate');
        self::delete_unused_rows('dynamicanalysis', 'buildid', 'build');
        self::delete_unused_rows('label2build', 'buildid', 'build');
        self::delete_unused_rows('note', 'id', 'build2note', 'noteid');
        self::delete_unused_rows('subproject2build', 'buildid', 'build');
        self::delete_unused_rows('summaryemail', 'buildid', 'build');
        self::delete_unused_rows('testdiff', 'buildid', 'build');
        self::delete_unused_rows('testoutput', 'id', 'build2test', 'outputid');
        self::delete_unused_rows('updatefile', 'updateid', 'buildupdate');
        self::delete_unused_rows('uploadfile', 'id', 'build2uploadfile', 'fileid');

        self::delete_unused_rows('dynamicanalysisdefect', 'dynamicanalysisid', 'dynamicanalysis');
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
