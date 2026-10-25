<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // Keep the first-positioned copy of each duplicate, since that's the one displayed.
        DB::delete('
            DELETE FROM measurement a
            USING measurement b
            WHERE
                a.projectid = b.projectid
                AND a.name = b.name
                AND (a.position, a.id) > (b.position, b.id)
        ');

        DB::statement('CREATE UNIQUE INDEX ON measurement (projectid, name)');
    }

    public function down(): void
    {
    }
};
