<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateBuildfailuredetailsTable extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('buildfailuredetails', function (Blueprint $table) {
            $table->bigInteger('id', true);
            $table->tinyInteger('type')->index();
            $table->text('stdoutput', 16777215);
            $table->text('stderror', 16777215);
            $table->string('exitcondition');
            $table->string('language', 64);
            $table->string('targetname');
            $table->string('outputfile', 512);
            $table->string('outputtype');
            $table->bigInteger('crc32')->default(0)->index();
        });
    }


    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('buildfailuredetails');
    }
}
