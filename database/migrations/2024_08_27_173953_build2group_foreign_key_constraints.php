<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        $num_deleted = DB::delete('
            DELETE FROM build2group
            WHERE groupid NOT IN (
                SELECT id FROM buildgroup
            )
        ');

        if ($num_deleted > 0) {
            echo "Deleted $num_deleted invalid build2group rows.";
        }

        Schema::table('build2group', function (Blueprint $table) {
            $table->dropPrimary();
            $table->unique(['buildid', 'groupid']);
            $table->unique(['groupid', 'buildid']);
            $table->foreign('groupid')->references('id')->on('buildgroup')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('build2group', function (Blueprint $table) {
            $table->primary(['buildid']);
            $table->dropUnique(['buildid', 'groupid']);
            $table->dropUnique(['groupid', 'buildid']);
            $table->dropForeign(['groupid']);
        });
    }
};
