<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateUser2repositoryTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('user2repository', function(Blueprint $table)
		{
			$table->integer('userid')->index('userid');
			$table->string('credential')->index('credential');
			$table->integer('projectid')->default(0)->index('projectid');
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('user2repository');
	}

}
