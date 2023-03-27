<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateBuild2configureTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('build2configure')) {
            Schema::create('build2configure', function (Blueprint $table) {
                $table->integer('configureid')->default(0)->index();
                $table->integer('buildid')->default(0)->primary();
                $table->dateTime('starttime')->default('1980-01-01 00:00:00');
                $table->dateTime('endtime')->default('1980-01-01 00:00:00');
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
        Schema::drop('build2configure');
    }
}
