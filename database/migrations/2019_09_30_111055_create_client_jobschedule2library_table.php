<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateClientJobschedule2libraryTable extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('client_jobschedule2library', function (Blueprint $table) {
            $table->bigInteger('scheduleid');
            $table->integer('libraryid');
            $table->unique(['scheduleid','libraryid'], 'client_jobschedule2library_scheduleid');
        });
    }


    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('client_jobschedule2library');
    }
}
