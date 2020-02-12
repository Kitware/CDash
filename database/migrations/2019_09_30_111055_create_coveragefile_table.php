<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateCoveragefileTable extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('coveragefile')) {
            Schema::create('coveragefile', function (Blueprint $table) {
                $table->integer('id', true);
                $table->string('fullpath')->default('')->index();
                $table->binary('file')->nullable();
                $table->bigInteger('crc32')->nullable()->index();
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
        Schema::drop('coveragefile');
    }
}
