<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateSubproject2buildTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('subproject2build')) {
            Schema::create('subproject2build', function (Blueprint $table) {
                $table->integer('subprojectid')->index();
                $table->bigInteger('buildid')->primary();
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
        Schema::drop('subproject2build');
    }
}
