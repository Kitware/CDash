<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::statement('ALTER TABLE project ADD COLUMN cmakeprojectroot text');
    }

    public function down(): void
    {
    }
};
