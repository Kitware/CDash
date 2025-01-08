<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateClientJobschedule2osTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('client_jobschedule2os')) {
            Schema::create('client_jobschedule2os', function (Blueprint $table) {
                $table->bigInteger('scheduleid');
                $table->integer('osid');
                $table->unique(['scheduleid', 'osid'], 'client_jobschedule2os_scheduleid');
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
        Schema::drop('client_jobschedule2os');
    }
}
