<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
    public function up(): void
    {
        DB::statement('ALTER TABLE project DROP COLUMN showipaddresses');
    }

    public function down(): void
    {
    }
};
