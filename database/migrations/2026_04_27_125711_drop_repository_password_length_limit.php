<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::statement('ALTER TABLE repositories ALTER COLUMN password TYPE text');
    }

    public function down(): void
    {
    }
};
