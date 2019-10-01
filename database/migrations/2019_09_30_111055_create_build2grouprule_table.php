<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateBuild2groupruleTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('build2grouprule', function(Blueprint $table)
		{
			$table->integer('groupid')->default(0);
			$table->integer('parentgroupid')->default(0);
			$table->string('buildtype', 20)->default('');
			$table->string('buildname')->default('');
			$table->integer('siteid')->default(0);
			$table->tinyInteger('expected')->default(0);
			$table->dateTime('starttime')->default('1980-01-01 00:00:00');
			$table->dateTime('endtime')->default('1980-01-01 00:00:00');

			$table->index('groupid');
			$table->index('parentgroupid');
			$table->index('buildtype');
			$table->index('buildname');
			$table->index('siteid');
			$table->index('expected');
			$table->index('starttime');
			$table->index('endtime');
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('build2grouprule');
	}

}
