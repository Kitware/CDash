<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::statement("ALTER TABLE project ADD COLUMN notrun_skipped_details_regex text NOT NULL DEFAULT '*skip*'");
    }

    public function down(): void
    {
    }
};
