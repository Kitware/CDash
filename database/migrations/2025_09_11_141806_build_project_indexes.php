<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::statement('CREATE INDEX ON build (projectid,name)');
        DB::statement('CREATE INDEX ON build (projectid,siteid)');
        DB::statement('CREATE INDEX ON build (projectid,starttime)');
        DB::statement('CREATE INDEX ON build (projectid,endtime)');
    }

    public function down(): void
    {
    }
};
