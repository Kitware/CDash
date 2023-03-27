<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class IncreaseTestMeasurementValueSize extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('testmeasurement', function (Blueprint $table) {
            // The blank comment is used to work around a limitation in
            // doctrine/dbal. See:
            // https://github.com/doctrine/dbal/issues/2566
            $table->mediumText('value')->comment(' ')->change(); // up
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('testmeasurement', function (Blueprint $table) {
            $table->text('value')->comment('')->change(); // down
        });
    }
}
