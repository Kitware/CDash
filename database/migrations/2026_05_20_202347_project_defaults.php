<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::statement('ALTER TABLE project ALTER COLUMN name DROP DEFAULT');
        DB::statement('ALTER TABLE project ALTER COLUMN public DROP DEFAULT');
        DB::statement('ALTER TABLE project ALTER COLUMN coveragethreshold DROP DEFAULT');
        DB::statement('ALTER TABLE project ALTER COLUMN nightlytime DROP DEFAULT');
        DB::statement('ALTER TABLE project ALTER COLUMN emaillowcoverage DROP DEFAULT');
        DB::statement('ALTER TABLE project ALTER COLUMN emailtesttimingchanged DROP DEFAULT');
        DB::statement('ALTER TABLE project ALTER COLUMN emailbrokensubmission DROP DEFAULT');
        DB::statement('ALTER TABLE project ALTER COLUMN emailredundantfailures DROP DEFAULT');
        DB::statement('ALTER TABLE project ALTER COLUMN testtimestd DROP DEFAULT');
        DB::statement('ALTER TABLE project ALTER COLUMN testtimestdthreshold DROP DEFAULT');
        DB::statement('ALTER TABLE project ALTER COLUMN showtesttime DROP DEFAULT');
        DB::statement('ALTER TABLE project ALTER COLUMN testtimemaxstatus DROP DEFAULT');
        DB::statement('ALTER TABLE project ALTER COLUMN emailmaxitems DROP DEFAULT');
        DB::statement('ALTER TABLE project ALTER COLUMN emailmaxchars DROP DEFAULT');
        DB::statement('ALTER TABLE project ALTER COLUMN displaylabels DROP DEFAULT');
        DB::statement('ALTER TABLE project ALTER COLUMN autoremovetimeframe DROP DEFAULT');
        DB::statement('ALTER TABLE project ALTER COLUMN uploadquota DROP DEFAULT');
        DB::statement('ALTER TABLE project ALTER COLUMN showcoveragecode DROP DEFAULT');
        DB::statement('ALTER TABLE project ALTER COLUMN authenticatesubmissions DROP DEFAULT');
    }

    public function down(): void
    {
    }
};
