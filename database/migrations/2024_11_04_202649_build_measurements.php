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

        Schema::create('targets', function (Blueprint $table) {
            $table->id();
            $table->integer('buildid')->nullable(false)->index();
            $table->enum('type', [
                'UNKNOWN',
                'STATIC_LIBRARY',
                'MODULE_LIBRARY',
                'SHARED_LIBRARY',
                'OBJECT_LIBRARY',
                'INTERFACE_LIBRARY',
                'EXECUTABLE',
            ])->nullable(false);
            $table->string('name')->nullable(false);
            $table->foreign('buildid')->references('id')->on('build')->cascadeOnDelete();
        });

        Schema::create('label2target', function (Blueprint $table) {
            $table->foreignId('targetid')->nullable(false)->references('id')->on('targets')->cascadeOnDelete();
            $table->bigInteger('labelid')->nullable(false);
            $table->foreign('labelid')->references('id')->on('label')->cascadeOnDelete();
            $table->unique(['targetid', 'labelid']);
            $table->unique(['labelid', 'targetid']);
        });

        Schema::create('buildcommands', function (Blueprint $table) {
            // Required columns for all types of commands
            $table->id();
            $table->integer('buildid')->nullable(false)->index();
            $table->foreign('buildid')->references('id')->on('build')->cascadeOnDelete();
            $table->foreignId('targetid')->nullable()->index()->references('id')->on('targets')->cascadeOnDelete();
            $table->enum('type', [
                'COMPILE',
                'LINK',
                'CUSTOM',
                'CMAKE_BUILD',
                'CMAKE_INSTALL',
                'INSTALL',
            ])->nullable(false);
            $table->dateTimeTz('starttime')->nullable(false);
            $table->integer('duration')->nullable(false);
            $table->text('command')->nullable(false);
            $table->text('workingdirectory')->nullable(false);
            $table->text('result')->nullable(false);

            // Optional columns depending on the type of command entered
            $table->text('source')->nullable();
            $table->string('language', 32)->nullable();
            $table->string('config', 32)->nullable();
        });

        Schema::create('buildmeasurements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('buildcommandid')->nullable(false)->index()->references('id')->on('buildcommands')->cascadeOnDelete();
            // 512 should be long enough for any reasonable value, while also being short enough to index if needed
            $table->string('type', 512)->nullable(false);
            $table->string('name', 512)->nullable(false);
            $table->string('value', 512)->nullable(false);
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
                $table->dropForeign(['targetid']);
            });
        }
        Schema::dropIfExists('buildcommands');

        if (Schema::hasTable('label2target')) {
            Schema::table('label2target', function (Blueprint $table) {
                $table->dropForeign(['labelid']);
                $table->dropForeign(['buildid']);
            });
        }
        Schema::dropIfExists('label2targets');

        if (Schema::hasTable('targets')) {
            Schema::table('targets', function (Blueprint $table) {
                $table->dropForeign(['buildid']);
            });
        }
        Schema::dropIfExists('targets');
    }
};
