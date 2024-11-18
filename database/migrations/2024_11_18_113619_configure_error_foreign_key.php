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
        echo "Adding foreign key constraint configureerror(configureid)->configure(id)...";
        $num_deleted = DB::delete("DELETE FROM configureerror WHERE configureid NOT IN (SELECT id FROM configure)");
        echo $num_deleted . ' invalid rows deleted' . PHP_EOL;
        Schema::table('configureerror', function (Blueprint $table) {
            $table->foreign('configureid')->references('id')->on('configure')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('configureerror', function (Blueprint $table) {
            $table->dropForeign(['configureid']);
        });
    }
};
