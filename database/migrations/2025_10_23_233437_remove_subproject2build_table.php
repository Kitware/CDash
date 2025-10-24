<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::statement('ALTER TABLE build ADD COLUMN subprojectid bigint REFERENCES subproject(id) ON DELETE SET NULL');

        DB::update('
            UPDATE build
            SET subprojectid = subproject2build.subprojectid
            FROM subproject2build, subproject
            WHERE
                subproject.id = subproject2build.subprojectid
                AND subproject2build.buildid = build.id
        ');

        DB::statement('DROP TABLE subproject2build');

        DB::statement('CREATE INDEX ON build (subprojectid)');
    }

    public function down(): void
    {
    }
};
