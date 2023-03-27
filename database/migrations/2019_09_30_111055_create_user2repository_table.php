<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateUser2repositoryTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('user2repository')) {
            Schema::create('user2repository', function (Blueprint $table) {
                $table->integer('userid')->index();
                $table->string('credential')->index();
                $table->integer('projectid')->default(0)->index();
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
        Schema::drop('user2repository');
    }
}
