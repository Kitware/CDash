<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::statement('ALTER TABLE buildcommands ALTER COLUMN starttime TYPE timestamptz(3)');
    }

    public function down(): void
    {
    }
};
