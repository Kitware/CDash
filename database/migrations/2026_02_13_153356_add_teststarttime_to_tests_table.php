<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE build2test ADD COLUMN starttime timestamp(3) with time zone');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
    }
};
