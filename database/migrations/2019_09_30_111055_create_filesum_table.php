<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateFilesumTable extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('filesum')) {
            Schema::create('filesum', function (Blueprint $table) {
                $table->integer('id', true);
                $table->string('md5sum', 32)->index();
                $table->binary('contents')->nullable();
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
        Schema::drop('filesum');
    }
}
