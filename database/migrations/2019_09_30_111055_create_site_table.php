<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateSiteTable extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('site')) {
            Schema::create('site', function (Blueprint $table) {
                $table->integer('id', true);
                $table->string('name')->default('')->unique();
                $table->string('ip')->default('');
                $table->string('latitude', 10)->default('');
                $table->string('longitude', 10)->default('');
                $table->tinyInteger('outoforder')->default(0);
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
        Schema::drop('site');
    }
}
