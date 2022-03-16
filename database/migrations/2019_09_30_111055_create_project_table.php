<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateProjectTable extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('project')) {
            Schema::create('project', function (Blueprint $table) {
                $table->integer('id', true);
                $table->string('name')->default('')->index();
                $table->text('description', 65535)->default('');
                $table->string('homeurl')->default('');
                $table->string('cvsurl')->default('');
                $table->string('bugtrackerurl')->default('');
                $table->string('bugtrackerfileurl')->default('');
                $table->string('bugtrackernewissueurl')->default('');
                $table->string('bugtrackertype', 16)->nullable();
                $table->string('documentationurl')->default('');
                $table->integer('imageid')->default(0);
                $table->tinyInteger('public')->default(1)->index();
                $table->smallInteger('coveragethreshold')->default(70);
                $table->string('testingdataurl')->default('');
                $table->string('nightlytime', 50)->default('00:00:00');
                $table->string('googletracker', 50)->default('');
                $table->tinyInteger('emaillowcoverage')->default(0);
                $table->tinyInteger('emailtesttimingchanged')->default(0);
                $table->tinyInteger('emailbrokensubmission')->default(1);
                $table->tinyInteger('emailredundantfailures')->default(0);
                $table->tinyInteger('emailadministrator')->default(1);
                $table->tinyInteger('showipaddresses')->default(1);
                $table->string('cvsviewertype', 10)->nullable();
                $table->decimal('testtimestd', 3, 1)->nullable()->default(4.0);
                $table->decimal('testtimestdthreshold', 3, 1)->nullable()->default(1.0);
                $table->tinyInteger('showtesttime')->nullable()->default(0);
                $table->tinyInteger('testtimemaxstatus')->nullable()->default(3);
                $table->tinyInteger('emailmaxitems')->nullable()->default(5);
                $table->integer('emailmaxchars')->nullable()->default(255);
                $table->tinyInteger('displaylabels')->nullable()->default(1);
                $table->integer('autoremovetimeframe')->nullable()->default(0);
                $table->integer('autoremovemaxbuilds')->nullable()->default(300);
                $table->bigInteger('uploadquota')->nullable()->default(0);
                $table->string('webapikey', 40)->nullable()->default('');
                $table->integer('tokenduration')->nullable()->default(0);
                $table->tinyInteger('showcoveragecode')->nullable()->default(1);
                $table->tinyInteger('sharelabelfilters')->nullable()->default(0);
                $table->tinyInteger('authenticatesubmissions')->nullable()->default(0);
            });
        }
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
