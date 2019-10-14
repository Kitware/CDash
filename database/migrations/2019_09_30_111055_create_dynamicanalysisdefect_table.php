<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateDynamicanalysisdefectTable extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('dynamicanalysisdefect', function (Blueprint $table) {
            $table->integer('dynamicanalysisid')->default(0)->index();
            $table->string('type', 50)->default('');
            $table->integer('value')->default(0);
        });
    }


    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('dynamicanalysisdefect');
    }
}
