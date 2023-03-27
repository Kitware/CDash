<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateVersionTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('version')) {
            Schema::create('version', function (Blueprint $table) {
                $table->tinyInteger('major');
                $table->tinyInteger('minor');
                $table->tinyInteger('patch');
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
        Schema::drop('version');
    }
}
