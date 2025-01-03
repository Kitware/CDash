<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('label2dynamicanalysis')) {
            Schema::table('label2dynamicanalysis', function (Blueprint $table) {
                $table->index(['dynamicanalysisid', 'labelid']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('label2dynamicanalysis')) {
            Schema::table('label2dynamicanalysis', function (Blueprint $table) {
                $table->dropIndex(['dynamicanalysisid', 'labelid']);
            });
        }
    }
};
