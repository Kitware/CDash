<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::statement('ALTER TABLE project ALTER COLUMN testtimestd TYPE numeric(12,2)');
        DB::statement('ALTER TABLE project ALTER COLUMN testtimestdthreshold TYPE numeric(12,2)');

        DB::statement('ALTER TABLE buildtesttime ALTER COLUMN time TYPE numeric(12,2)');

        DB::statement('ALTER TABLE build2test ALTER COLUMN time TYPE numeric(12,2)');
        DB::statement('ALTER TABLE build2test ALTER COLUMN timemean TYPE numeric(12,2)');
        DB::statement('ALTER TABLE build2test ALTER COLUMN timestd TYPE numeric(12,2)');
    }

    public function down(): void
    {
    }
};
