<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateUpdatefileTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('updatefile', function(Blueprint $table)
		{
			$table->integer('updateid')->default(0)->index();
			$table->string('filename')->default('');
			$table->dateTime('checkindate')->default('1980-01-01 00:00:00');
			$table->string('author')->default('')->index();
			$table->string('email')->default('');
			$table->string('committer')->default('');
			$table->string('committeremail')->default('');
			$table->text('log', 65535);
			$table->string('revision', 60)->default('0');
			$table->string('priorrevision', 60)->default('0');
			$table->string('status', 12)->default('');
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('updatefile');
	}

}
