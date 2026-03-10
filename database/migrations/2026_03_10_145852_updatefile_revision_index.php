<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::statement('CREATE INDEX ON updatefile(revision)');
        DB::statement('CREATE INDEX ON updatefile(priorrevision)');
        DB::statement('CREATE INDEX ON updatefile(filename, revision)');
    }

    public function down(): void
    {
    }
};
