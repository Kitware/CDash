<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class DropSubmissionTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::drop('submission');
        Schema::drop('submission2ip');
        Schema::drop('submissionprocessor');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        if (!Schema::hasTable('submission')) {
            Schema::create('submission', function (Blueprint $table) {
                $table->bigInteger('id', true);
                $table->string('filename', 500);
                $table->integer('projectid')->index();
                $table->tinyInteger('status')->index();
                $table->integer('attempts')->default(0);
                $table->integer('filesize')->default(0);
                $table->string('filemd5sum', 32)->default('');
                $table->dateTime('lastupdated')->default('1980-01-01 00:00:00');
                $table->dateTime('created')->default('1980-01-01 00:00:00');
                $table->dateTime('started')->default('1980-01-01 00:00:00');
                $table->dateTime('finished')->default('1980-01-01 00:00:00')->index();
            });
        }
        if (!Schema::hasTable('submission2ip')) {
            Schema::create('submission2ip', function (Blueprint $table) {
                $table->bigInteger('submissionid')->primary();
                $table->string('ip')->default('');
            });
        }
        if (!Schema::hasTable('submissionprocessor')) {
            Schema::create('submissionprocessor', function (Blueprint $table) {
                $table->integer('projectid')->primary();
                $table->integer('pid');
                $table->dateTime('lastupdated')->default('1980-01-01 00:00:00');
                $table->dateTime('locked')->default('1980-01-01 00:00:00');
            });
        }
    }
}
