<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreatePasswordTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('password')) {
            Schema::create('password', function (Blueprint $table) {
                $table->integer('userid')->index();
                $table->string('password')->default('');
                $table->timestamp('date')->default(DB::raw('CURRENT_TIMESTAMP'));
                $table->timestamps();
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
        Schema::drop('password');
    }
}
