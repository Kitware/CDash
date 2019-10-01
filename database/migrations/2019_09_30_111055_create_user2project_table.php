<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateUser2projectTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('user2project', function(Blueprint $table)
		{
			$table->integer('userid')->default(0);
			$table->integer('projectid')->default(0);
			$table->integer('role')->default(0);
			$table->string('cvslogin', 50)->default('')->index();
			$table->tinyInteger('emailtype')->default(0)->index();
			$table->tinyInteger('emailcategory')->default(62);
			$table->tinyInteger('emailsuccess')->default(0)->index();
			$table->tinyInteger('emailmissingsites')->default(0)->index();
			$table->primary(['userid','projectid']);
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('user2project');
	}

}
