<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

class IncreaseDynamicAnalysisDefectTypeSize extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('dynamicanalysisdefect', function ($table) {
            $table->string('type', 255)->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('dynamicanalysisdefect', function ($table) {
            $table->string('type', 50)->change();
        });
    }
}
