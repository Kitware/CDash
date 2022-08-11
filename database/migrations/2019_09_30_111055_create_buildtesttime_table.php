<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateBuildtesttimeTable extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('buildtesttime')) {
            Schema::create('buildtesttime', function (Blueprint $table) {
                $table->integer('buildid')->default(0)->primary();
                $table->decimal('time', 7, 2)->default(0.00);
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
        Schema::drop('buildtesttime');
    }
}
