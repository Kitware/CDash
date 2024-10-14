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
        Schema::table('buildmeasurements', function (Blueprint $table) {
            $table->unique(['buildid', 'source', 'type', 'name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('buildmeasurements', function (Blueprint $table) {
            $table->dropUnique(['buildid', 'source', 'type', 'name']);
        });
    }
};
