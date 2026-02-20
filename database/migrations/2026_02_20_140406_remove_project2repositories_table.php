<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::statement('ALTER TABLE repositories ADD COLUMN projectid bigint');
        DB::statement('ALTER TABLE repositories ADD FOREIGN KEY (projectid) REFERENCES project(id) ON DELETE CASCADE');

        DB::insert('
            INSERT INTO repositories (projectid, url, username, password, branch)
            SELECT project.id, repositories.url, repositories.username, repositories.password, repositories.branch
            FROM repositories
            INNER JOIN project2repositories ON repositories.id = project2repositories.repositoryid
            INNER JOIN project ON project2repositories.projectid = project.id
        ');

        DB::delete('DELETE FROM repositories WHERE projectid IS NULL');
        DB::statement('ALTER TABLE repositories ALTER COLUMN projectid SET NOT NULL');

        DB::statement('DROP TABLE project2repositories');
    }

    public function down(): void
    {
    }
};
