<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateClientJobTable extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('client_job')) {
            Schema::create('client_job', function (Blueprint $table) {
                $table->bigInteger('id', true)->unsigned();
                $table->bigInteger('scheduleid')->index();
                $table->tinyInteger('osid');
                $table->integer('siteid')->nullable();
                $table->dateTime('startdate')->default('1980-01-01 00:00:00')->index();
                $table->dateTime('enddate')->default('1980-01-01 00:00:00')->index();
                $table->integer('status')->nullable()->index();
                $table->text('output', 65535)->nullable();
                $table->integer('cmakeid');
                $table->integer('compilerid');
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
        Schema::drop('client_job');
    }
}
