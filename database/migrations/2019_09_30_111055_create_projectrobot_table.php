<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateProjectrobotTable extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('projectrobot')) {
            Schema::create('projectrobot', function (Blueprint $table) {
                $table->integer('projectid')->index();
                $table->string('robotname')->index();
                $table->string('authorregex', 512);
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
        Schema::drop('projectrobot');
    }
}
