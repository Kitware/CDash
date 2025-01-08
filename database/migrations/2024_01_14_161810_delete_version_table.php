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
        if (Schema::hasTable('version')) {
            Schema::drop('version');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('version')) {
            Schema::create('version', function (Blueprint $table) {
                $table->integer('major');
                $table->integer('minor');
                $table->integer('patch');
            });
        }
    }
};
