<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateLabel2dynamicanalysisTable extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('label2dynamicanalysis', function (Blueprint $table) {
            $table->bigInteger('labelid');
            $table->bigInteger('dynamicanalysisid');
            $table->primary(['labelid','dynamicanalysisid']);
        });
    }


    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('label2dynamicanalysis');
    }
}
