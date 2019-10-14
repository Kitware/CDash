<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateBuildtesttimeTable extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('buildtesttime', function (Blueprint $table) {
            $table->integer('buildid')->default(0)->primary();
            $table->float('time', 7)->default(0.00);
        });
    }


    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('buildtesttime');
    }
}
