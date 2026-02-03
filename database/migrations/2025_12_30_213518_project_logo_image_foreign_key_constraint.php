<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::statement('ALTER TABLE project ALTER COLUMN imageid DROP NOT NULL');
        DB::statement('ALTER TABLE project ALTER COLUMN imageid DROP DEFAULT');
        DB::update('
            UPDATE project
            SET imageid=NULL
            WHERE imageid=0 OR NOT EXISTS (
                SELECT * FROM image WHERE image.id=project.imageid
            )');
        DB::statement('ALTER TABLE project ADD FOREIGN KEY (imageid) REFERENCES image(id) ON DELETE SET NULL');
    }

    public function down(): void
    {
    }
};
