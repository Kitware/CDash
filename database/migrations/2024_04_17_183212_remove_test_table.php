<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('test')) {
            Schema::dropColumns('testoutput', 'testid');

            Schema::table('build2test', function (Blueprint $table) {
                $table->string('testname', 255)
                    ->nullable(); // Temporarily make the column nullable
            });

            if (config('database.default') === 'pgsql') {
                DB::update('
                    UPDATE build2test
                    SET testname = test.name
                    FROM test
                    WHERE test.id = build2test.testid
                ');
            } else {
                DB::update('
                    UPDATE build2test, test
                    SET build2test.testname = test.name
                    WHERE test.id = build2test.testid
                ');
            }

            $num_b2t_rows_deleted = DB::delete('DELETE FROM build2test WHERE testname IS NULL');
            if ($num_b2t_rows_deleted > 0) {
                // Theoretically this case should never happen because there is a FK constraint on the testid
                echo "Deleted $num_b2t_rows_deleted rows with no test name from build2test table.";
            }

            // Take care of any invalid rows which do not have an output ID before we add a foreign key
            $num_b2t_rows_deleted = DB::delete('
                DELETE FROM build2test
                WHERE outputid NOT IN (
                    SELECT id
                    FROM testoutput
                )
            ');
            if ($num_b2t_rows_deleted > 0) {
                echo "Deleted $num_b2t_rows_deleted rows with no corresponding test output from build2test table.";
            }

            Schema::table('build2test', function (Blueprint $table) {
                $table->string('testname', 255)
                    ->nullable(false) // Make the testname column non-null
                    ->change();
                $table->dropColumn('testid');

                $table->foreign('outputid')->references('id')->on('testoutput')->cascadeOnDelete();

                $table->unique(['testname', 'id']);
                $table->unique(['id', 'testname']);

                $table->index(['testname', 'buildid']);
                $table->index(['buildid', 'testname']);

                $table->index(['testname', 'outputid']);
                $table->index(['outputid', 'testname']);

                $table->index(['buildid', 'outputid']);
                $table->index(['outputid', 'buildid']);
            });

            Schema::drop('test');
        } else {
            echo "Error: Unable to run migration because expected tables do not exist!";
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::create('test', function (Blueprint $table) {
            $table->id();
            $table->integer('projectid');
            $table->string('name');
            $table->foreign('projectid')->references('id')->on('project')->cascadeOnDelete();
            $table->unique(['name', 'projectid']);
        });

        DB::insert('
            INSERT INTO test(projectid, name) (
                SELECT DISTINCT project.id AS projectid, build2test.testname AS name
                FROM build2test
                INNER JOIN build ON build.id = build2test.buildid
                INNER JOIN project ON project.id = build.projectid
            )
        ');

        Schema::table('build2test', function (Blueprint $table) {
            $table->integer('testid')
                ->default(0)
                ->nullable(); // Make the column nullable temporarily
        });

        if (config('database.default') === 'pgsql') {
            DB::update('
                UPDATE build2test
                SET testid = test.id
                FROM test
                WHERE test.name = build2test.testname
            ');
        } else {
            DB::update('
                UPDATE build2test, test
                SET build2test.testid = test.id
                WHERE test.name = build2test.testname
            ');
        }

        Schema::table('build2test', function (Blueprint $table) {
            $table->integer('testid')
                ->default(0)
                ->nullable(false)
                ->change();
            $table->dropColumn('testname');
        });

        Schema::table('testoutput', function (Blueprint $table) {
            $table->integer('testid')
                ->default(0)
                ->nullable();
        });

        if (config('database.default') === 'pgsql') {
            DB::update('
                UPDATE testoutput
                SET testid = test.id
                FROM test
                INNER JOIN build2test ON build2test.testid = test.id
                WHERE testoutput.id = build2test.outputid
            ');
        } else {
            DB::update('
                UPDATE testoutput, build2test, test
                SET testoutput.testid = test.id
                WHERE
                    testoutput.id = build2test.outputid
                    AND test.id = build2test.testid
            ');
        }
    }
};
