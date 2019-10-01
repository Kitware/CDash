<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateBlockbuildTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('blockbuild', function(Blueprint $table)
		{
			$table->integer('id', true);
			$table->integer('projectid')->index();
			$table->string('buildname')->index();
			$table->string('sitename')->index();
			$table->string('ipaddress', 50)->index();
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('blockbuild');
	}

}
