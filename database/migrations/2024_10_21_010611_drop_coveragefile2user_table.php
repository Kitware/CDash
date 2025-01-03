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
        Schema::table('coveragefile2user', function (Blueprint $table) {
            $table->dropForeign(['userid']);
        });
        Schema::dropIfExists('coveragefile2user');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Note: the data cannot be restored after running this migration.
        Schema::create('coveragefile2user', function (Blueprint $table) {
            $table->bigInteger('fileid')->index();
            $table->integer('userid')->index();
            $table->smallInteger('position');
            $table->foreign('userid')->references('id')->on('user')->cascadeOnDelete();
        });
    }
};
