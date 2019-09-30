<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateSubprojectgroupTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('subprojectgroup', function(Blueprint $table)
		{
			$table->integer('id', true);
			$table->string('name')->index('name');
			$table->integer('projectid')->index('projectid');
			$table->smallInteger('coveragethreshold')->default(70);
			$table->boolean('is_default');
			$table->dateTime('starttime')->default('1980-01-01 00:00:00');
			$table->dateTime('endtime')->default('1980-01-01 00:00:00');
			$table->integer('position')->default(0)->index('position');
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('subprojectgroup');
	}

}
