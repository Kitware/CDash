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
			$table->integer('projectid')->index('projectid');
			$table->string('buildname')->index('buildname');
			$table->string('sitename')->index('sitename');
			$table->string('ipaddress', 50)->index('ipaddress');
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
