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
        Schema::table('testmeasurement', function (Blueprint $table) {
            $table->foreignId('testid')
                ->nullable(); // Temporarily make the column nullable
        });

        DB::insert('
            INSERT INTO testmeasurement (
                outputid,
                name,
                type,
                value,
                testid
            )
            SELECT
                testmeasurement.outputid,
                testmeasurement.name,
                testmeasurement.type,
                testmeasurement.value,
                build2test.id
            FROM testmeasurement
            JOIN build2test ON testmeasurement.outputid = build2test.outputid
        ');

        // Delete any entries which will fail the FK constraint (including the old values which now have a null testid)
        DB::delete('
            DELETE FROM testmeasurement
            WHERE
                testid IS NULL OR
                NOT EXISTS (
                    SELECT 1
                    FROM build2test
                    WHERE build2test.id = testmeasurement.testid
                )
        ');

        Schema::table('testmeasurement', function (Blueprint $table) {
            $table->foreignId('testid')
                ->nullable(false) // Add a not-null constraint
                ->index()
                ->change();
            $table->foreign('testid')->references('id')->on('build2test')->cascadeOnDelete();
            $table->dropColumn('outputid');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('testmeasurement', function (Blueprint $table) {
            $table->foreignId('testid')
                ->nullable()
                ->change();

            $table->integer('outputid')
                ->nullable(); // Temporarily make the column nullable
        });

        DB::insert('
            INSERT INTO testmeasurement (
                outputid,
                name,
                type,
                value
            )
            SELECT
                build2test.outputid,
                testmeasurement.name,
                testmeasurement.type,
                testmeasurement.value
            FROM testmeasurement
            JOIN build2test ON testmeasurement.testid = build2test.id
            GROUP BY
                build2test.outputid,
                testmeasurement.name,
                testmeasurement.type,
                testmeasurement.value
        ');

        // Delete all of the old entries
        DB::delete('DELETE FROM testmeasurement WHERE outputid IS NULL');

        Schema::table('testmeasurement', function (Blueprint $table) {
            $table->dropForeign(['testid']);
            $table->dropColumn('testid');
            $table->integer('outputid')
                ->nullable(false) // Revert back to a not-null column
                ->index()
                ->change();
        });
    }
};
