<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::statement('ALTER TABLE project ALTER COLUMN description DROP DEFAULT');
        DB::statement('ALTER TABLE project ALTER COLUMN description DROP NOT NULL');
        DB::update("UPDATE project SET description = NULL WHERE description = ''");
    }

    public function down(): void
    {
    }
};
