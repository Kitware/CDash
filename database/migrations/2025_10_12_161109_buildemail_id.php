<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::statement('ALTER TABLE buildemail ADD COLUMN id bigint PRIMARY KEY GENERATED ALWAYS AS IDENTITY');
    }

    public function down(): void
    {
    }
};
