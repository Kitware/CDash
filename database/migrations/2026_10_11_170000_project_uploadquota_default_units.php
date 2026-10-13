<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::update('UPDATE project SET uploadquota = 10737418240 WHERE uploadquota = 10');
    }

    public function down(): void
    {
    }
};
