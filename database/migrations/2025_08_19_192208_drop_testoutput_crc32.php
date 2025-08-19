<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::statement('ALTER TABLE testoutput DROP COLUMN IF EXISTS crc32');
        DB::statement('CREATE INDEX ON testoutput USING HASH (output)');
        DB::statement('CREATE INDEX ON testoutput USING HASH (command)');
        DB::statement('CREATE INDEX ON testoutput USING HASH (path)');
    }

    public function down(): void
    {
    }
};
