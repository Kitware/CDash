<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateBuildemailTable extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('buildemail')) {
            Schema::create('buildemail', function (Blueprint $table) {
                $table->integer('userid');
                $table->bigInteger('buildid');
                $table->tinyInteger('category');
                $table->dateTime('time')->default('1980-01-01 00:00:00');

                $table->index('userid');
                $table->index('buildid');
                $table->index('category');
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
        Schema::drop('buildemail');
    }
}
