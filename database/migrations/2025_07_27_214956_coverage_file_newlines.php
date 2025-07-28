<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::update("UPDATE coveragefile SET file = replace(file, '<br>', E'\n')");
    }

    public function down(): void
    {
    }
};
