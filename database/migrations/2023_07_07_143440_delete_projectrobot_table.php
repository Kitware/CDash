<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::drop('projectrobot');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::create('projectrobot', function (Blueprint $table) {
            $table->integer('projectid')->index();
            $table->string('robotname')->index();
            $table->string('authorregex', 512);
        });
    }
};
