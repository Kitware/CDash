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
        Schema::table('builderror', function (Blueprint $table) {
            $table->index(['buildid', 'type', 'crc32']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('builderror', function (Blueprint $table) {
            $table->dropIndex(['buildid', 'type', 'crc32']);
        });
    }
};
