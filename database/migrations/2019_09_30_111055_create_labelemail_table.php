<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateLabelemailTable extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('labelemail')) {
            Schema::create('labelemail', function (Blueprint $table) {
                $table->integer('projectid')->index();
                $table->integer('userid')->index();
                $table->bigInteger('labelid');
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
        Schema::drop('labelemail');
    }
}
