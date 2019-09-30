<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateProjectrobotTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('projectrobot', function(Blueprint $table)
		{
			$table->integer('projectid')->index('projectid');
			$table->string('robotname')->index('robotname');
			$table->string('authorregex', 512);
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('projectrobot');
	}

}
