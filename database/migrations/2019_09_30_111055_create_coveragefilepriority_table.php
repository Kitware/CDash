<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateCoveragefilepriorityTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('coveragefilepriority', function(Blueprint $table)
		{
			$table->bigInteger('id', true);
			$table->boolean('priority')->index('priority');
			$table->string('fullpath')->index('fullpath');
			$table->integer('projectid')->index('projectid');
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('coveragefilepriority');
	}

}
