<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateClientJobschedule2cmakeTable extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('client_jobschedule2cmake', function (Blueprint $table) {
            $table->bigInteger('scheduleid');
            $table->integer('cmakeid');
            $table->unique(['scheduleid','cmakeid'], 'client_jobschedule2cmake_scheduleid');
        });
    }


    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('client_jobschedule2cmake');
    }
}
