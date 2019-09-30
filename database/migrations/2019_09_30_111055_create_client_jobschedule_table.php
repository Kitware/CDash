<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateClientJobscheduleTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('client_jobschedule', function(Blueprint $table)
		{
			$table->bigInteger('id', true)->unsigned();
			$table->integer('userid')->nullable()->index('userid');
			$table->integer('projectid')->nullable()->index('projectid');
			$table->text('cmakecache', 16777215);
			$table->text('clientscript', 65535)->nullable();
			$table->dateTime('startdate')->default('1980-01-01 00:00:00');
			$table->dateTime('enddate')->default('1980-01-01 00:00:00');
			$table->boolean('type');
			$table->time('starttime')->default('00:00:00')->index('starttime');
			$table->decimal('repeattime', 6)->default(0.00)->index('repeattime');
			$table->boolean('enable')->index('enable');
			$table->dateTime('lastrun')->default('1980-01-01 00:00:00');
			$table->string('repository', 512)->nullable()->default('');
			$table->string('module')->nullable()->default('');
			$table->string('buildnamesuffix')->nullable()->default('');
			$table->string('tag')->nullable()->default('');
			$table->boolean('buildconfiguration')->nullable()->default(0);
			$table->text('description', 65535)->nullable();
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('client_jobschedule');
	}

}
