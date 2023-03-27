<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateCoveragefilepriorityTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('coveragefilepriority')) {
            Schema::create('coveragefilepriority', function (Blueprint $table) {
                $table->bigInteger('id', true);
                $table->tinyInteger('priority')->index();
                $table->string('fullpath')->index();
                $table->integer('projectid')->index();
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
        Schema::drop('coveragefilepriority');
    }
}
