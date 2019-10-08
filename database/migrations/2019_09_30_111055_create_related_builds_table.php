<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateRelatedBuildsTable extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('related_builds', function (Blueprint $table) {
            $table->bigInteger('buildid')->index();
            $table->bigInteger('relatedid')->index();
            $table->string('relationship')->nullable();
            $table->primary(['buildid','relatedid']);
        });
    }


    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('related_builds');
    }
}
