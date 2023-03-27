<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class DropCdashAtHome extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::dropIfExists('client_cmake');
        Schema::dropIfExists('client_compiler');
        Schema::dropIfExists('client_job');
        Schema::dropIfExists('client_jobschedule');
        Schema::dropIfExists('client_jobschedule2build');
        Schema::dropIfExists('client_jobschedule2cmake');
        Schema::dropIfExists('client_jobschedule2compiler');
        Schema::dropIfExists('client_jobschedule2library');
        Schema::dropIfExists('client_jobschedule2os');
        Schema::dropIfExists('client_jobschedule2site');
        Schema::dropIfExists('client_jobschedule2submission');
        Schema::dropIfExists('client_library');
        Schema::dropIfExists('client_os');
        Schema::dropIfExists('client_site');
        Schema::dropIfExists('client_site2cmake');
        Schema::dropIfExists('client_site2compiler');
        Schema::dropIfExists('client_site2library');
        Schema::dropIfExists('client_site2program');
        Schema::dropIfExists('client_site2project');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        if (!Schema::hasTable('client_cmake')) {
            Schema::create('client_cmake', function (Blueprint $table) {
                $table->integer('id', true);
                $table->string('version');
            });
        }
        if (!Schema::hasTable('client_compiler')) {
            Schema::create('client_compiler', function (Blueprint $table) {
                $table->integer('id', true);
                $table->string('name');
                $table->string('version');
            });
        }
        if (!Schema::hasTable('client_job')) {
            Schema::create('client_job', function (Blueprint $table) {
                $table->bigInteger('id', true)->unsigned();
                $table->bigInteger('scheduleid')->index();
                $table->tinyInteger('osid');
                $table->integer('siteid')->nullable();
                $table->dateTime('startdate')->default('1980-01-01 00:00:00')->index();
                $table->dateTime('enddate')->default('1980-01-01 00:00:00')->index();
                $table->integer('status')->nullable()->index();
                $table->text('output', 65535)->nullable();
                $table->integer('cmakeid');
                $table->integer('compilerid');
            });
        }
        if (!Schema::hasTable('client_jobschedule')) {
            Schema::create('client_jobschedule', function (Blueprint $table) {
                $table->bigInteger('id', true)->unsigned();
                $table->integer('userid')->nullable()->index();
                $table->integer('projectid')->nullable()->index();
                $table->text('cmakecache', 16777215);
                $table->text('clientscript', 65535)->nullable();
                $table->dateTime('startdate')->default('1980-01-01 00:00:00');
                $table->dateTime('enddate')->default('1980-01-01 00:00:00');
                $table->tinyInteger('type');
                $table->time('starttime')->default('00:00:00')->index();
                $table->decimal('repeattime', 6)->default(0.00)->index();
                $table->tinyInteger('enable')->index();
                $table->dateTime('lastrun')->default('1980-01-01 00:00:00');
                $table->string('repository', 512)->nullable()->default('');
                $table->string('module')->nullable()->default('');
                $table->string('buildnamesuffix')->nullable()->default('');
                $table->string('tag')->nullable()->default('');
                $table->tinyInteger('buildconfiguration')->nullable()->default(0);
                $table->text('description', 65535)->nullable();
            });
        }
        if (!Schema::hasTable('client_jobschedule2build')) {
            Schema::create('client_jobschedule2build', function (Blueprint $table) {
                $table->bigInteger('scheduleid')->unsigned();
                $table->integer('buildid');
                $table->unique(['scheduleid','buildid'], 'scheduleid');
            });
        }
        if (!Schema::hasTable('client_jobschedule2cmake')) {
            Schema::create('client_jobschedule2cmake', function (Blueprint $table) {
                $table->bigInteger('scheduleid');
                $table->integer('cmakeid');
                $table->unique(['scheduleid','cmakeid'], 'client_jobschedule2cmake_scheduleid');
            });
        }
        if (!Schema::hasTable('client_jobschedule2compiler')) {
            Schema::create('client_jobschedule2compiler', function (Blueprint $table) {
                $table->bigInteger('scheduleid');
                $table->integer('compilerid');
                $table->unique(['scheduleid','compilerid'], 'client_jobschedule2compiler_scheduleid');
            });
        }
        if (!Schema::hasTable('client_jobschedule2library')) {
            Schema::create('client_jobschedule2library', function (Blueprint $table) {
                $table->bigInteger('scheduleid');
                $table->integer('libraryid');
                $table->unique(['scheduleid','libraryid'], 'client_jobschedule2library_scheduleid');
            });
        }
        if (!Schema::hasTable('client_jobschedule2os')) {
            Schema::create('client_jobschedule2os', function (Blueprint $table) {
                $table->bigInteger('scheduleid');
                $table->integer('osid');
                $table->unique(['scheduleid','osid'], 'client_jobschedule2os_scheduleid');
            });
        }
        if (!Schema::hasTable('client_jobschedule2site')) {
            Schema::create('client_jobschedule2site', function (Blueprint $table) {
                $table->bigInteger('scheduleid');
                $table->integer('siteid');
                $table->unique(['scheduleid','siteid'], 'client_jobschedule2site_scheduleid');
            });
        }
        if (!Schema::hasTable('client_jobschedule2submission')) {
            Schema::create('client_jobschedule2submission', function (Blueprint $table) {
                $table->bigInteger('scheduleid')->unique();
                $table->bigInteger('submissionid')->primary();
            });
        }
        if (!Schema::hasTable('client_library')) {
            Schema::create('client_library', function (Blueprint $table) {
                $table->integer('id', true);
                $table->string('name');
                $table->string('version');
            });
        }
        if (!Schema::hasTable('client_os')) {
            Schema::create('client_os', function (Blueprint $table) {
                $table->integer('id', true);
                $table->string('name')->index();
                $table->string('version')->index();
                $table->tinyInteger('bits')->default(32)->index();
            });
        }
        if (!Schema::hasTable('client_site2cmake')) {
            Schema::create('client_site2cmake', function (Blueprint $table) {
                $table->integer('siteid')->nullable()->index();
                $table->integer('cmakeid')->nullable()->index();
                $table->string('path', 512)->nullable();
            });
        }
        if (!Schema::hasTable('client_site2compiler')) {
            Schema::create('client_site2compiler', function (Blueprint $table) {
                $table->integer('siteid')->nullable()->index();
                $table->integer('compilerid')->nullable();
                $table->string('command', 512)->nullable();
                $table->string('generator');
            });
        }
        if (!Schema::hasTable('client_site2library')) {
            Schema::create('client_site2library', function (Blueprint $table) {
                $table->integer('siteid')->nullable()->index();
                $table->integer('libraryid')->nullable();
                $table->string('path', 512)->nullable();
                $table->string('include', 512);
            });
        }
        if (!Schema::hasTable('client_site2program')) {
            Schema::create('client_site2program', function (Blueprint $table) {
                $table->integer('siteid')->index();
                $table->string('name', 30);
                $table->string('version', 30);
                $table->string('path', 512);
            });
        }
        if (!Schema::hasTable('client_site2project')) {
            Schema::create('client_site2project', function (Blueprint $table) {
                $table->integer('projectid')->nullable();
                $table->integer('siteid')->nullable()->index();
            });
        }
        if (!Schema::hasTable('client_site')) {
            Schema::create('client_site', function (Blueprint $table) {
                $table->integer('id', true);
                $table->string('name')->nullable()->index();
                $table->integer('osid')->nullable()->index();
                $table->string('systemname')->nullable();
                $table->string('host')->nullable();
                $table->string('basedirectory', 512);
                $table->dateTime('lastping')->default('1980-01-01 00:00:00')->index();
            });
        }
    }
}
