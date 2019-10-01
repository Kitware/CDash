<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateBuilderrorTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('builderror', function(Blueprint $table)
		{
			$table->integer('buildid')->default(0)->index();
			$table->tinyInteger('type')->default(0)->index();
			$table->integer('logline')->default(0);
			$table->text('text', 65535);
			$table->string('sourcefile')->default('');
			$table->integer('sourceline')->default(0);
			$table->text('precontext', 65535)->nullable();
			$table->text('postcontext', 65535)->nullable();
			$table->integer('repeatcount')->default(0);
			$table->bigInteger('crc32')->default(0)->index();
			$table->tinyInteger('newstatus')->default(0)->index();
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('builderror');
	}

}
