<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateBuildFiltersTable extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('build_filters')) {
            Schema::create('build_filters', function (Blueprint $table) {
                $table->integer('projectid')->primary();
                $table->text('warnings', 65535)->nullable();
                $table->text('errors', 65535)->nullable();
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
        Schema::drop('build_filters');
    }
}
