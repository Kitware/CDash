<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('filesum')) {
            Schema::drop('filesum');
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        if (!Schema::hasTable('filesum')) {
            Schema::create('filesum', function (Blueprint $table) {
                $table->integer('id', true);
                $table->string('md5sum', 32)->index();
                $table->binary('contents')->nullable();
            });
        }
    }
};
