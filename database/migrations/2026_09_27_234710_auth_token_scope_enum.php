<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // We drop the type first in case the database has been truncated previously.
        DB::statement('DROP TYPE IF EXISTS authtokenscope');
        DB::statement("CREATE TYPE authtokenscope AS ENUM ('FULL_ACCESS', 'SUBMIT_ONLY')");

        // Tokens with an unrecognized scope are already unusable, so we delete them rather than
        // risk granting them more access than they had before.
        DB::statement("DELETE FROM authtoken WHERE scope NOT IN ('full_access', 'submit_only')");

        DB::statement('ALTER TABLE authtoken ALTER COLUMN scope DROP DEFAULT');

        DB::statement("
            ALTER TABLE authtoken
            ALTER COLUMN scope TYPE authtokenscope
            USING CASE scope
                WHEN 'full_access' THEN 'FULL_ACCESS'::authtokenscope
                WHEN 'submit_only' THEN 'SUBMIT_ONLY'::authtokenscope
            END
        ");

        DB::statement("ALTER TABLE authtoken ALTER COLUMN scope SET DEFAULT 'FULL_ACCESS'::authtokenscope");
    }

    public function down(): void
    {
    }
};
