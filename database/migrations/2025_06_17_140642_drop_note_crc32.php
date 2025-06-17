<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::statement('ALTER TABLE note DROP COLUMN IF EXISTS crc32');
        DB::statement('CREATE INDEX ON note USING HASH (text)');
    }

    public function down(): void
    {
    }
};
