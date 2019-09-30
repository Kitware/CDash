<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateCoveragesummaryTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('coveragesummary', function(Blueprint $table)
		{
			$table->integer('buildid')->default(0)->primary();
			$table->integer('loctested')->default(0);
			$table->integer('locuntested')->default(0);
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('coveragesummary');
	}

}
