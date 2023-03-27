<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateSite2userTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('site2user')) {
            Schema::create('site2user', function (Blueprint $table) {
                $table->integer('siteid')->default(0)->index();
                $table->integer('userid')->default(0)->index();
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
        Schema::drop('site2user');
    }
}
