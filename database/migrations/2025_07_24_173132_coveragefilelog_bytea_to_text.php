<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::statement('ALTER TABLE coveragefilelog RENAME COLUMN log TO old_log');
        DB::statement('ALTER TABLE coveragefilelog ADD COLUMN IF NOT EXISTS log text');

        // Convert from binary/bytea to UTF-8 text..
        DB::update("UPDATE coveragefilelog SET log = encode(old_log, 'escape')");

        DB::statement('ALTER TABLE coveragefilelog ALTER COLUMN log SET NOT NULL');
        DB::statement('ALTER TABLE coveragefilelog DROP COLUMN old_log');
    }

    public function down(): void
    {
    }
};
