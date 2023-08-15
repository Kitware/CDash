<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        echo 'Adding buildid foreign key to build2configure table...' . PHP_EOL;
        Schema::table('build2configure', function (Blueprint $table) {
            $table->integer('buildid')->nullable(false)->change();
            $table->foreign('buildid')->references('id')->on('build')->cascadeOnDelete();
        });


        echo 'Adding buildid foreign key to build2group table...' . PHP_EOL;
        Schema::table('build2group', function (Blueprint $table) {
            $table->integer('buildid')->nullable(false)->change();
            $table->foreign('buildid')->references('id')->on('build')->cascadeOnDelete();
        });

        echo 'Adding buildid foreign key to build2note table...' . PHP_EOL;
        Schema::table('build2note', function (Blueprint $table) {
            $table->integer('buildid')->nullable(false)->change();
            $table->foreign('buildid')->references('id')->on('build')->cascadeOnDelete();
        });

        echo 'Adding buildid foreign key to build2test table...' . PHP_EOL;
        Schema::table('build2test', function (Blueprint $table) {
            $table->integer('buildid')->nullable(false)->change();
            $table->foreign('buildid')->references('id')->on('build')->cascadeOnDelete();
        });

        echo 'Adding buildid foreign key to build2update table...' . PHP_EOL;
        Schema::table('build2update', function (Blueprint $table) {
            $table->integer('buildid')->nullable(false)->change();
            $table->foreign('buildid')->references('id')->on('build')->cascadeOnDelete();
        });

        echo 'Adding buildid foreign key to build2uploadfile table...' . PHP_EOL;
        Schema::table('build2uploadfile', function (Blueprint $table) {
            $table->integer('buildid')->nullable(false)->change();
            $table->foreign('buildid')->references('id')->on('build')->cascadeOnDelete();
        });

        echo 'Adding buildid foreign key to buildemail table...' . PHP_EOL;
        Schema::table('buildemail', function (Blueprint $table) {
            $table->integer('buildid')->nullable(false)->change();
            $table->foreign('buildid')->references('id')->on('build')->cascadeOnDelete();
        });

        echo 'Adding buildid foreign key to builderror table...' . PHP_EOL;
        Schema::table('builderror', function (Blueprint $table) {
            $table->integer('buildid')->nullable(false)->change();
            $table->foreign('buildid')->references('id')->on('build')->cascadeOnDelete();
        });

        echo 'Adding buildid foreign key to builderrordiff table...' . PHP_EOL;
        Schema::table('builderrordiff', function (Blueprint $table) {
            $table->integer('buildid')->nullable(false)->change();
            $table->foreign('buildid')->references('id')->on('build')->cascadeOnDelete();
        });

        echo 'Adding buildid foreign key to buildfailure table...' . PHP_EOL;
        Schema::table('buildfailure', function (Blueprint $table) {
            $table->integer('buildid')->nullable(false)->change();
            $table->foreign('buildid')->references('id')->on('build')->cascadeOnDelete();
        });

        echo 'Adding buildid foreign key to buildfile table...' . PHP_EOL;
        Schema::table('buildfile', function (Blueprint $table) {
            $table->integer('buildid')->nullable(false)->change();
            $table->foreign('buildid')->references('id')->on('build')->cascadeOnDelete();
        });

        echo 'Adding buildid foreign key to buildinformation table...' . PHP_EOL;
        Schema::table('buildinformation', function (Blueprint $table) {
            $table->integer('buildid')->nullable(false)->change();
            $table->foreign('buildid')->references('id')->on('build')->cascadeOnDelete();
        });

        echo 'Adding buildid foreign key to buildnote table...' . PHP_EOL;
        Schema::table('buildnote', function (Blueprint $table) {
            $table->integer('buildid')->nullable(false)->change();
            $table->foreign('buildid')->references('id')->on('build')->cascadeOnDelete();
        });

        echo 'Adding buildid foreign key to buildproperties table...' . PHP_EOL;
        Schema::table('buildproperties', function (Blueprint $table) {
            $table->integer('buildid')->nullable(false)->change();
            $table->foreign('buildid')->references('id')->on('build')->cascadeOnDelete();
        });

        echo 'Adding buildid foreign key to buildtesttime table...' . PHP_EOL;
        Schema::table('buildtesttime', function (Blueprint $table) {
            $table->integer('buildid')->nullable(false)->change();
            $table->foreign('buildid')->references('id')->on('build')->cascadeOnDelete();
        });

        echo 'Adding buildid foreign key to configureerrordiff table...' . PHP_EOL;
        Schema::table('configureerrordiff', function (Blueprint $table) {
            $table->integer('buildid')->nullable(false)->change();
            $table->foreign('buildid')->references('id')->on('build')->cascadeOnDelete();
        });

        echo 'Adding buildid foreign key to coverage table...' . PHP_EOL;
        Schema::table('coverage', function (Blueprint $table) {
            $table->integer('buildid')->nullable(false)->change();
            $table->foreign('buildid')->references('id')->on('build')->cascadeOnDelete();
        });

        echo 'Adding buildid foreign key to coveragefilelog table...' . PHP_EOL;
        Schema::table('coveragefilelog', function (Blueprint $table) {
            $table->integer('buildid')->nullable(false)->change();
            $table->foreign('buildid')->references('id')->on('build')->cascadeOnDelete();
        });

        echo 'Adding buildid foreign key to coveragesummary table...' . PHP_EOL;
        Schema::table('coveragesummary', function (Blueprint $table) {
            $table->integer('buildid')->nullable(false)->change();
            $table->foreign('buildid')->references('id')->on('build')->cascadeOnDelete();
        });

        echo 'Adding buildid foreign key to coveragesummarydiff table...' . PHP_EOL;
        Schema::table('coveragesummarydiff', function (Blueprint $table) {
            $table->integer('buildid')->nullable(false)->change();
            $table->foreign('buildid')->references('id')->on('build')->cascadeOnDelete();
        });

        echo 'Adding buildid foreign key to dynamicanalysis table...' . PHP_EOL;
        Schema::table('dynamicanalysis', function (Blueprint $table) {
            $table->integer('buildid')->nullable(false)->change();
            $table->foreign('buildid')->references('id')->on('build')->cascadeOnDelete();
        });

        echo 'Adding buildid foreign key to dynamicanalysissummary table...' . PHP_EOL;
        Schema::table('dynamicanalysissummary', function (Blueprint $table) {
            $table->integer('buildid')->nullable(false)->change();
            $table->foreign('buildid')->references('id')->on('build')->cascadeOnDelete();
        });

        echo 'Adding buildid foreign key to label2build table...' . PHP_EOL;
        Schema::table('label2build', function (Blueprint $table) {
            $table->integer('buildid')->nullable(false)->change();
            $table->foreign('buildid')->references('id')->on('build')->cascadeOnDelete();
        });

        echo 'Adding buildid foreign key to label2coveragefile table...' . PHP_EOL;
        Schema::table('label2coveragefile', function (Blueprint $table) {
            $table->integer('buildid')->nullable(false)->change();
            $table->foreign('buildid')->references('id')->on('build')->cascadeOnDelete();
        });

        echo 'Adding buildid foreign key to label2test table...' . PHP_EOL;
        Schema::table('label2test', function (Blueprint $table) {
            $table->integer('buildid')->nullable(false)->change();
            $table->foreign('buildid')->references('id')->on('build')->cascadeOnDelete();
        });

        echo 'Adding buildid foreign key to pending_submissions table...' . PHP_EOL;
        Schema::table('pending_submissions', function (Blueprint $table) {
            $table->integer('buildid')->nullable(false)->change();
            $table->foreign('buildid')->references('id')->on('build')->cascadeOnDelete();
        });

        echo 'Adding buildid foreign key to related_builds table...' . PHP_EOL;
        Schema::table('related_builds', function (Blueprint $table) {
            $table->integer('buildid')->nullable(false)->change();
            $table->foreign('buildid')->references('id')->on('build')->cascadeOnDelete();
        });

        echo 'Adding buildid foreign key to subproject2build table...' . PHP_EOL;
        Schema::table('subproject2build', function (Blueprint $table) {
            $table->integer('buildid')->nullable(false)->change();
            $table->foreign('buildid')->references('id')->on('build')->cascadeOnDelete();
        });

        echo 'Adding buildid foreign key to summaryemail table...' . PHP_EOL;
        Schema::table('summaryemail', function (Blueprint $table) {
            $table->integer('buildid')->nullable(false)->change();
            $table->foreign('buildid')->references('id')->on('build')->cascadeOnDelete();
        });

        echo 'Adding buildid foreign key to testdiff table...' . PHP_EOL;
        Schema::table('testdiff', function (Blueprint $table) {
            $table->integer('buildid')->nullable(false)->change();
            $table->foreign('buildid')->references('id')->on('build')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('build2configure', function (Blueprint $table) {
            $table->integer('buildid')->change();
            $table->dropForeign(['buildid']);
        });

        Schema::table('build2group', function (Blueprint $table) {
            $table->integer('buildid')->change();
            $table->dropForeign(['buildid']);
        });

        Schema::table('build2note', function (Blueprint $table) {
            $table->integer('buildid')->change();
            $table->dropForeign(['buildid']);
        });

        Schema::table('build2test', function (Blueprint $table) {
            $table->integer('buildid')->change();
            $table->dropForeign(['buildid']);
        });

        Schema::table('build2update', function (Blueprint $table) {
            $table->integer('buildid')->change();
            $table->dropForeign(['buildid']);
        });

        Schema::table('build2uploadfile', function (Blueprint $table) {
            $table->integer('buildid')->change();
            $table->dropForeign(['buildid']);
        });

        Schema::table('buildemail', function (Blueprint $table) {
            $table->integer('buildid')->change();
            $table->dropForeign(['buildid']);
        });

        Schema::table('builderror', function (Blueprint $table) {
            $table->integer('buildid')->change();
            $table->dropForeign(['buildid']);
        });

        Schema::table('builderrordiff', function (Blueprint $table) {
            $table->integer('buildid')->change();
            $table->dropForeign(['buildid']);
        });

        Schema::table('buildfailure', function (Blueprint $table) {
            $table->integer('buildid')->change();
            $table->dropForeign(['buildid']);
        });

        Schema::table('buildfile', function (Blueprint $table) {
            $table->integer('buildid')->change();
            $table->dropForeign(['buildid']);
        });

        Schema::table('buildinformation', function (Blueprint $table) {
            $table->integer('buildid')->change();
            $table->dropForeign(['buildid']);
        });

        Schema::table('buildnote', function (Blueprint $table) {
            $table->integer('buildid')->change();
            $table->dropForeign(['buildid']);
        });

        Schema::table('buildproperties', function (Blueprint $table) {
            $table->integer('buildid')->change();
            $table->dropForeign(['buildid']);
        });

        Schema::table('buildtesttime', function (Blueprint $table) {
            $table->integer('buildid')->change();
            $table->dropForeign(['buildid']);
        });

        Schema::table('configureerrordiff', function (Blueprint $table) {
            $table->integer('buildid')->change();
            $table->dropForeign(['buildid']);
        });

        Schema::table('coverage', function (Blueprint $table) {
            $table->integer('buildid')->change();
            $table->dropForeign(['buildid']);
        });

        Schema::table('coveragefilelog', function (Blueprint $table) {
            $table->integer('buildid')->change();
            $table->dropForeign(['buildid']);
        });

        Schema::table('coveragesummary', function (Blueprint $table) {
            $table->integer('buildid')->change();
            $table->dropForeign(['buildid']);
        });

        Schema::table('coveragesummarydiff', function (Blueprint $table) {
            $table->integer('buildid')->change();
            $table->dropForeign(['buildid']);
        });

        Schema::table('dynamicanalysis', function (Blueprint $table) {
            $table->integer('buildid')->change();
            $table->dropForeign(['buildid']);
        });

        Schema::table('dynamicanalysissummary', function (Blueprint $table) {
            $table->integer('buildid')->change();
            $table->dropForeign(['buildid']);
        });

        Schema::table('label2build', function (Blueprint $table) {
            $table->integer('buildid')->change();
            $table->dropForeign(['buildid']);
        });

        Schema::table('label2coveragefile', function (Blueprint $table) {
            $table->integer('buildid')->change();
            $table->dropForeign(['buildid']);
        });

        Schema::table('label2test', function (Blueprint $table) {
            $table->integer('buildid')->change();
            $table->dropForeign(['buildid']);
        });

        Schema::table('pending_submissions', function (Blueprint $table) {
            $table->integer('buildid')->change();
            $table->dropForeign(['buildid']);
        });

        Schema::table('related_builds', function (Blueprint $table) {
            $table->integer('buildid')->change();
            $table->dropForeign(['buildid']);
        });

        Schema::table('subproject2build', function (Blueprint $table) {
            $table->integer('buildid')->change();
            $table->dropForeign(['buildid']);
        });

        Schema::table('summaryemail', function (Blueprint $table) {
            $table->integer('buildid')->change();
            $table->dropForeign(['buildid']);
        });

        Schema::table('testdiff', function (Blueprint $table) {
            $table->integer('buildid')->change();
            $table->dropForeign(['buildid']);
        });
    }
};
