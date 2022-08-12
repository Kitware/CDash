<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateBuild2testTable extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('build2test')) {
            Schema::create('build2test', function (Blueprint $table) {
                $table->integer('buildid')->default(0);
                $table->integer('testid')->default(0);
                $table->string('status', 10)->default('');
                $table->decimal('time', 7, 2)->default(0.00);
                $table->decimal('timemean', 7, 2)->default(0.00);
                $table->decimal('timestd', 7, 2)->default(0.00);
                $table->tinyInteger('timestatus')->default(0);
                $table->tinyInteger('newstatus')->default(0);

                $table->index('buildid');
                $table->index('testid');
                $table->index('status');
                $table->index('timestatus');
                $table->index('newstatus');
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
        Schema::drop('build2test');
    }
}
