<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateBuild2groupTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('build2group')) {
            Schema::create('build2group', function (Blueprint $table) {
                $table->integer('groupid')->default(0)->index();
                $table->integer('buildid')->default(0)->primary();
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
        Schema::drop('build2group');
    }
}
