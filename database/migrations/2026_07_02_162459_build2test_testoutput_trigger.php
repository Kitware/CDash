<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // When build2test rows are deleted, remove any testoutput rows that are
        // no longer referenced by any remaining build2test row.  A statement-level
        // trigger with a transition table is used so that bulk deletes are handled
        // efficiently in a single pass.
        DB::unprepared(<<<'SQL'
                CREATE OR REPLACE FUNCTION delete_unreferenced_testoutput() RETURNS trigger AS $$
                BEGIN
                    DELETE FROM testoutput
                    WHERE id IN (SELECT DISTINCT outputid FROM deleted_build2test)
                    AND NOT EXISTS (
                        SELECT 1 FROM build2test WHERE build2test.outputid = testoutput.id
                    );
                    RETURN NULL;
                END;
                $$ LANGUAGE plpgsql;
            SQL);

        DB::unprepared(<<<'SQL'
                CREATE OR REPLACE TRIGGER build2test_delete_testoutput
                AFTER DELETE ON build2test
                REFERENCING OLD TABLE AS deleted_build2test
                FOR EACH STATEMENT
                EXECUTE FUNCTION delete_unreferenced_testoutput();
            SQL);
    }

    public function down(): void
    {
    }
};
