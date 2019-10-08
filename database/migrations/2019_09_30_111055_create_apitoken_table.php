<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateApitokenTable extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('apitoken', function (Blueprint $table) {
            $table->integer('projectid');
            $table->string('token', 40)->nullable()->index();
            $table->dateTime('expiration_date')->default('1980-01-01 00:00:00');
        });
    }


    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('apitoken');
    }
}
