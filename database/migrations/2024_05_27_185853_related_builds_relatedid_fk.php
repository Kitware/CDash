<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('related_builds')) {
            echo "Adding relatedid foreign key to related_builds table...";
            $num_deleted = DB::delete('DELETE FROM related_builds WHERE relatedid NOT IN (SELECT id FROM build)');
            echo $num_deleted . ' invalid rows deleted' . PHP_EOL;
            Schema::table('related_builds', function (Blueprint $table) {
                $table->integer('relatedid')->change();
                $table->foreign('relatedid')->references('id')->on('build')->cascadeOnDelete();
            });
        } else {
            echo "ERROR: related_builds table does not exist!";
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('related_builds')) {
            Schema::table('related_builds', function (Blueprint $table) {
                $table->bigInteger('relatedid')->change();
                $table->dropForeign(['relatedid']);
            });
        } else {
            echo "ERROR: related_builds table does not exist!";
        }
    }
};
