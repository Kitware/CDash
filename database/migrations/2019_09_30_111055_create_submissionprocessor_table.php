<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateSubmissionprocessorTable extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('submissionprocessor')) {
            Schema::create('submissionprocessor', function (Blueprint $table) {
                $table->integer('projectid')->primary();
                $table->integer('pid');
                $table->dateTime('lastupdated')->default('1980-01-01 00:00:00');
                $table->dateTime('locked')->default('1980-01-01 00:00:00');
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
        Schema::drop('submissionprocessor');
    }
}
