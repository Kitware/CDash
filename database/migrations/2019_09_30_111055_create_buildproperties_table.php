<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateBuildpropertiesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('buildproperties')) {
            Schema::create('buildproperties', function (Blueprint $table) {
                $table->integer('buildid')->default(0)->primary();
                $table->text('properties', 16777215);
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
        Schema::drop('buildproperties');
    }
}
