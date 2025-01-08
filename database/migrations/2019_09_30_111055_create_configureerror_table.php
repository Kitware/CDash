<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateConfigureerrorTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('configureerror')) {
            Schema::create('configureerror', function (Blueprint $table) {
                $table->integer('configureid')->index();
                $table->tinyInteger('type')->nullable()->index();
                $table->text('text', 65535)->nullable();
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
        Schema::drop('configureerror');
    }
}
