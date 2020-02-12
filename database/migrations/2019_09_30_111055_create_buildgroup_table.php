<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateBuildgroupTable extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('buildgroup')) {
            Schema::create('buildgroup', function (Blueprint $table) {
                $table->integer('id', true);
                $table->string('name')->default('');
                $table->integer('projectid')->default(0)->index();
                $table->dateTime('starttime')->default('1980-01-01 00:00:00')->index();
                $table->dateTime('endtime')->default('1980-01-01 00:00:00')->index();
                $table->integer('autoremovetimeframe')->nullable()->default(0);
                $table->text('description', 65535);
                $table->tinyInteger('summaryemail')->nullable()->default(0);
                $table->tinyInteger('includesubprojectotal')->nullable()->default(1);
                $table->tinyInteger('emailcommitters')->nullable()->default(0);
                $table->string('type', 20)->default('Daily')->index();
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
        Schema::drop('buildgroup');
    }
}
