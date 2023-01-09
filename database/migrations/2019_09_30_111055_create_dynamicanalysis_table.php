<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateDynamicanalysisTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('dynamicanalysis')) {
            Schema::create('dynamicanalysis', function (Blueprint $table) {
                $table->integer('id', true);
                $table->integer('buildid')->default(0)->index();
                $table->string('status', 10)->default('');
                $table->string('checker', 60)->default('');
                $table->string('name')->default('');
                $table->string('path')->default('');
                $table->string('fullcommandline')->default('');
                $table->text('log');
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
        Schema::drop('dynamicanalysis');
    }
}
