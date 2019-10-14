<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateBuildTable extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('build', function (Blueprint $table) {
            $table->integer('id', true);
            $table->integer('siteid')->default(0);
            $table->integer('projectid')->default(0);
            $table->integer('parentid')->default(0);
            $table->string('stamp')->default('');
            $table->string('name')->default('');
            $table->string('type')->default('');
            $table->string('generator')->default('');
            $table->dateTime('starttime')->default('1980-01-01 00:00:00');
            $table->dateTime('endtime')->default('1980-01-01 00:00:00');
            $table->dateTime('submittime')->default('1980-01-01 00:00:00');
            $table->text('command', 65535);
            $table->text('log', 65535);
            $table->smallInteger('configureerrors')->nullable()->default(-1);
            $table->smallInteger('configurewarnings')->nullable()->default(-1);
            $table->integer('configureduration')->default(0);
            $table->smallInteger('builderrors')->nullable()->default(-1);
            $table->smallInteger('buildwarnings')->nullable()->default(-1);
            $table->integer('buildduration')->default(0);
            $table->smallInteger('testnotrun')->nullable()->default(-1);
            $table->smallInteger('testfailed')->nullable()->default(-1);
            $table->smallInteger('testpassed')->nullable()->default(-1);
            $table->smallInteger('testtimestatusfailed')->nullable()->default(-1);
            $table->integer('testduration')->default(0);
            $table->tinyInteger('notified')->nullable()->default(0);
            $table->tinyInteger('done')->nullable()->default(0);
            $table->string('uuid', 36)->unique();
            $table->string('changeid', 40)->nullable()->default('');

            $table->index('siteid');
            $table->index('projectid');
            $table->index('parentid');
            $table->index('stamp');
            $table->index('name');
            $table->index('type');
            $table->index('starttime');
            $table->index('submittime');

            $table->index(['projectid','parentid','starttime'], 'projectid_parentid_starttime');
        });
    }


    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('build');
    }
}
