<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateSiteinformationTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('siteinformation', function(Blueprint $table)
		{
			$table->integer('siteid');
			$table->dateTime('timestamp')->default('1980-01-01 00:00:00');
			$table->tinyInteger('processoris64bits')->default(-1);
			$table->string('processorvendor')->default('NA');
			$table->string('processorvendorid')->default('NA');
			$table->integer('processorfamilyid')->default(-1);
			$table->integer('processormodelid')->default(-1);
			$table->integer('processorcachesize')->default(-1);
			$table->tinyInteger('numberlogicalcpus')->default(-1);
			$table->tinyInteger('numberphysicalcpus')->default(-1);
			$table->integer('totalvirtualmemory')->default(-1);
			$table->integer('totalphysicalmemory')->default(-1);
			$table->integer('logicalprocessorsperphysical')->default(-1);
			$table->integer('processorclockfrequency')->default(-1);
			$table->string('description')->default('NA');
			$table->index(['siteid','timestamp'], 'siteid');
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('siteinformation');
	}

}
