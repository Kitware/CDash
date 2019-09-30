<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateBuild2testTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('build2test', function(Blueprint $table)
		{
			$table->integer('buildid')->default(0)->index('buildid');
			$table->integer('testid')->default(0)->index('testid');
			$table->string('status', 10)->default('')->index('status');
			$table->float('time', 7)->default(0.00);
			$table->float('timemean', 7)->default(0.00);
			$table->float('timestd', 7)->default(0.00);
			$table->boolean('timestatus')->default(0)->index('timestatus');
			$table->boolean('newstatus')->default(0)->index('newstatus');
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('build2test');
	}

}
