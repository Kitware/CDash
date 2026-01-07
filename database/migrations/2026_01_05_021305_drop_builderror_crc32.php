<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
    public function up(): void
    {
        DB::statement('ALTER TABLE builderror DROP COLUMN crc32');
    }

    public function down(): void
    {
    }
};
