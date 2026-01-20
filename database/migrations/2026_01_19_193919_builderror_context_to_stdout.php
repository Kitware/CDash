<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::statement('ALTER TABLE builderror ADD COLUMN stdoutput text');
        DB::update('UPDATE builderror SET stdoutput = CONCAT(precontext, text, postcontext)');
        DB::statement('ALTER TABLE builderror DROP COLUMN precontext');
        DB::statement('ALTER TABLE builderror DROP COLUMN postcontext');
        DB::statement('ALTER TABLE builderror RENAME COLUMN text TO stderror');
        DB::statement('ALTER TABLE builderror ALTER COLUMN stdoutput SET NOT NULL');
    }

    public function down(): void
    {
    }
};
