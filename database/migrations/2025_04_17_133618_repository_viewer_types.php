<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::update("UPDATE project SET cvsviewertype = NULL WHERE cvsviewertype NOT IN ('github', 'gitlab')");
    }

    public function down(): void
    {
    }
};
