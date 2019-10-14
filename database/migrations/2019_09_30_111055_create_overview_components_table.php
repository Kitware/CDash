<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateOverviewComponentsTable extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('overview_components', function (Blueprint $table) {
            $table->integer('projectid')->default(1)->index();
            $table->integer('buildgroupid')->default(0)->index();
            $table->integer('position')->default(0);
            $table->string('type', 32)->default('build');
        });
    }


    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('overview_components');
    }
}
