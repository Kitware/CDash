<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateTestmeasurementTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('testmeasurement', function(Blueprint $table)
		{
			$table->bigInteger('id', true);
			$table->bigInteger('testid')->index('testid');
			$table->string('name', 70);
			$table->string('type', 70);
			$table->text('value', 65535);
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('testmeasurement');
	}

}
