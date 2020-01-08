<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateProjectjobscriptTable extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('projectjobscript')) {
            Schema::create('projectjobscript', function (Blueprint $table) {
                $table->integer('id', true);
                $table->integer('projectid')->index();
                $table->text('script');
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
        Schema::drop('projectjobscript');
    }
}
