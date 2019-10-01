<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateClientJobschedule2compilerTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('client_jobschedule2compiler', function(Blueprint $table)
		{
			$table->bigInteger('scheduleid');
			$table->integer('compilerid');
			$table->unique(['scheduleid','compilerid'], 'client_jobschedule2compiler_scheduleid');
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('client_jobschedule2compiler');
	}

}
