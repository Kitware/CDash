<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::statement('ALTER TABLE test2image ALTER COLUMN outputid DROP NOT NULL');

        DB::statement('ALTER TABLE test2image ADD COLUMN testid bigint');
        DB::statement('ALTER TABLE test2image ADD FOREIGN KEY (testid) REFERENCES build2test(id) ON DELETE CASCADE');

        DB::insert('
            INSERT INTO test2image (testid, imgid, role) (
                SELECT
                    build2test.id AS testid,
                    test2image.imgid,
                    test2image.role
                FROM test2image
                INNER JOIN build2test ON (test2image.outputid = build2test.outputid)
            )
        ');

        // Delete the old rows
        DB::delete('DELETE FROM test2image WHERE testid IS NULL');
        DB::statement('ALTER TABLE test2image ALTER COLUMN testid SET NOT NULL');

        DB::statement('CREATE INDEX ON test2image (testid)');

        DB::statement('ALTER TABLE test2image DROP COLUMN outputid');
    }

    public function down(): void
    {
    }
};
