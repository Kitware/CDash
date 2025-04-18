<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('project', 'banner')) {
            Schema::table('project', function (Blueprint $table) {
                $table->string('banner', 255)->nullable();
            });
        }

        if (config('database.default') === 'pgsql') {
            DB::update('
                UPDATE project
                SET banner = banner.text
                FROM banner
                WHERE banner.projectid = project.id
            ');
        } else {
            DB::update('
                UPDATE project, banner
                SET project.banner = banner.text
                WHERE banner.projectid = project.id
            ');
        }

        Schema::dropIfExists('banner');
    }

    public function down(): void
    {
        if (!Schema::hasTable('banner')) {
            Schema::create('banner', function (Blueprint $table) {
                $table->integer('projectid')->nullable(false);
                $table->foreign('projectid')->references('id')->on('project')->cascadeOnDelete();
                $table->string('text', 255)->nullable(false);
            });
        }

        DB::insert('
            INSERT INTO banner
            SELECT project.id AS projectid, project.banner AS text
            FROM project
            WHERE project.banner IS NOT NULL
        ');

        if (Schema::hasColumn('project', 'banner')) {
            Schema::dropColumns('project', ['banner']);
        }
    }
};
