<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        $num_deleted = DB::delete('
            DELETE FROM buildgroupposition
            WHERE buildgroupid NOT IN (
                SELECT id
                FROM buildgroup
            )
        ');

        if ($num_deleted > 0) {
            echo "Deleted $num_deleted invalid buildgroupposition rows.";
        }

        Schema::table('buildgroupposition', function (Blueprint $table) {
            $table->increments('id');
            $table->foreign('buildgroupid')
                ->references('id')
                ->on('buildgroup')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('buildgroupposition', function (Blueprint $table) {
            $table->dropColumn('id');
            $table->dropForeign(['buildgroupid']);
        });
    }
};
