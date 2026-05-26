<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::statement('
            CREATE TABLE coveragediff (
                id bigint PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
                basebuildid bigint REFERENCES build(id) ON DELETE CASCADE NOT NULL,
                comparebuildid bigint REFERENCES build(id) ON DELETE CASCADE NOT NULL,
                coveredlinesadded bigint NOT NULL,
                coveredlinesremoved bigint NOT NULL,
                coveredlinesuncovered bigint NOT NULL,
                uncoveredlinesadded bigint NOT NULL,
                uncoveredlinesremoved bigint NOT NULL,
                uncoveredlinescovered bigint NOT NULL,
                CONSTRAINT basebuildid_comparebuildid_unique UNIQUE (basebuildid, comparebuildid)
            )
        ');

        DB::statement('CREATE INDEX ON coveragediff (comparebuildid, basebuildid)');
    }

    public function down(): void
    {
    }
};
