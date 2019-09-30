<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateSummaryemailTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('summaryemail', function(Blueprint $table)
		{
			$table->bigInteger('buildid');
			$table->date('date')->index('date');
			$table->smallInteger('groupid')->index('groupid');
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('summaryemail');
	}

}
