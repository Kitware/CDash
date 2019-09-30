<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateFeedTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('feed', function(Blueprint $table)
		{
			$table->bigInteger('id', true);
			$table->integer('projectid')->index('projectid');
			$table->timestamp('date')->default(DB::raw('CURRENT_TIMESTAMP'))->index('date');
			$table->bigInteger('buildid');
			$table->integer('type');
			$table->string('description');
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('feed');
	}

}
