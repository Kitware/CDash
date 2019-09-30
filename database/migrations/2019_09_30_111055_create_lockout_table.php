<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateLockoutTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('lockout', function(Blueprint $table)
		{
			$table->integer('userid')->primary();
			$table->boolean('failedattempts')->nullable()->default(0);
			$table->boolean('islocked')->default(0);
			$table->dateTime('unlocktime')->default('1980-01-01 00:00:00');
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('lockout');
	}

}
