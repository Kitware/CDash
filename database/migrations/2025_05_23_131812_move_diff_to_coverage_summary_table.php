<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('coveragesummary', function (Blueprint $table) {
            $table->integer('loctesteddiff')->nullable(false)->default(0);
            $table->integer('locuntesteddiff')->nullable(false)->default(0);
        });

        DB::update('
            UPDATE coveragesummary AS cs
            SET
                loctesteddiff = csd.loctested,
                locuntesteddiff = csd.locuntested
            FROM coveragesummarydiff AS csd
            WHERE cs.buildid = csd.buildid
        ');

        Schema::drop('coveragesummarydiff');
    }

    public function down(): void
    {
        Schema::create('coveragesummarydiff', function (Blueprint $table) {
            $table->foreignId('buildid')->references('id')->on('build');
            $table->integer('loctested')->default(0);
            $table->integer('locuntested')->default(0);
        });

        DB::update('
            INSERT INTO coveragesummarydiff (
                SELECT
                    buildid,
                    loctesteddiff AS loctested,
                    locuntesteddiff AS locuntested
                FROM coveragesummary
            )
        ');

        Schema::dropColumns('coveragesummary', ['loctesteddiff', 'locuntesteddiff']);
    }
};
