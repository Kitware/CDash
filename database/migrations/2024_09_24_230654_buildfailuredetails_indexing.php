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
        Schema::table('buildfailuredetails', function (Blueprint $table) {
            $table->index(['exitcondition']);
            $table->index(['language']);
            $table->index(['outputfile']);
            $table->index(['outputtype']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('buildfailuredetails', function (Blueprint $table) {
            $table->dropIndex(['exitcondition']);
            $table->dropIndex(['language']);
            $table->dropIndex(['outputfile']);
            $table->dropIndex(['outputtype']);
        });
    }
};
