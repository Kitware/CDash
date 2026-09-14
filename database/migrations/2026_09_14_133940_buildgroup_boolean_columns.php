<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::statement('ALTER TABLE buildgroup ALTER COLUMN includesubprojectotal DROP DEFAULT');
        DB::statement('ALTER TABLE buildgroup ALTER COLUMN includesubprojectotal TYPE boolean USING includesubprojectotal::text::boolean');
        DB::statement('ALTER TABLE buildgroup ALTER COLUMN includesubprojectotal SET DEFAULT TRUE');
        DB::statement('ALTER TABLE buildgroup ALTER COLUMN includesubprojectotal SET NOT NULL');

        DB::statement('ALTER TABLE buildgroup ALTER COLUMN emailcommitters DROP DEFAULT');
        DB::statement('ALTER TABLE buildgroup ALTER COLUMN emailcommitters TYPE boolean USING emailcommitters::text::boolean');
        DB::statement('ALTER TABLE buildgroup ALTER COLUMN emailcommitters SET DEFAULT FALSE');
        DB::statement('ALTER TABLE buildgroup ALTER COLUMN emailcommitters SET NOT NULL');
    }

    public function down(): void
    {
    }
};
