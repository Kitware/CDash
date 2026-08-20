<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::statement('ALTER TABLE build2test ALTER COLUMN outputid TYPE bigint');
        DB::statement('ALTER TABLE testoutput ALTER COLUMN id TYPE bigint');
        DB::statement('ALTER SEQUENCE test_id_seq AS bigint');
    }

    public function down(): void
    {
    }
};
