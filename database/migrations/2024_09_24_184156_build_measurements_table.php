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
        Schema::create('buildmeasurements', function (Blueprint $table) {
            $table->id();
            $table->integer('buildid')->nullable(false);
            $table->smallInteger('type')->nullable(false);
            $table->string('name', 511)->nullable(false);
            $table->string('source', 511)->nullable(false);
            $table->string('value', 255)->nullable(false);

            $table->foreign('buildid')->references('id')->on('build')->cascadeOnDelete();
            $table->index(['buildid', 'name']);
            $table->index(['buildid', 'source']);
            $table->index(['buildid', 'type']);
            $table->index(['buildid', 'value']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('buildmeasurements');
    }
};
