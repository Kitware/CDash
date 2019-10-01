<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateClientSite2libraryTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('client_site2library', function(Blueprint $table)
		{
			$table->integer('siteid')->nullable()->index();
			$table->integer('libraryid')->nullable();
			$table->string('path', 512)->nullable();
			$table->string('include', 512);
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('client_site2library');
	}

}
