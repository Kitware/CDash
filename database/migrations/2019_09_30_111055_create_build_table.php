<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateBuildTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('build', function(Blueprint $table)
		{
			$table->integer('id', true);
			$table->integer('siteid')->default(0)->index('siteid');
			$table->integer('projectid')->default(0)->index('projectid');
			$table->integer('parentid')->default(0)->index('parentid');
			$table->string('stamp')->default('')->index('stamp');
			$table->string('name')->default('')->index('name');
			$table->string('type')->default('')->index('type');
			$table->string('generator')->default('');
			$table->dateTime('starttime')->default('1980-01-01 00:00:00')->index('starttime');
			$table->dateTime('endtime')->default('1980-01-01 00:00:00');
			$table->dateTime('submittime')->default('1980-01-01 00:00:00')->index('submittime');
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
			$table->boolean('notified')->nullable()->default(0);
			$table->boolean('done')->nullable()->default(0);
			$table->string('uuid', 36)->unique('uuid');
			$table->string('changeid', 40)->nullable()->default('');
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
