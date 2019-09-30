<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateProjectTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('project', function(Blueprint $table)
		{
			$table->integer('id', true);
			$table->string('name')->default('')->index('name');
			$table->text('description', 65535);
			$table->string('homeurl')->default('');
			$table->string('cvsurl')->default('');
			$table->string('bugtrackerurl')->default('');
			$table->string('bugtrackerfileurl')->default('');
			$table->string('bugtrackernewissueurl')->default('');
			$table->string('bugtrackertype', 16)->nullable();
			$table->string('documentationurl')->default('');
			$table->integer('imageid')->default(0);
			$table->boolean('public')->default(1)->index('public');
			$table->smallInteger('coveragethreshold')->default(70);
			$table->string('testingdataurl')->default('');
			$table->string('nightlytime', 50)->default('00:00:00');
			$table->string('googletracker', 50)->default('');
			$table->boolean('emaillowcoverage')->default(0);
			$table->boolean('emailtesttimingchanged')->default(0);
			$table->boolean('emailbrokensubmission')->default(1);
			$table->boolean('emailredundantfailures')->default(0);
			$table->boolean('emailadministrator')->default(1);
			$table->boolean('showipaddresses')->default(1);
			$table->string('cvsviewertype', 10)->nullable();
			$table->float('testtimestd', 3, 1)->nullable()->default(4.0);
			$table->float('testtimestdthreshold', 3, 1)->nullable()->default(1.0);
			$table->boolean('showtesttime')->nullable()->default(0);
			$table->boolean('testtimemaxstatus')->nullable()->default(3);
			$table->boolean('emailmaxitems')->nullable()->default(5);
			$table->integer('emailmaxchars')->nullable()->default(255);
			$table->boolean('displaylabels')->nullable()->default(1);
			$table->integer('autoremovetimeframe')->nullable()->default(0);
			$table->integer('autoremovemaxbuilds')->nullable()->default(300);
			$table->bigInteger('uploadquota')->nullable()->default(0);
			$table->string('webapikey', 40)->nullable();
			$table->integer('tokenduration')->nullable();
			$table->boolean('showcoveragecode')->nullable()->default(1);
			$table->boolean('sharelabelfilters')->nullable()->default(0);
			$table->boolean('authenticatesubmissions')->nullable()->default(0);
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('project');
	}

}
