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
        // Add the unique constraint.
        // Note: This migration will fail if two projects with the same name exist in the database.
        //       Since having two projects with the same name is undefined behavior, and should
        //       never happen in theory, we defer to the CDash administrator to decide what to do.
        //       The CDash UI prevents duplicate projects from being created, so this should never
        //       occur under normal circumstances.
        Schema::table('project', function (Blueprint $table) {
            $table->unique('name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('project', function (Blueprint $table) {
            $table->dropUnique(['name']);
        });
    }
};
