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
			$table->integer('userid')->nullable()->index();
			$table->integer('projectid')->nullable()->index();
			$table->text('cmakecache', 16777215);
			$table->text('clientscript', 65535)->nullable();
			$table->dateTime('startdate')->default('1980-01-01 00:00:00');
			$table->dateTime('enddate')->default('1980-01-01 00:00:00');
			$table->tinyInteger('type');
			$table->time('starttime')->default('00:00:00')->index();
			$table->decimal('repeattime', 6)->default(0.00)->index();
			$table->tinyInteger('enable')->index();
			$table->dateTime('lastrun')->default('1980-01-01 00:00:00');
			$table->string('repository', 512)->nullable()->default('');
			$table->string('module')->nullable()->default('');
			$table->string('buildnamesuffix')->nullable()->default('');
			$table->string('tag')->nullable()->default('');
			$table->tinyInteger('buildconfiguration')->nullable()->default(0);
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
