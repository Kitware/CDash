<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateTest2imageTable extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('test2image', function (Blueprint $table) {
            $table->bigInteger('id', true);
            $table->integer('imgid')->index();
            $table->integer('testid')->index();
            $table->text('role');
        });
    }


    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('test2image');
    }
}
