<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateSubmissionTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('submission', function(Blueprint $table)
		{
			$table->bigInteger('id', true);
			$table->string('filename', 500);
			$table->integer('projectid')->index('projectid');
			$table->boolean('status')->index('status');
			$table->integer('attempts')->default(0);
			$table->integer('filesize')->default(0);
			$table->string('filemd5sum', 32)->default('');
			$table->dateTime('lastupdated')->default('1980-01-01 00:00:00');
			$table->dateTime('created')->default('1980-01-01 00:00:00');
			$table->dateTime('started')->default('1980-01-01 00:00:00');
			$table->dateTime('finished')->default('1980-01-01 00:00:00')->index('finished');
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('submission');
	}

}
