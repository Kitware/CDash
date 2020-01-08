<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateBuildfailureTable extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('buildfailure')) {
            Schema::create('buildfailure', function (Blueprint $table) {
                $table->bigInteger('id', true);
                $table->bigInteger('buildid')->index();
                $table->bigInteger('detailsid')->index();
                $table->string('workingdirectory', 512);
                $table->string('sourcefile', 512);
                $table->tinyInteger('newstatus')->default(0)->index();
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
        Schema::drop('buildfailure');
    }
}
