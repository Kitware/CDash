<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateLabel2buildTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('label2build', function(Blueprint $table)
		{
			$table->bigInteger('labelid')->index('labelid');
			$table->bigInteger('buildid')->index('buildid');
			$table->primary(['labelid','buildid']);
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('label2build');
	}

}
