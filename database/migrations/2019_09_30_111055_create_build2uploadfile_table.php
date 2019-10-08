<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateBuild2uploadfileTable extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('build2uploadfile', function (Blueprint $table) {
            $table->bigInteger('fileid');
            $table->bigInteger('buildid');

            $table->index('fileid');
            $table->index('buildid');
        });
    }


    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('build2uploadfile');
    }
}
