<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    private const tables_to_modify = [
        'blockbuild',
        'build',
        'build_filters',
        'buildgroup',
        'coveragefilepriority',
        'labelemail',
        'measurement',
        'overview_components',
        'project2repositories',
        'subproject',
        'subprojectgroup',
        'test',
        'user2project',
        'user2repository',
        'userstatistics',
    ];

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // The authtoken table is special because the projectid column can be nullable
        echo "Adding projectid foreign key to authtoken table...";
        $num_deleted = DB::delete("DELETE FROM authtoken WHERE projectid IS NOT NULL AND projectid NOT IN (SELECT id FROM project)");
        echo $num_deleted . ' invalid rows deleted' . PHP_EOL;
        Schema::table('authtoken', function (Blueprint $table) {
            $table->integer('projectid')->nullable()->change();
            $table->foreign('projectid')->references('id')->on('project')->cascadeOnDelete();
        });

        foreach (self::tables_to_modify as $table) {
            echo "Adding projectid foreign key to $table table...";
            $num_deleted = DB::delete("DELETE FROM $table WHERE projectid NOT IN (SELECT id FROM project)");
            echo $num_deleted . ' invalid rows deleted' . PHP_EOL;
            Schema::table($table, function (Blueprint $table) {
                $table->integer('projectid')->nullable(false)->change();
                $table->foreign('projectid')->references('id')->on('project')->cascadeOnDelete();
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
        foreach (self::tables_to_modify + ['authtoken'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->integer('projectid')->change();
                $table->dropForeign(['projectid']);
            });
        }
    }
};
