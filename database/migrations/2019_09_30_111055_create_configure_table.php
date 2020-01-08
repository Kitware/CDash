<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateConfigureTable extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('configure')) {
            Schema::create('configure', function (Blueprint $table) {
                $table->integer('id', true);
                $table->text('command', 65535);
                $table->text('log', 16777215);
                $table->tinyInteger('status')->default(0);
                $table->smallInteger('warnings')->nullable()->default(-1);
                $table->bigInteger('crc32')->unique();
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
        Schema::drop('configure');
    }
}
