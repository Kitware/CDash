<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateBuildgrouppositionTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('buildgroupposition', function(Blueprint $table)
		{
			$table->integer('buildgroupid')->default(0)->index();
			$table->integer('position')->default(0)->index();
			$table->dateTime('starttime')->default('1980-01-01 00:00:00')->index();
			$table->dateTime('endtime')->default('1980-01-01 00:00:00')->index();
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('buildgroupposition');
	}

}
