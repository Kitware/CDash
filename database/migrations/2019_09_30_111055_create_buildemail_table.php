<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateBuildemailTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('buildemail', function(Blueprint $table)
		{
			$table->integer('userid')->index('userid');
			$table->bigInteger('buildid')->index('buildid');
			$table->boolean('category')->index('category');
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
		Schema::drop('buildemail');
	}

}
