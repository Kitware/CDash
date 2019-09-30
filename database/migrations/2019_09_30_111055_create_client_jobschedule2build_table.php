<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateClientJobschedule2buildTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('client_jobschedule2build', function(Blueprint $table)
		{
			$table->bigInteger('scheduleid')->unsigned();
			$table->integer('buildid');
			$table->unique(['scheduleid','buildid'], 'scheduleid');
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('client_jobschedule2build');
	}

}
