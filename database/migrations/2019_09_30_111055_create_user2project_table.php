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
			$table->string('cvslogin', 50)->default('')->index('cvslogin');
			$table->boolean('emailtype')->default(0)->index('emailtype');
			$table->boolean('emailcategory')->default(62);
			$table->boolean('emailsuccess')->default(0)->index('emailsucess');
			$table->boolean('emailmissingsites')->default(0)->index('emailmissingsites');
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
