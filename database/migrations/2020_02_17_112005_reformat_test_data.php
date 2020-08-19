<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class ReformatTestData extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasColumn('testoutput', 'name')) {
            // Return early if it looks like this migration has
            // already been performed.
            return;
        }

        // Careully move data around between tables.

        // Add testid column on testoutput table.
        $this->print("Adding 'testid' column on 'testoutput' table");
        Schema::table('testoutput', function (Blueprint $table) {
            $table->integer('testid')->default(0);
            $table->index('testid');
        });

        // Add/rename columns on the build2test table.
        $this->print("Modifying columns of 'build2test' table");
        Schema::table('build2test', function (Blueprint $table) {
            // Rename build2test.testid to .outputid,
            $table->renameColumn('testid', 'outputid');
            // Rename the index on this column too,
            // if it was previously created by Laravel.
            $sm = Schema::getConnection()->getDoctrineSchemaManager();
            $indexesFound = $sm->listTableIndexes('build2test');
            if (array_key_exists('build2test_testid_index', $indexesFound)) {
                $table->renameIndex('build2test_testid_index', 'build2test_outputid_index');
            }

            // Add a primary key.
            $table->increments('id');

            // Add the 'details' column.
            $table->string('details', 255)->default('');
        });
        Schema::table('build2test', function (Blueprint $table) {
            // Add a new testid column.
            $table->integer('testid')->default(0);
            $table->index('testid');
        });

        // Rename testid to outputid on test2image, testmeasurement, and label2test
        Schema::table('test2image', function (Blueprint $table) {
            $table->renameColumn('testid', 'outputid');
        });
        Schema::table('testmeasurement', function (Blueprint $table) {
            $table->renameColumn('testid', 'outputid');
        });
        Schema::table('label2test', function (Blueprint $table) {
            $table->renameColumn('testid', 'outputid');
        });

        // Migrate (name, projectid) from testoutput to test
        $this->print("Moving name and projectid to 'test' table");
        DB::table('test')->insertUsing(
            ['name', 'projectid'],
            DB::table('testoutput')->distinct()->select('name', 'projectid'));

        // Set testid in the testoutput table.
        $this->print('Set testid in the testoutput table');
        if (config('database.default') == 'pgsql') {
            DB::insert('
                UPDATE testoutput
                SET testid = test.id
                FROM test
                WHERE testoutput.name = test.name AND
                      testoutput.projectid = test.projectid');
        } else {
            DB::insert('
                UPDATE testoutput
                INNER JOIN test ON
                    (testoutput.name = test.name AND
                     testoutput.projectid = test.projectid)
                SET testoutput.testid = test.id');
        }

        // Set testid and details in the build2test table.
        $this->print('Set testid and details in the build2test table');

        $start = DB::table('build2test')->min('id') || 1;
        $max = DB::table('build2test')->max('id') || $start;
        $total = $max - $start;
        $num_done = 0;
        $next_report = 10;
        $done = false;

        while (!$done) {
            $end = $start + 4999;
            if (config('database.default') == 'pgsql') {
                DB::update("
                    UPDATE build2test
                    SET testid = testoutput.testid,
                        details = testoutput.details
                    FROM testoutput
                    WHERE build2test.outputid = testoutput.id AND
                          build2test.id BETWEEN $start AND $end");
            } else {
                DB::update("
                    UPDATE build2test
                    INNER JOIN testoutput ON build2test.outputid = testoutput.id
                    SET build2test.testid = testoutput.testid,
                        build2test.details = testoutput.details
                        WHERE build2test.id BETWEEN $start AND $end");
            }
            $num_done += 5000;
            if ($end >= $max) {
                $done = true;
            } else {
                usleep(1);
                $start += 5000;
                // Calculate percentage inserted.
                $percent = round(($num_done / $total) * 100, -1);
                if ($percent > $next_report) {
                    $this->print("{$percent}%");
                    $next_report = $percent + 10;
                }
            }
        }

        // Remove migrated columns from testoutput table.
        $this->print("Removing migrated columns from the 'testoutput' table");
        Schema::table('testoutput', function (Blueprint $table) {
            $table->dropColumn(['name', 'projectid', 'details']);
        });

        $this->print('Done!');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        if (Schema::hasColumn('testoutput', 'name')) {
            return;
        }

        Schema::table('testoutput', function (Blueprint $table) {
            $table->string('name')->default('')->index();
            $table->integer('projectid')->default(0)->index();
            $table->text('details', 65535)->default('');
        });

        if (config('database.default') == 'pgsql') {
            DB::update('
                UPDATE testoutput
                SET name = test.name,
                    projectid = test.projectid
                FROM test
                WHERE testoutput.testid = test.id');
            DB::update('
                UPDATE testoutput
                SET details = build2test.details
                FROM build2test
                WHERE testoutput.id = build2test.outputid');
        } else {
            DB::update('
                UPDATE testoutput
                INNER JOIN test ON testoutput.testid = test.id
                SET testoutput.name = test.name,
                    testoutput.projectid = test.projectid');
            DB::update('
                UPDATE testoutput
                INNER JOIN build2test ON testoutput.id = build2test.outputid
                SET testoutput.details = build2test.details');
        }

        Schema::table('label2test', function (Blueprint $table) {
            $table->renameColumn('outputid', 'testid');
        });

        Schema::table('testmeasurement', function (Blueprint $table) {
            $table->renameColumn('outputid', 'testid');
        });

        Schema::table('test2image', function (Blueprint $table) {
            $table->renameColumn('outputid', 'testid');
        });

        Schema::table('build2test', function (Blueprint $table) {
            $table->dropColumn('testid');
        });

        Schema::table('build2test', function (Blueprint $table) {
            // Rename build2test.testid to .outputid,
            $table->renameColumn('outputid', 'testid');
            $table->dropColumn('id');
            $table->dropColumn('details');
        });

        Schema::table('testoutput', function (Blueprint $table) {
            $table->dropColumn('testid');
        });
    }

    public function print($msg)
    {
        echo date('[Y-m-d H:i:s] ') . $msg . PHP_EOL;
    }
}
