<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateBuildfailureTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('buildfailure', function(Blueprint $table)
		{
			$table->bigInteger('id', true);
			$table->bigInteger('buildid')->index('buildid');
			$table->bigInteger('detailsid')->index('detailsid');
			$table->string('workingdirectory', 512);
			$table->string('sourcefile', 512);
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
		Schema::drop('buildfailure');
	}

}
