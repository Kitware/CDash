<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateSubproject2subprojectTable extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('subproject2subproject')) {
            Schema::create('subproject2subproject', function (Blueprint $table) {
                $table->integer('subprojectid')->index();
                $table->integer('dependsonid')->index();
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
        Schema::drop('subproject2subproject');
    }
}
