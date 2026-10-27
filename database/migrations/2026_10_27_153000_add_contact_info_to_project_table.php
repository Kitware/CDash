<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::statement('ALTER TABLE project ADD COLUMN contact_info character varying(100)');
    }

    public function down(): void
    {
    }
};
