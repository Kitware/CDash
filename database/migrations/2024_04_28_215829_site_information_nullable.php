<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Make all of the user-controlled fields nullable
        Schema::table('siteinformation', function (Blueprint $table) {
            $table->dateTime('timestamp')->default('CURRENT_TIMESTAMP')->change();
            $table->smallInteger('processoris64bits')->nullable()->default(null)->change();
            $table->string('processorvendor', 255)->nullable()->default(null)->change();
            $table->string('processorvendorid', 255)->nullable()->default(null)->change();
            $table->integer('processorfamilyid')->nullable()->default(null)->change();
            $table->integer('processormodelid')->nullable()->default(null)->change();
            $table->integer('processorcachesize')->nullable()->default(null)->change();
            $table->smallInteger('numberlogicalcpus')->nullable()->default(null)->change();
            $table->smallInteger('numberphysicalcpus')->nullable()->default(null)->change();
            $table->integer('totalvirtualmemory')->nullable()->default(null)->change();
            $table->integer('totalphysicalmemory')->nullable()->default(null)->change();
            $table->integer('logicalprocessorsperphysical')->nullable()->default(null)->change();
            $table->integer('processorclockfrequency')->nullable()->default(null)->change();
            $table->string('description', 255)->nullable()->default(null)->change();
        });

        // Set any field with the default value to null.
        DB::update("UPDATE siteinformation SET processoris64bits = NULL WHERE processoris64bits = -1");
        DB::update("UPDATE siteinformation SET processorvendor = NULL WHERE processorvendor = 'NA'");
        DB::update("UPDATE siteinformation SET processorvendorid = NULL WHERE processorvendorid = 'NA'");
        DB::update("UPDATE siteinformation SET processorfamilyid = NULL WHERE processorfamilyid = -1");
        DB::update("UPDATE siteinformation SET processormodelid = NULL WHERE processormodelid = -1");
        DB::update("UPDATE siteinformation SET processorcachesize = NULL WHERE processorcachesize = -1");
        DB::update("UPDATE siteinformation SET numberlogicalcpus = NULL WHERE numberlogicalcpus = 0");
        DB::update("UPDATE siteinformation SET numberphysicalcpus = NULL WHERE numberphysicalcpus = 0");
        DB::update("UPDATE siteinformation SET totalvirtualmemory = NULL WHERE totalvirtualmemory = -1");
        DB::update("UPDATE siteinformation SET totalphysicalmemory = NULL WHERE totalphysicalmemory = -1");
        DB::update("UPDATE siteinformation SET logicalprocessorsperphysical = NULL WHERE logicalprocessorsperphysical = -1");
        DB::update("UPDATE siteinformation SET processorclockfrequency = NULL WHERE processorclockfrequency = -1");
        DB::update("UPDATE siteinformation SET description = NULL WHERE description = 'NA'");

        // Change these to integer columns for consistency with other columns
        Schema::table('siteinformation', function (Blueprint $table) {
            $table->integer('numberlogicalcpus')->nullable()->change();
            $table->integer('numberphysicalcpus')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Set values back to default where null
        DB::update("UPDATE siteinformation SET processoris64bits = -1 WHERE processoris64bits IS NULL");
        DB::update("UPDATE siteinformation SET processorvendor = 'NA' WHERE processorvendor IS NULL");
        DB::update("UPDATE siteinformation SET processorvendorid = 'NA' WHERE processorvendorid IS NULL");
        DB::update("UPDATE siteinformation SET processorfamilyid = -1 WHERE processorfamilyid IS NULL");
        DB::update("UPDATE siteinformation SET processormodelid = -1 WHERE processormodelid IS NULL");
        DB::update("UPDATE siteinformation SET processorcachesize = -1 WHERE processorcachesize IS NULL");
        DB::update("UPDATE siteinformation SET numberlogicalcpus = 0 WHERE numberlogicalcpus IS NULL");
        DB::update("UPDATE siteinformation SET numberphysicalcpus = 0 WHERE numberphysicalcpus IS NULL");
        DB::update("UPDATE siteinformation SET totalvirtualmemory = -1 WHERE totalvirtualmemory IS NULL");
        DB::update("UPDATE siteinformation SET totalphysicalmemory = -1 WHERE totalphysicalmemory IS NULL");
        DB::update("UPDATE siteinformation SET logicalprocessorsperphysical = -1 WHERE logicalprocessorsperphysical IS NULL");
        DB::update("UPDATE siteinformation SET processorclockfrequency = -1 WHERE processorclockfrequency IS NULL");
        DB::update("UPDATE siteinformation SET description = 'NA' WHERE description IS NULL");

        // Change the column constraints back to the original constraints
        Schema::table('siteinformation', function (Blueprint $table) {
            $table->dateTime('timestamp')->default('1980-01-01 00:00:00')->change();
            $table->smallInteger('processoris64bits')->nullable(false)->default(-1)->change();
            $table->string('processorvendor', 255)->nullable(false)->default('NA')->change();
            $table->string('processorvendorid', 255)->nullable(false)->default('NA')->change();
            $table->integer('processorfamilyid')->nullable(false)->default(-1)->change();
            $table->integer('processormodelid')->nullable(false)->default(-1)->change();
            $table->integer('processorcachesize')->nullable(false)->default(-1)->change();
            $table->smallInteger('numberlogicalcpus')->nullable(false)->default(0)->change();
            $table->smallInteger('numberphysicalcpus')->nullable(false)->default(0)->change();
            $table->integer('totalvirtualmemory')->nullable(false)->default(-1)->change();
            $table->integer('totalphysicalmemory')->nullable(false)->default(-1)->change();
            $table->integer('logicalprocessorsperphysical')->nullable(false)->default(-1)->change();
            $table->integer('processorclockfrequency')->nullable(false)->default(-1)->change();
            $table->string('description', 255)->nullable(false)->default('NA')->change();
        });
    }
};
