<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateUserstatisticsTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('userstatistics', function(Blueprint $table)
		{
			$table->integer('userid')->index('userid');
			$table->smallInteger('projectid')->index('projectid');
			$table->timestamp('checkindate')->default(DB::raw('CURRENT_TIMESTAMP'))->index('checkindate');
			$table->bigInteger('totalupdatedfiles');
			$table->bigInteger('totalbuilds');
			$table->bigInteger('nfixedwarnings');
			$table->bigInteger('nfailedwarnings');
			$table->bigInteger('nfixederrors');
			$table->bigInteger('nfailederrors');
			$table->bigInteger('nfixedtests');
			$table->bigInteger('nfailedtests');
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('userstatistics');
	}

}
