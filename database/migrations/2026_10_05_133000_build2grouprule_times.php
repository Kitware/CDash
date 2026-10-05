<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::statement('ALTER TABLE build2grouprule ALTER COLUMN starttime DROP DEFAULT');

        DB::statement('ALTER TABLE build2grouprule ALTER COLUMN endtime DROP DEFAULT');
        DB::statement('ALTER TABLE build2grouprule ALTER COLUMN endtime DROP NOT NULL');
        DB::update("UPDATE build2grouprule SET endtime = NULL WHERE endtime = '1980-01-01 00:00:00'");
    }

    public function down(): void
    {
    }
};
