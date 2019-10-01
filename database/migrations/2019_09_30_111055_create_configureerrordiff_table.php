<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateConfigureerrordiffTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('configureerrordiff', function(Blueprint $table)
		{
			$table->bigInteger('buildid')->index();
			$table->tinyInteger('type')->nullable()->index();
			$table->integer('difference')->nullable();
			$table->unique(['buildid','type'], 'unique_configureerrordiff');
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('configureerrordiff');
	}

}
