<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateCoveragefilelogTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('coveragefilelog', function(Blueprint $table)
		{
			$table->integer('buildid')->default(0)->index('buildid');
			$table->integer('fileid')->default(0)->index('fileid');
			$table->binary('log');
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('coveragefilelog');
	}

}
