<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateBuild2noteTable extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('build2note')) {
            Schema::create('build2note', function (Blueprint $table) {
                $table->bigInteger('buildid')->index();
                $table->bigInteger('noteid')->index();
                $table->dateTime('time')->default('1980-01-01 00:00:00');
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
        Schema::drop('build2note');
    }
}
