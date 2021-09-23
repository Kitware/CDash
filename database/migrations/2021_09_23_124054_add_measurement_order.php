<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddMeasurementOrder extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        echo "!!! this gets called" . PHP_EOL;
        if (Schema::hasColumn('measurement', 'position')) {
            // Return early if it looks like this migration has
            // already been performed.
            echo "!!! return early" . PHP_EOL;
            return;
        }

        // Add position column on measurement table.
        echo "Adding 'position' column on 'measurement' table" . PHP_EOL;
        Schema::table('measurement', function (Blueprint $table) {
            $table->unsignedSmallInteger('position')->default(0);
        });

        // Set default position for existing measurements:
        // alphabetical order, per project.
        $projectid_rows = DB::table('measurement')->select('projectid')->get();
        foreach ($projectid_rows as $projectid_row) {
            $pos = 1;
            $measurementid_rows = DB::table('measurement')
                ->select('id')
                ->where('projectid', $projectid_row->projectid)
                ->orderBy('name')
                ->get();
            foreach ($measurementid_rows as $measurementid_row) {
                DB::table('measurement')
                    ->where('id', $measurementid_row->id)
                    ->update(['position' => $pos]);
                $pos += 1;
            }
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        if (Schema::hasColumn('measurement', 'position')) {
            Schema::table('measurement', function (Blueprint $table) {
                $table->dropColumn('position');
            });
        }
    }
}
