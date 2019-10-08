<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateDynamicanalysissummaryTable extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('dynamicanalysissummary', function (Blueprint $table) {
            $table->integer('buildid')->default(0)->primary();
            $table->string('checker', 60)->default('');
            $table->integer('numdefects')->default(0);
        });
    }


    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('dynamicanalysissummary');
    }
}
