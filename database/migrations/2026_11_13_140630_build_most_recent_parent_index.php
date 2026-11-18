<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::statement('CREATE INDEX ON build(projectid, submittime, id) WHERE parentid IS NULL');
    }

    public function down(): void
    {
    }
};
