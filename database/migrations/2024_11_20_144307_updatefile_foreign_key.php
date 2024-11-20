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
        echo "Adding foreign key constraint updatefile(updateid)->buildupdate(id)...";
        $num_deleted = DB::delete("DELETE FROM updatefile WHERE updateid NOT IN (SELECT id FROM buildupdate)");
        echo $num_deleted . ' invalid rows deleted' . PHP_EOL;
        Schema::table('updatefile', function (Blueprint $table) {
            $table->foreign('updateid')->references('id')->on('buildupdate')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('updatefile', function (Blueprint $table) {
            $table->dropForeign(['updateid']);
        });
    }
};
