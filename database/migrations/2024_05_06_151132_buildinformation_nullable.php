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
        // Make all of the user-controlled fields nullable
        Schema::table('buildinformation', function (Blueprint $table) {
            $table->string('osname', 255)->nullable()->change();
            $table->string('osplatform', 255)->nullable()->change();
            $table->string('osrelease', 255)->nullable()->change();
            $table->string('osversion', 255)->nullable()->change();
            $table->string('compilername', 255)->nullable()->change();
            $table->string('compilerversion', 255)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::update("UPDATE buildinformation SET osname = '' WHERE osname IS NULL");
        DB::update("UPDATE buildinformation SET osplatform = '' WHERE osplatform IS NULL");
        DB::update("UPDATE buildinformation SET osrelease = '' WHERE osrelease IS NULL");
        DB::update("UPDATE buildinformation SET osversion = '' WHERE osversion IS NULL");
        DB::update("UPDATE buildinformation SET compilername = '' WHERE compilername IS NULL");
        DB::update("UPDATE buildinformation SET compilerversion = '' WHERE compilerversion IS NULL");

        Schema::table('buildinformation', function (Blueprint $table) {
            $table->string('osname', 255)->nullable(false)->change();
            $table->string('osplatform', 255)->nullable(false)->change();
            $table->string('osrelease', 255)->nullable(false)->change();
            $table->string('osversion', 255)->nullable(false)->change();
            $table->string('compilername', 255)->nullable(false)->change();
            $table->string('compilerversion', 255)->nullable(false)->change();
        });
    }
};
