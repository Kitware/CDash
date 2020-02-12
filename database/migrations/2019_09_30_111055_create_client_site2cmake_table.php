<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateClientSite2cmakeTable extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('client_site2cmake')) {
            Schema::create('client_site2cmake', function (Blueprint $table) {
                $table->integer('siteid')->nullable()->index();
                $table->integer('cmakeid')->nullable()->index();
                $table->string('path', 512)->nullable();
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
        Schema::drop('client_site2cmake');
    }
}
