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
			$table->integer('groupid')->default(0)->index('groupid');
			$table->integer('parentgroupid')->default(0)->index('parentgroupid');
			$table->string('buildtype', 20)->default('')->index('buildtype');
			$table->string('buildname')->default('')->index('buildname');
			$table->integer('siteid')->default(0)->index('siteid');
			$table->boolean('expected')->default(0)->index('expected');
			$table->dateTime('starttime')->default('1980-01-01 00:00:00')->index('starttime');
			$table->dateTime('endtime')->default('1980-01-01 00:00:00')->index('endtime');
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
