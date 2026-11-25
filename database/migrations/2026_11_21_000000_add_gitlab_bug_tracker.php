<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::statement("ALTER TYPE bugtrackertype ADD VALUE IF NOT EXISTS 'GitLab'");
    }

    public function down(): void
    {
    }
};
