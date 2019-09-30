<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateBuildgroupTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('buildgroup', function(Blueprint $table)
		{
			$table->integer('id', true);
			$table->string('name')->default('');
			$table->integer('projectid')->default(0)->index('projectid');
			$table->dateTime('starttime')->default('1980-01-01 00:00:00')->index('starttime');
			$table->dateTime('endtime')->default('1980-01-01 00:00:00')->index('endtime');
			$table->integer('autoremovetimeframe')->nullable()->default(0);
			$table->text('description', 65535);
			$table->boolean('summaryemail')->nullable()->default(0);
			$table->boolean('includesubprojectotal')->nullable()->default(1);
			$table->boolean('emailcommitters')->nullable()->default(0);
			$table->string('type', 20)->default('Daily')->index('type');
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('buildgroup');
	}

}
