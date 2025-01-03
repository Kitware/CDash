<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (config('database.default') !== 'pgsql') {
            // We might have to drop these foreign key constraints if users have already run the newer migrations
            $testmeasurement_foreign_dropped = false;
            try {
                Schema::table('testmeasurement', function (Blueprint $table) {
                    $table->dropForeign(['testid']);
                });
                echo 'Dropped testmeasurement(testid) foreign key constraint';
                $testmeasurement_foreign_dropped = true;
            } catch (\Illuminate\Database\QueryException) {
                echo 'testmeasurement(testid) foreign key constraint does not exist.  No changes needed.';
            }

            $label2test_foreign_dropped = false;
            try {
                Schema::table('label2test', function (Blueprint $table) {
                    $table->dropForeign(['testid']);
                });
                echo 'Dropped label2test(testid) foreign key constraint';
                $label2test_foreign_dropped = true;
            } catch (\Illuminate\Database\QueryException) {
                echo 'label2test(testid) foreign key constraint does not exist.  No changes needed.';
            }
        }


        Schema::table('build2test', function (Blueprint $table) {
            // Convert to bigint type
            $table->id()->change();
        });

        if (config('database.default') !== 'pgsql') {
            // Restore the dropped foreign key constraints if needed
            if ($testmeasurement_foreign_dropped) {
                Schema::table('testmeasurement', function (Blueprint $table) {
                    $table->foreignId('testid')->change();
                    $table->foreign('testid')->references('id')->on('build2test')->cascadeOnDelete();
                });
            }
            if ($label2test_foreign_dropped) {
                Schema::table('label2test', function (Blueprint $table) {
                    $table->foreignId('testid')->change();
                    $table->foreign('testid')->references('id')->on('build2test')->cascadeOnDelete();
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No down migration because we don't know which type the column was before the migration
    }
};
