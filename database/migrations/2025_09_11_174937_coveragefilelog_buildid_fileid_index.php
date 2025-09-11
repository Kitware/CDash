<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::statement('CREATE INDEX ON coveragefilelog (buildid, fileid)');
    }

    public function down(): void
    {
    }
};
