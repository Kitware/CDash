<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateMeasurementTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('measurement', function(Blueprint $table)
		{
			$table->integer('id', true);
			$table->integer('projectid')->index();
			$table->string('name')->index();
			$table->tinyInteger('testpage');
			$table->tinyInteger('summarypage');
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('measurement');
	}

}
