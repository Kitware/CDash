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
			$table->string('name')->index('name');
			$table->string('version')->index('version');
			$table->boolean('bits')->default(32)->index('bits');
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
