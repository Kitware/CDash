<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::statement("ALTER TABLE comments DROP CONSTRAINT {$this->getConstraintName('buildid')}");
        DB::statement("ALTER TABLE comments DROP CONSTRAINT {$this->getConstraintName('userid')}");

        // Re-add constraints with ON DELETE CASCADE.
        DB::statement('ALTER TABLE comments ADD FOREIGN KEY (buildid) REFERENCES build (id) ON DELETE CASCADE');
        DB::statement('ALTER TABLE comments ADD FOREIGN KEY (userid) REFERENCES users (id) ON DELETE CASCADE');
    }

    private function getConstraintName(string $columnName): string
    {
        // Get foreign key constraint name.  The constraint name isn't guaranteed to be the same on
        // all systems, due to MySQL->Postgres migration differences.
        return DB::select("
            SELECT tc.constraint_name as constraint_name
            FROM information_schema.table_constraints AS tc
            JOIN information_schema.key_column_usage AS kcu
              ON tc.constraint_name = kcu.constraint_name
              AND tc.table_schema = kcu.table_schema
            WHERE tc.constraint_type = 'FOREIGN KEY'
              AND tc.table_name = 'comments'
              AND kcu.column_name = '{$columnName}'
            LIMIT 1;
        ")[0]->constraint_name;
    }

    public function down(): void
    {
    }
};
