<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateBuildnoteTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('buildnote')) {
            Schema::create('buildnote', function (Blueprint $table) {
                $table->integer('buildid')->index();
                $table->integer('userid');
                $table->text('note', 16777215);
                $table->dateTime('timestamp');
                $table->tinyInteger('status')->default(0);
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
        Schema::drop('buildnote');
    }
}
