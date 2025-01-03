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
        if (Schema::hasTable('site2user')) {
            echo "Adding siteid foreign key to site2user table...";
            $num_deleted = DB::delete('DELETE FROM site2user WHERE siteid NOT IN (SELECT id FROM site)');
            echo $num_deleted . ' invalid rows deleted' . PHP_EOL;
            Schema::table('site2user', function (Blueprint $table) {
                $table->foreign('siteid')->references('id')->on('site')->cascadeOnDelete();
                $table->unique(['userid', 'siteid']);
                $table->unique(['siteid', 'userid']);
            });
        } else {
            echo "ERROR: site2user table does not exist!";
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('site2user')) {
            Schema::table('site2user', function (Blueprint $table) {
                $table->dropForeign(['siteid']);
                $table->dropUnique(['userid', 'siteid']);
                $table->dropUnique(['siteid', 'userid']);
            });
        } else {
            echo "ERROR: site2user table does not exist!";
        }
    }
};
