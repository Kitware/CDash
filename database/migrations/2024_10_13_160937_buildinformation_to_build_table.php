<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('build', function (Blueprint $table) {
            $table->string('osname', 255)->nullable();
            $table->string('osplatform', 255)->nullable();
            $table->string('osrelease', 255)->nullable();
            $table->string('osversion', 255)->nullable();
            $table->string('compilername', 255)->nullable();
            $table->string('compilerversion', 255)->nullable();
        });

        if (config('database.default') === 'pgsql') {
            DB::update('
                UPDATE build
                SET
                    osname = buildinformation.osname,
                    osplatform = buildinformation.osplatform,
                    osrelease = buildinformation.osrelease,
                    osversion = buildinformation.osversion,
                    compilername = buildinformation.compilername,
                    compilerversion = buildinformation.compilerversion
                FROM buildinformation
                WHERE build.id = buildinformation.buildid
            ');
        } else {
            DB::update('
                UPDATE build, buildinformation
                SET
                    build.osname = buildinformation.osname,
                    build.osplatform = buildinformation.osplatform,
                    build.osrelease = buildinformation.osrelease,
                    build.osversion = buildinformation.osversion,
                    build.compilername = buildinformation.compilername,
                    build.compilerversion = buildinformation.compilerversion
                WHERE
                    build.id = buildinformation.buildid
            ');
        }

        Schema::dropIfExists('buildinformation');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::create('buildinformation', function (Blueprint $table) {
            $table->integer('buildid');
            $table->string('osname', 255)->nullable();
            $table->string('osplatform', 255)->nullable();
            $table->string('osrelease', 255)->nullable();
            $table->string('osversion', 255)->nullable();
            $table->string('compilername', 255)->nullable();
            $table->string('compilerversion', 255)->nullable();
            $table->foreign('buildid')->references('id')->on('build');
        });

        DB::insert('
            INSERT INTO buildinformation (
                buildid,
                osname,
                osplatform,
                osrelease,
                osversion,
                compilername,
                compilerversion
            )
            SELECT
                build.id,
                build.osname,
                build.osplatform,
                build.osrelease,
                build.osversion,
                build.compilername,
                build.compilerversion
            FROM build
        ');

        Schema::table('build', function (Blueprint $table) {
            $table->dropColumn([
                'osname',
                'osplatform',
                'osrelease',
                'osversion',
                'compilername',
                'compilerversion',
            ]);
        });
    }
};
