<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

class IncreaseSiteInformationCPUColumnsSize extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('siteinformation', function ($table) {
            $table->unsignedSmallInteger('numberlogicalcpus')->default(0)->change();
            $table->unsignedSmallInteger('numberphysicalcpus')->default(0)->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        if (config('database.default') === 'pgsql') {
            Schema::table('siteinformation', function ($table) {
                $table->smallInteger('numberlogicalcpus')->default(-1)->change();
                $table->smallInteger('numberphysicalcpus')->default(-1)->change();
            });
        } else {
            Schema::table('siteinformation', function ($table) {
                $table->boolean('numberlogicalcpus')->default(-1)->change();
                $table->boolean('numberphysicalcpus')->default(-1)->change();
            });
        }
    }
}
