<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateClientSite2compilerTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('client_site2compiler', function(Blueprint $table)
		{
			$table->integer('siteid')->nullable()->index('siteid');
			$table->integer('compilerid')->nullable();
			$table->string('command', 512)->nullable();
			$table->string('generator');
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('client_site2compiler');
	}

}
