<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateLabel2updateTable extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('label2update')) {
            Schema::create('label2update', function (Blueprint $table) {
                $table->bigInteger('labelid');
                $table->bigInteger('updateid');
                $table->primary(['labelid','updateid']);
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
        Schema::drop('label2update');
    }
}
