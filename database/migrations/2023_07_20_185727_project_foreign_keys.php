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
        echo 'Adding projectid foreign key to authtoken table...' . PHP_EOL;
        Schema::table('authtoken', function (Blueprint $table) {
            $table->integer('projectid')->change();
            $table->foreign('projectid')->references('id')->on('project')->cascadeOnDelete();
        });

        echo 'Adding projectid foreign key to blockbuild table...' . PHP_EOL;
        Schema::table('blockbuild', function (Blueprint $table) {
            $table->integer('projectid')->nullable(false)->change();
            $table->foreign('projectid')->references('id')->on('project')->cascadeOnDelete();
        });

        echo 'Adding projectid foreign key to build table...' . PHP_EOL;
        Schema::table('build', function (Blueprint $table) {
            $table->integer('projectid')->nullable(false)->change();
            $table->foreign('projectid')->references('id')->on('project')->cascadeOnDelete();
        });

        echo 'Adding projectid foreign key to build_filters table...' . PHP_EOL;
        Schema::table('build_filters', function (Blueprint $table) {
            $table->integer('projectid')->nullable(false)->change();
            $table->foreign('projectid')->references('id')->on('project')->cascadeOnDelete();
        });

        echo 'Adding projectid foreign key to buildgroup table...' . PHP_EOL;
        Schema::table('buildgroup', function (Blueprint $table) {
            $table->integer('projectid')->nullable(false)->change();
            $table->foreign('projectid')->references('id')->on('project')->cascadeOnDelete();
        });

        echo 'Adding projectid foreign key to coveragefilepriority table...' . PHP_EOL;
        Schema::table('coveragefilepriority', function (Blueprint $table) {
            $table->integer('projectid')->nullable(false)->change();
            $table->foreign('projectid')->references('id')->on('project')->cascadeOnDelete();
        });

        echo 'Adding projectid foreign key to labelemail table...' . PHP_EOL;
        Schema::table('labelemail', function (Blueprint $table) {
            $table->integer('projectid')->nullable(false)->change();
            $table->foreign('projectid')->references('id')->on('project')->cascadeOnDelete();
        });

        echo 'Adding projectid foreign key to measurement table...' . PHP_EOL;
        Schema::table('measurement', function (Blueprint $table) {
            $table->integer('projectid')->nullable(false)->change();
            $table->foreign('projectid')->references('id')->on('project')->cascadeOnDelete();
        });

        echo 'Adding projectid foreign key to overview_components table...' . PHP_EOL;
        Schema::table('overview_components', function (Blueprint $table) {
            $table->integer('projectid')->nullable(false)->change();
            $table->foreign('projectid')->references('id')->on('project')->cascadeOnDelete();
        });

        echo 'Adding projectid foreign key to project2repositories table...' . PHP_EOL;
        Schema::table('project2repositories', function (Blueprint $table) {
            $table->integer('projectid')->nullable(false)->change();
            $table->foreign('projectid')->references('id')->on('project')->cascadeOnDelete();
        });

        echo 'Adding projectid foreign key to subproject table...' . PHP_EOL;
        Schema::table('subproject', function (Blueprint $table) {
            $table->integer('projectid')->nullable(false)->change();
            $table->foreign('projectid')->references('id')->on('project')->cascadeOnDelete();
        });

        echo 'Adding projectid foreign key to subprojectgroup table...' . PHP_EOL;
        Schema::table('subprojectgroup', function (Blueprint $table) {
            $table->integer('projectid')->nullable(false)->change();
            $table->foreign('projectid')->references('id')->on('project')->cascadeOnDelete();
        });

        echo 'Adding projectid foreign key to test table...' . PHP_EOL;
        Schema::table('test', function (Blueprint $table) {
            $table->integer('projectid')->nullable(false)->change();
            $table->foreign('projectid')->references('id')->on('project')->cascadeOnDelete();
        });

        echo 'Adding projectid foreign key to user2project table...' . PHP_EOL;
        Schema::table('user2project', function (Blueprint $table) {
            $table->integer('projectid')->nullable(false)->change();
            $table->foreign('projectid')->references('id')->on('project')->cascadeOnDelete();
        });

        echo 'Adding projectid foreign key to user2repository table...' . PHP_EOL;
        Schema::table('user2repository', function (Blueprint $table) {
            $table->integer('projectid')->nullable(false)->change();
            $table->foreign('projectid')->references('id')->on('project')->cascadeOnDelete();
        });

        echo 'Adding projectid foreign key to userstatistics table...' . PHP_EOL;
        Schema::table('userstatistics', function (Blueprint $table) {
            $table->integer('projectid')->nullable(false)->change();
            $table->foreign('projectid')->references('id')->on('project')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('authtoken', function (Blueprint $table) {
            $table->integer('projectid')->change();
            $table->dropForeign(['projectid']);
        });

        Schema::table('blockbuild', function (Blueprint $table) {
            $table->integer('projectid')->change();
            $table->dropForeign(['projectid']);
        });

        Schema::table('build', function (Blueprint $table) {
            $table->integer('projectid')->change();
            $table->dropForeign(['projectid']);
        });

        Schema::table('build_filters', function (Blueprint $table) {
            $table->integer('projectid')->change();
            $table->dropForeign(['projectid']);
        });

        Schema::table('buildgroup', function (Blueprint $table) {
            $table->integer('projectid')->change();
            $table->dropForeign(['projectid']);
        });

        Schema::table('coveragefilepriority', function (Blueprint $table) {
            $table->integer('projectid')->change();
            $table->dropForeign(['projectid']);
        });

        Schema::table('labelemail', function (Blueprint $table) {
            $table->integer('projectid')->change();
            $table->dropForeign(['projectid']);
        });

        Schema::table('measurement', function (Blueprint $table) {
            $table->integer('projectid')->change();
            $table->dropForeign(['projectid']);
        });

        Schema::table('overview_components', function (Blueprint $table) {
            $table->integer('projectid')->change();
            $table->dropForeign(['projectid']);
        });

        Schema::table('project2repositories', function (Blueprint $table) {
            $table->integer('projectid')->change();
            $table->dropForeign(['projectid']);
        });

        Schema::table('subproject', function (Blueprint $table) {
            $table->integer('projectid')->change();
            $table->dropForeign(['projectid']);
        });

        Schema::table('subprojectgroup', function (Blueprint $table) {
            $table->integer('projectid')->change();
            $table->dropForeign(['projectid']);
        });

        Schema::table('test', function (Blueprint $table) {
            $table->integer('projectid')->change();
            $table->dropForeign(['projectid']);
        });

        Schema::table('user2project', function (Blueprint $table) {
            $table->integer('projectid')->change();
            $table->dropForeign(['projectid']);
        });

        Schema::table('user2repository', function (Blueprint $table) {
            $table->integer('projectid')->change();
            $table->dropForeign(['projectid']);
        });

        Schema::table('userstatistics', function (Blueprint $table) {
            $table->integer('projectid')->change();
            $table->dropForeign(['projectid']);
        });
    }
};
