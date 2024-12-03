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
        // Completely re-do the table
        if (Schema::hasTable('buildmeasurements')) {
            Schema::table('buildmeasurements', function (Blueprint $table) {
                $table->dropForeign(['buildid']);
            });
        }
        Schema::dropIfExists('buildmeasurements');

        Schema::create('buildcommands', function (Blueprint $table) {
            // Required columns for all types of commands
            $table->id();
            $table->integer('buildid')->nullable(false)->index();
            $table->tinyInteger('type')->nullable(false);
            $table->dateTimeTz('starttime')->nullable(false);
            $table->integer('duration')->nullable(false);
            $table->text('binarydirectory')->nullable();
            $table->text('command')->nullable();
            $table->text('result')->nullable();

            // Optional columns depending on the type of command entered
            $table->string('language', 32)->nullable();
            $table->text('source')->nullable();
            $table->text('target')->nullable();
            $table->string('targettype', 32)->nullable();

            $table->foreign('buildid')->references('id')->on('build')->cascadeOnDelete();
        });

        Schema::create('buildmeasurements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('buildcommandid')
                ->nullable(false)
                ->index()
                ->references('id')
                ->on('buildcommands')
                ->cascadeOnDelete();
            $table->string('type', 512)->nullable(false);
            $table->string('name', 512)->nullable(false);
            $table->string('value', 512)->nullable(false);
        });

        Schema::create('buildcommands2labels', function (Blueprint $table) {
            $table->foreignId('buildcommandid')
                ->nullable(false)
                ->index()
                ->references('id')
                ->on('buildcommands')
                ->cascadeOnDelete();
            $table->foreignId('labelid')
                ->nullable(false)
                ->index()
                ->references('id')
                ->on('label')
                ->cascadeOnDelete();
            $table->unique(['buildcommandid', 'labelid']);
            $table->unique(['labelid', 'buildcommandid']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No need to revert the changes to the buildmeasurements table because it isn't hooked up to anything yet.
        if (Schema::hasTable('buildmeasurements')) {
            Schema::table('buildmeasurements', function (Blueprint $table) {
                $table->dropForeign(['buildcommandid']);
            });
        }
        Schema::dropIfExists('buildmeasurements');

        if (Schema::hasTable('buildcommands')) {
            Schema::table('buildcommands', function (Blueprint $table) {
                $table->dropForeign(['buildid']);
            });
        }
        Schema::dropIfExists('buildcommands');

        Schema::dropIfExists('buildcommands2labels');
    }
};
