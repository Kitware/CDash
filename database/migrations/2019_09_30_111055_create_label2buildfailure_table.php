<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateLabel2buildfailureTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('label2buildfailure', function(Blueprint $table)
		{
			$table->bigInteger('labelid');
			$table->bigInteger('buildfailureid');
			$table->primary(['labelid','buildfailureid']);
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('label2buildfailure');
	}

}
