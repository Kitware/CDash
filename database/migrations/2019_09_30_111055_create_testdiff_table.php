<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateTestdiffTable extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('testdiff')) {
            Schema::create('testdiff', function (Blueprint $table) {
                $table->bigInteger('buildid')->index();
                $table->tinyInteger('type')->index();
                $table->integer('difference_positive')->index();
                $table->integer('difference_negative')->index();
                $table->unique(['buildid','type'], 'unique_testdiff');
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
        Schema::drop('testdiff');
    }
}
