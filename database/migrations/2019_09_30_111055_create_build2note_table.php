<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateBuild2noteTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('build2note', function(Blueprint $table)
		{
			$table->bigInteger('buildid')->index('buildid');
			$table->bigInteger('noteid')->index('noteid');
			$table->dateTime('time')->default('1980-01-01 00:00:00');
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('build2note');
	}

}
