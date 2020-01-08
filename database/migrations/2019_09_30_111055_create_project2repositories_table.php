<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateProject2repositoriesTable extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('project2repositories')) {
            Schema::create('project2repositories', function (Blueprint $table) {
                $table->integer('projectid');
                $table->integer('repositoryid');
                $table->primary(['projectid','repositoryid']);
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
        Schema::drop('project2repositories');
    }
}
