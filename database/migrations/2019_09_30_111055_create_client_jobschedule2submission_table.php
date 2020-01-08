<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateClientJobschedule2submissionTable extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('client_jobschedule2submission')) {
            Schema::create('client_jobschedule2submission', function (Blueprint $table) {
                $table->bigInteger('scheduleid')->unique();
                $table->bigInteger('submissionid')->primary();
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
        Schema::drop('client_jobschedule2submission');
    }
}
