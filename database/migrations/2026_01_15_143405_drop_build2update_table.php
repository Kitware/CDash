<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
    public function up(): void
    {
        DB::statement('ALTER TABLE build ADD COLUMN updateid bigint');
        DB::statement('ALTER TABLE build ADD FOREIGN KEY (updateid) REFERENCES buildupdate(id) ON DELETE SET NULL');

        DB::update('
            UPDATE build
            SET updateid = build2update.updateid
            FROM build2update
            WHERE build.id = build2update.buildid
        ');

        DB::statement('CREATE INDEX ON build(updateid)');

        DB::statement('DROP TABLE build2update');
    }

    public function down(): void
    {
    }
};
