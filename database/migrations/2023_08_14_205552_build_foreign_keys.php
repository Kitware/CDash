<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    private const tables_to_modify = [
        'build2configure',
        'build2group',
        'build2note',
        'build2test',
        'build2update',
        'build2uploadfile',
        'buildemail',
        'builderror',
        'builderrordiff',
        'buildfailure',
        'buildfile',
        'buildinformation',
        'buildnote',
        'buildproperties',
        'buildtesttime',
        'configureerrordiff',
        'coverage',
        'coveragefilelog',
        'coveragesummary',
        'coveragesummarydiff',
        'dynamicanalysis',
        'dynamicanalysissummary',
        'label2build',
        'label2coveragefile',
        'label2test',
        'pending_submissions',
        'related_builds',
        'subproject2build',
        'summaryemail',
        'testdiff',
    ];

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        foreach (self::tables_to_modify as $table) {
            echo "Adding buildid foreign key to $table table...";
            $num_deleted = DB::delete("DELETE FROM $table WHERE buildid NOT IN (SELECT id FROM build)");
            echo $num_deleted . ' invalid rows deleted' . PHP_EOL;
            Schema::table($table, function (Blueprint $table) {
                $table->integer('buildid')->nullable(false)->change();
                $table->foreign('buildid')->references('id')->on('build')->cascadeOnDelete();
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        foreach (self::tables_to_modify as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->integer('buildid')->change();
                $table->dropForeign(['buildid']);
            });
        }
    }
};
