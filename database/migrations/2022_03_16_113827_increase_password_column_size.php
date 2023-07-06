<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

class IncreasePasswordColumnSize extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('password', function ($table) {
            $table->string('password', 255)->change();
        });
        Schema::table('user', function ($table) {
            $table->string('password', 255)->change();
        });
        Schema::table('usertemp', function ($table) {
            $table->string('password', 255)->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('password', function ($table) {
            $table->string('password')->change();
        });
        Schema::table('user', function ($table) {
            $table->string('password')->change();
        });
        Schema::table('usertemp', function ($table) {
            $table->string('password')->change();
        });
    }
}
