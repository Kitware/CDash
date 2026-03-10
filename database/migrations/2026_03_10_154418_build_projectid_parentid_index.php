<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::statement('CREATE INDEX ON build(projectid, id) WHERE parentid = 0 OR parentid = 1');
    }

    public function down(): void
    {
    }
};
