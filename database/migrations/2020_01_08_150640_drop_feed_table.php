<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class DropFeedTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::dropIfExists('feed');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        if (!Schema::hasTable('feed')) {
            Schema::create('feed', function (Blueprint $table) {
                $table->bigInteger('id', true);
                $table->integer('projectid')->index();
                $table->timestamp('date')->default(DB::raw('CURRENT_TIMESTAMP'))->index();
                $table->bigInteger('buildid');
                $table->integer('type');
                $table->string('description');
            });
        }
    }
}
