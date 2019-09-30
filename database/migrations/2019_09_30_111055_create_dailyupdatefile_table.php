<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateDailyupdatefileTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('dailyupdatefile', function(Blueprint $table)
		{
			$table->integer('dailyupdateid')->default(0)->index('dailyupdateid');
			$table->string('filename')->default('');
			$table->timestamp('checkindate')->default(DB::raw('CURRENT_TIMESTAMP'));
			$table->string('author')->default('')->index('author');
			$table->string('email')->default('');
			$table->text('log', 65535);
			$table->string('revision', 60)->default('0');
			$table->string('priorrevision', 60)->default('0');
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('dailyupdatefile');
	}

}
