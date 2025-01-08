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
        Schema::table('label2test', function (Blueprint $table) {
            $table->unique(['outputid', 'buildid', 'labelid']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('label2test', function (Blueprint $table) {
            $table->dropUnique(['outputid', 'buildid', 'labelid']);
        });
    }
};
