<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateCoveragefile2userTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('coveragefile2user', function(Blueprint $table)
		{
			$table->bigInteger('fileid')->index();
			$table->bigInteger('userid')->index();
			$table->tinyInteger('position');
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('coveragefile2user');
	}

}
