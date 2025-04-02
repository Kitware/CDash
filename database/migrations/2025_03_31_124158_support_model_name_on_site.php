<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add position column on measurement table.
        Schema::table('siteinformation', function (Blueprint $table) {
            $table->text('processormodelname')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('siteinformation', 'processormodelname')) {
            Schema::table('siteinformation', function (Blueprint $table) {
                $table->dropColumn('processormodelname');
            });
        }
    }
};
