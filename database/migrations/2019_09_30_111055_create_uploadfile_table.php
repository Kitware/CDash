<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateUploadfileTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('uploadfile', function(Blueprint $table)
		{
			$table->integer('id', true);
			$table->string('filename');
			$table->integer('filesize')->default(0);
			$table->string('sha1sum', 40)->index();
			$table->tinyInteger('isurl')->default(0);
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('uploadfile');
	}

}
