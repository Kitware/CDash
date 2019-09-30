<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateBuildinformationTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('buildinformation', function(Blueprint $table)
		{
			$table->integer('buildid')->primary();
			$table->string('osname');
			$table->string('osplatform');
			$table->string('osrelease');
			$table->string('osversion');
			$table->string('compilername');
			$table->string('compilerversion', 20);
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('buildinformation');
	}

}
