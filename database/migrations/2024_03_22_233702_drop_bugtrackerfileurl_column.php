<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('project')) {
            Schema::dropColumns('project', 'bugtrackerfileurl');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // This migration is irreversible
    }
};
