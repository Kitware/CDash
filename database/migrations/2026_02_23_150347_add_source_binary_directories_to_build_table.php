<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE build ADD COLUMN sourcedirectory text');
        DB::statement('ALTER TABLE build ADD COLUMN binarydirectory text');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
    }
};
