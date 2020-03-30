<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddTestOutputTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('testoutput')) {
            // Return early if it looks like this migration has
            // already been performed.
            return;
        }

        // Rename test table to testoutput.
        echo "Renaming 'test' to 'testoutput'" . PHP_EOL;
        Schema::rename('test', 'testoutput');

        // Create a much simpler test table.
        echo "Creating new 'test' table" . PHP_EOL;
        Schema::create('test', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('projectid');
            $table->string('name', 255);
            $table->unique(['name', 'projectid']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('test');
        Schema::rename('testoutput', 'test');
    }
}
