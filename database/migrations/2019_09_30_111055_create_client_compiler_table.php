<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateClientCompilerTable extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('client_compiler')) {
            Schema::create('client_compiler', function (Blueprint $table) {
                $table->integer('id', true);
                $table->string('name');
                $table->string('version');
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
        Schema::drop('client_compiler');
    }
}
