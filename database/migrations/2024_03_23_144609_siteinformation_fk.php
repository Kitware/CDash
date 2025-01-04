<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('siteinformation')) {
            echo 'Adding siteid foreign key to siteinformation table...';
            $num_deleted = DB::delete('DELETE FROM siteinformation WHERE siteid NOT IN (SELECT id FROM site)');
            echo $num_deleted . ' invalid rows deleted' . PHP_EOL;
            Schema::table('siteinformation', function (Blueprint $table) {
                $table->foreign('siteid')->references('id')->on('site')->cascadeOnDelete();
            });
        } else {
            echo 'ERROR: siteinformation table does not exist!';
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('siteinformation')) {
            Schema::table('siteinformation', function (Blueprint $table) {
                $table->dropForeign(['siteid']);
            });
        } else {
            echo 'ERROR: siteinformation table does not exist!';
        }
    }
};
