<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateClientOsTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('client_os', function(Blueprint $table)
		{
			$table->integer('id', true);
			$table->string('name')->index();
			$table->string('version')->index();
			$table->tinyInteger('bits')->default(32)->index();
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('client_os');
	}

}
