<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateClientSite2programTable extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('client_site2program')) {
            Schema::create('client_site2program', function (Blueprint $table) {
                $table->integer('siteid')->index();
                $table->string('name', 30);
                $table->string('version', 30);
                $table->string('path', 512);
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
        Schema::drop('client_site2program');
    }
}
