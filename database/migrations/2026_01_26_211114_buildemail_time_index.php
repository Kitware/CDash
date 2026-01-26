<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
    public function up(): void
    {
        DB::statement('CREATE INDEX ON buildemail (time)');
    }

    public function down(): void
    {
    }
};
