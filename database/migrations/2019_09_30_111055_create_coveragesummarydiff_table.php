<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateCoveragesummarydiffTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('coveragesummarydiff')) {
            Schema::create('coveragesummarydiff', function (Blueprint $table) {
                $table->bigInteger('buildid')->primary();
                $table->integer('loctested')->default(0);
                $table->integer('locuntested')->default(0);
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
        Schema::drop('coveragesummarydiff');
    }
}
