<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateLabel2testTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('label2test', function(Blueprint $table)
		{
			$table->bigInteger('labelid');
			$table->bigInteger('buildid')->index('buildid');
			$table->bigInteger('testid')->index('testid');
			$table->primary(['labelid','buildid','testid']);
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('label2test');
	}

}
