<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // Disable triggers temporarily
        DB::statement('ALTER TABLE build2test DISABLE TRIGGER ALL');
        DB::statement('ALTER TABLE testoutput DISABLE TRIGGER ALL');

        // Dynamically find and drop all indexes on testoutput EXCEPT the primary key
        DB::statement("
            DO $$
            DECLARE
                idx_name text;
            BEGIN
                FOR idx_name IN (
                    SELECT i.relname
                    FROM pg_index x
                    JOIN pg_class c ON c.oid = x.indrelid
                    JOIN pg_class i ON i.oid = x.indexrelid
                    WHERE c.relname = 'testoutput'
                      AND x.indisprimary = false
                ) LOOP
                    EXECUTE 'DROP INDEX IF EXISTS ' || idx_name;
                END LOOP;
            END $$;
        ");

        // Update build2test.outputid to bigint
        DB::statement('ALTER TABLE build2test ALTER COLUMN outputid TYPE bigint');

        // Dynamically find the sequence associated with testoutput.id and alter it to bigint
        DB::statement("
            DO $$
            DECLARE
                seq_name text;
            BEGIN
                SELECT pg_get_serial_sequence('testoutput', 'id') INTO seq_name;
                IF seq_name IS NOT NULL THEN
                    EXECUTE 'ALTER SEQUENCE ' || seq_name || ' AS bigint';
                END IF;
            END $$;
        ");

        // Update testoutput.id to bigint
        DB::statement('ALTER TABLE testoutput ALTER COLUMN id TYPE bigint');

        // Re-add only the output hash index
        DB::statement('CREATE INDEX ON testoutput USING hash (output)');

        // Re-enable triggers
        DB::statement('ALTER TABLE build2test ENABLE TRIGGER ALL');
        DB::statement('ALTER TABLE testoutput ENABLE TRIGGER ALL');
    }

    public function down(): void
    {
    }
};
