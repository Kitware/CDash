<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class RemoveTestCheckboxes extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasColumn('measurement', 'testpage')) {
            // Return early if it looks like this migration has
            // already been performed.
            return;
        }

        // Remove testpage and summarypage columns on measurement table.
        Schema::table('measurement', function (Blueprint $table) {
            echo "Removing 'testpage' column on 'measurement' table" . PHP_EOL;
            $table->dropColumn('testpage');
            echo "Removing 'summarypage' column on 'measurement' table" . PHP_EOL;
            $table->dropColumn('summarypage');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        if (!Schema::hasColumn('measurement', 'testpage')) {
            Schema::table('measurement', function (Blueprint $table) {
                $table->tinyInteger('testpage');
                $table->tinyInteger('summarypage');
            });
        }
    }
}
