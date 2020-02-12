<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateImageTable extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('image')) {
            Schema::create('image', function (Blueprint $table) {
                $table->integer('id', true);
                $table->binary('img');
                $table->text('extension');
                $table->bigInteger('checksum')->index();
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
        Schema::drop('image');
    }
}
