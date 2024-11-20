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
        echo "Adding foreign key constraint buildfailure2argument(buildfailureid)->buildfailure(id)...";
        $num_deleted = DB::delete("DELETE FROM buildfailure2argument WHERE buildfailureid NOT IN (SELECT id FROM buildfailure)");
        echo $num_deleted . ' invalid rows deleted' . PHP_EOL;
        Schema::table('buildfailure2argument', function (Blueprint $table) {
            $table->foreign('buildfailureid')->references('id')->on('buildfailure')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('buildfailure2argument', function (Blueprint $table) {
            $table->dropForeign(['buildfailureid']);
        });
    }
};
