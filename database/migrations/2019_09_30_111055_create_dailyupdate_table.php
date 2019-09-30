<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateDailyupdateTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('dailyupdate', function(Blueprint $table)
		{
			$table->bigInteger('id', true);
			$table->integer('projectid')->index('projectid');
			$table->date('date')->index('date');
			$table->text('command', 65535);
			$table->string('type', 4)->default('');
			$table->boolean('status')->default(0);
			$table->string('revision', 60)->default('0');
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('dailyupdate');
	}

}
