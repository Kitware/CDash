<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::update('
            UPDATE user2project
            SET role=0
            WHERE role=1
        ');
    }

    public function down(): void
    {
        // This migration is irreversible
    }
};
