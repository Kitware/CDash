<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // We drop the type first in case the database has been truncated previously.
        DB::statement('DROP TYPE IF EXISTS projectrole');
        DB::statement("CREATE TYPE projectrole AS ENUM ('USER', 'ADMINISTRATOR')");

        DB::statement('ALTER TABLE user2project ALTER COLUMN role DROP DEFAULT');

        DB::statement("
            ALTER TABLE user2project
            ALTER COLUMN role TYPE projectrole
            USING CASE role
                WHEN 0 THEN 'USER'::projectrole
                WHEN 2 THEN 'ADMINISTRATOR'::projectrole
                ELSE 'USER'::projectrole
            END
        ");

        DB::statement("ALTER TABLE user2project ALTER COLUMN role SET DEFAULT 'USER'::projectrole");

        DB::statement("
            ALTER TABLE project_invitations
            ALTER COLUMN role TYPE projectrole
            USING CASE role
                WHEN 'USER' THEN 'USER'::projectrole
                WHEN 'ADMINISTRATOR' THEN 'ADMINISTRATOR'::projectrole
                ELSE 'USER'::projectrole
            END
        ");
    }

    public function down(): void
    {
    }
};
