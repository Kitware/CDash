<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
    public function up(): void
    {
        DB::statement('ALTER TABLE project ALTER COLUMN emaillowcoverage DROP DEFAULT');
        DB::statement('ALTER TABLE project ALTER COLUMN emaillowcoverage TYPE boolean USING emaillowcoverage::text::boolean');
        DB::statement('ALTER TABLE project ALTER COLUMN emaillowcoverage SET DEFAULT FALSE');

        DB::statement('ALTER TABLE project ALTER COLUMN emailtesttimingchanged DROP DEFAULT');
        DB::statement('ALTER TABLE project ALTER COLUMN emailtesttimingchanged TYPE boolean USING emailtesttimingchanged::text::boolean');
        DB::statement('ALTER TABLE project ALTER COLUMN emailtesttimingchanged SET DEFAULT FALSE');

        DB::statement('ALTER TABLE project ALTER COLUMN emailbrokensubmission DROP DEFAULT');
        DB::statement('ALTER TABLE project ALTER COLUMN emailbrokensubmission TYPE boolean USING emailbrokensubmission::text::boolean');
        DB::statement('ALTER TABLE project ALTER COLUMN emailbrokensubmission SET DEFAULT TRUE');

        DB::statement('ALTER TABLE project ALTER COLUMN emailredundantfailures DROP DEFAULT');
        DB::statement('ALTER TABLE project ALTER COLUMN emailredundantfailures TYPE boolean USING emailredundantfailures::text::boolean');
        DB::statement('ALTER TABLE project ALTER COLUMN emailredundantfailures SET DEFAULT FALSE');

        DB::statement('ALTER TABLE project ALTER COLUMN showtesttime DROP DEFAULT');
        DB::statement('ALTER TABLE project ALTER COLUMN showtesttime TYPE boolean USING showtesttime::text::boolean');
        DB::statement('ALTER TABLE project ALTER COLUMN showtesttime SET DEFAULT FALSE');
        DB::statement('ALTER TABLE project ALTER COLUMN showtesttime SET NOT NULL');

        DB::statement('ALTER TABLE project ALTER COLUMN displaylabels DROP DEFAULT');
        DB::statement('ALTER TABLE project ALTER COLUMN displaylabels TYPE boolean USING displaylabels::text::boolean');
        DB::statement('ALTER TABLE project ALTER COLUMN displaylabels SET DEFAULT TRUE');
        DB::statement('ALTER TABLE project ALTER COLUMN displaylabels SET NOT NULL');

        DB::statement('ALTER TABLE project ALTER COLUMN showcoveragecode DROP DEFAULT');
        DB::statement('ALTER TABLE project ALTER COLUMN showcoveragecode TYPE boolean USING showcoveragecode::text::boolean');
        DB::statement('ALTER TABLE project ALTER COLUMN showcoveragecode SET DEFAULT TRUE');
        DB::statement('ALTER TABLE project ALTER COLUMN showcoveragecode SET NOT NULL');

        DB::statement('ALTER TABLE project ALTER COLUMN sharelabelfilters DROP DEFAULT');
        DB::statement('ALTER TABLE project ALTER COLUMN sharelabelfilters TYPE boolean USING sharelabelfilters::text::boolean');
        DB::statement('ALTER TABLE project ALTER COLUMN sharelabelfilters SET DEFAULT FALSE');
        DB::statement('ALTER TABLE project ALTER COLUMN sharelabelfilters SET NOT NULL');

        DB::statement('ALTER TABLE project ALTER COLUMN authenticatesubmissions DROP DEFAULT');
        DB::statement('ALTER TABLE project ALTER COLUMN authenticatesubmissions TYPE boolean USING authenticatesubmissions::text::boolean');
        DB::statement('ALTER TABLE project ALTER COLUMN authenticatesubmissions SET DEFAULT FALSE');
        DB::statement('ALTER TABLE project ALTER COLUMN authenticatesubmissions SET NOT NULL');

        DB::statement('ALTER TABLE project ALTER COLUMN viewsubprojectslink DROP DEFAULT');
        DB::statement('ALTER TABLE project ALTER COLUMN viewsubprojectslink TYPE boolean USING viewsubprojectslink::text::boolean');
        DB::statement('ALTER TABLE project ALTER COLUMN viewsubprojectslink SET DEFAULT TRUE');
    }

    public function down(): void
    {
    }
};
