<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::statement('ALTER TABLE image ALTER COLUMN id TYPE bigint');
        DB::statement('ALTER TABLE project ALTER COLUMN imageid TYPE bigint');
        DB::statement('ALTER TABLE test2image ALTER COLUMN imgid TYPE bigint');
        DB::statement('ALTER SEQUENCE image_id_seq AS bigint');
    }

    public function down(): void
    {
    }
};
