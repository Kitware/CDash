<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::statement('ALTER TABLE updatefile ADD COLUMN id bigint PRIMARY KEY GENERATED ALWAYS AS IDENTITY');
        DB::statement('ALTER TABLE updatefile ALTER COLUMN log DROP NOT NULL');
    }

    public function down(): void
    {
    }
};
