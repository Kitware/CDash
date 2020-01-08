<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreatePendingSubmissionsTable extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('pending_submissions')) {
            Schema::create('pending_submissions', function (Blueprint $table) {
                $table->integer('buildid')->primary();
                $table->tinyInteger('numfiles')->default(0);
                $table->tinyInteger('recheck')->default(0);
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
        Schema::drop('pending_submissions');
    }
}
