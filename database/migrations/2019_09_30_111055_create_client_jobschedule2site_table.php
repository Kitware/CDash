<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateClientJobschedule2siteTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('client_jobschedule2site')) {
            Schema::create('client_jobschedule2site', function (Blueprint $table) {
                $table->bigInteger('scheduleid');
                $table->integer('siteid');
                $table->unique(['scheduleid','siteid'], 'client_jobschedule2site_scheduleid');
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
        Schema::drop('client_jobschedule2site');
    }
}
