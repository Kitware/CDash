<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateCoverageTable extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('coverage', function (Blueprint $table) {
            $table->integer('buildid')->default(0)->index();
            $table->integer('fileid')->default(0)->index();
            $table->tinyInteger('covered')->default(0)->index();
            $table->integer('loctested')->default(0);
            $table->integer('locuntested')->default(0);
            $table->integer('branchstested')->default(0);
            $table->integer('branchsuntested')->default(0);
            $table->integer('functionstested')->default(0);
            $table->integer('functionsuntested')->default(0);
        });
    }


    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('coverage');
    }
}
