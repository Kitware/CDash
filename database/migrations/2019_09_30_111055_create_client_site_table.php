<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateClientSiteTable extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('client_site')) {
            Schema::create('client_site', function (Blueprint $table) {
                $table->integer('id', true);
                $table->string('name')->nullable()->index();
                $table->integer('osid')->nullable()->index();
                $table->string('systemname')->nullable();
                $table->string('host')->nullable();
                $table->string('basedirectory', 512);
                $table->dateTime('lastping')->default('1980-01-01 00:00:00')->index();
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
        Schema::drop('client_site');
    }
}
