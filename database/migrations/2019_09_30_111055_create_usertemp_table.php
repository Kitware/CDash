<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateUsertempTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('usertemp')) {
            Schema::create('usertemp', function (Blueprint $table) {
                $table->string('email')->default('')->primary();
                $table->string('password')->default('');
                $table->string('firstname', 40)->default('');
                $table->string('lastname', 40)->default('');
                $table->string('institution')->default('');
                $table->dateTime('registrationdate')->index();
                $table->string('registrationkey', 40)->default('');
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
        Schema::drop('usertemp');
    }
}
