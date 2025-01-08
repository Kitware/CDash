<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateFeedTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
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

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('feed');
    }
}
