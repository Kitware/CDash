<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateBuildfileTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('buildfile', function(Blueprint $table)
		{
			$table->integer('buildid')->index('buildid');
			$table->string('filename')->index('filename');
			$table->string('md5', 40)->index('md5');
			$table->string('type', 32)->default('')->index('type');
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('buildfile');
	}

}
