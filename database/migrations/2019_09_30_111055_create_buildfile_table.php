<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateBuildfileTable extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('buildfile')) {
            Schema::create('buildfile', function (Blueprint $table) {
                $table->integer('buildid')->index();
                $table->string('filename')->index();
                $table->string('md5', 40)->index();
                $table->string('type', 32)->default('')->index();
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
        Schema::drop('buildfile');
    }
}
