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
        echo 'Adding foreign key constraint dynamicanalysisdefect(dynamicanalysisid)->dynamicanalysis(id)...';
        $num_deleted = DB::delete('DELETE FROM dynamicanalysisdefect WHERE dynamicanalysisid NOT IN (SELECT id FROM dynamicanalysis)');
        echo $num_deleted . ' invalid rows deleted' . PHP_EOL;
        Schema::table('dynamicanalysisdefect', function (Blueprint $table) {
            $table->foreign('dynamicanalysisid')->references('id')->on('dynamicanalysis')->cascadeOnDelete();
            $table->index(['value']);
            $table->index(['type']);
            $table->unique(['dynamicanalysisid', 'value', 'type']);
            $table->unique(['dynamicanalysisid', 'type', 'value']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('dynamicanalysisdefect', function (Blueprint $table) {
            $table->dropForeign(['dynamicanalysisid']);
            $table->dropIndex(['value']);
            $table->dropIndex(['type']);
            $table->dropUnique(['dynamicanalysisid', 'value', 'type']);
            $table->dropUnique(['dynamicanalysisid', 'type', 'value']);
        });
    }
};
