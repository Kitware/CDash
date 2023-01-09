<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateLabel2coveragefileTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('label2coveragefile')) {
            Schema::create('label2coveragefile', function (Blueprint $table) {
                $table->bigInteger('labelid');
                $table->bigInteger('buildid');
                $table->bigInteger('coveragefileid');
                $table->primary(['labelid','buildid','coveragefileid']);
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
        Schema::drop('label2coveragefile');
    }
}
