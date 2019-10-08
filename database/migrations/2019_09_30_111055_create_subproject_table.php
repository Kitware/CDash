<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateSubprojectTable extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('subproject', function (Blueprint $table) {
            $table->bigInteger('id', true);
            $table->string('name');
            $table->integer('projectid')->index();
            $table->integer('groupid')->index();
            $table->string('path', 512)->default('')->index();
            $table->smallInteger('position')->unsigned()->default(0);
            $table->dateTime('starttime')->default('1980-01-01 00:00:00');
            $table->dateTime('endtime')->default('1980-01-01 00:00:00');
            $table->unique(['name','projectid','endtime'], 'subproject_unique_key');
        });
    }


    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('subproject');
    }
}
