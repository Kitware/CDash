<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateBuildupdateTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('buildupdate', function(Blueprint $table)
		{
			$table->integer('id', true);
			$table->dateTime('starttime')->default('1980-01-01 00:00:00');
			$table->dateTime('endtime')->default('1980-01-01 00:00:00');
			$table->text('command', 65535);
			$table->string('type', 4)->default('');
			$table->text('status', 65535);
			$table->smallInteger('nfiles')->nullable()->default(-1);
			$table->smallInteger('warnings')->nullable()->default(-1);
			$table->string('revision', 60)->default('0')->index();
			$table->string('priorrevision', 60)->default('0');
			$table->string('path')->default('');
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('buildupdate');
	}

}
