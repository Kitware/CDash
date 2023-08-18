<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        echo 'Adding userid foreign key to authtoken table...' . PHP_EOL;
        Schema::table('authtoken', function (Blueprint $table) {
            $table->integer('userid')->nullable(false)->change();
            $table->foreign('userid')->references('id')->on('user')->cascadeOnDelete();
        });

        echo 'Adding userid foreign key to buildemail table...' . PHP_EOL;
        Schema::table('buildemail', function (Blueprint $table) {
            $table->integer('userid')->nullable(false)->change();
            $table->foreign('userid')->references('id')->on('user')->cascadeOnDelete();
        });

        echo 'Adding userid foreign key to buildnote table...' . PHP_EOL;
        Schema::table('buildnote', function (Blueprint $table) {
            $table->integer('userid')->nullable(false)->change();
            $table->foreign('userid')->references('id')->on('user')->cascadeOnDelete();
        });

        echo 'Adding userid foreign key to coveragefile2user table...' . PHP_EOL;
        Schema::table('coveragefile2user', function (Blueprint $table) {
            $table->integer('userid')->nullable(false)->change();
            $table->foreign('userid')->references('id')->on('user')->cascadeOnDelete();
        });

        echo 'Adding userid foreign key to labelemail table...' . PHP_EOL;
        Schema::table('labelemail', function (Blueprint $table) {
            $table->integer('userid')->nullable(false)->change();
            $table->foreign('userid')->references('id')->on('user')->cascadeOnDelete();
        });

        echo 'Adding userid foreign key to lockout table...' . PHP_EOL;
        Schema::table('lockout', function (Blueprint $table) {
            $table->integer('userid')->nullable(false)->change();
            $table->foreign('userid')->references('id')->on('user')->cascadeOnDelete();
        });

        echo 'Adding userid foreign key to password table...' . PHP_EOL;
        Schema::table('password', function (Blueprint $table) {
            $table->integer('userid')->nullable(false)->change();
            $table->foreign('userid')->references('id')->on('user')->cascadeOnDelete();
        });

        echo 'Adding userid foreign key to site2user table...' . PHP_EOL;
        Schema::table('site2user', function (Blueprint $table) {
            $table->integer('userid')->nullable(false)->change();
            $table->foreign('userid')->references('id')->on('user')->cascadeOnDelete();
        });

        echo 'Adding userid foreign key to user2project table...' . PHP_EOL;
        Schema::table('user2project', function (Blueprint $table) {
            $table->integer('userid')->nullable(false)->change();
            $table->foreign('userid')->references('id')->on('user')->cascadeOnDelete();
        });

        echo 'Adding userid foreign key to user2repository table...' . PHP_EOL;
        Schema::table('user2repository', function (Blueprint $table) {
            $table->integer('userid')->nullable(false)->change();
            $table->foreign('userid')->references('id')->on('user')->cascadeOnDelete();
        });

        echo 'Adding userid foreign key to userstatistics table...' . PHP_EOL;
        Schema::table('userstatistics', function (Blueprint $table) {
            $table->integer('userid')->nullable(false)->change();
            $table->foreign('userid')->references('id')->on('user')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('authtoken', function (Blueprint $table) {
            $table->integer('userid')->change();
            $table->dropForeign(['userid']);
        });

        Schema::table('buildemail', function (Blueprint $table) {
            $table->integer('userid')->change();
            $table->dropForeign(['userid']);
        });

        Schema::table('buildnote', function (Blueprint $table) {
            $table->integer('userid')->change();
            $table->dropForeign(['userid']);
        });

        Schema::table('coveragefile2user', function (Blueprint $table) {
            $table->integer('userid')->change();
            $table->dropForeign(['userid']);
        });

        Schema::table('labelemail', function (Blueprint $table) {
            $table->integer('userid')->change();
            $table->dropForeign(['userid']);
        });

        Schema::table('lockout', function (Blueprint $table) {
            $table->integer('userid')->change();
            $table->dropForeign(['userid']);
        });

        Schema::table('password', function (Blueprint $table) {
            $table->integer('userid')->change();
            $table->dropForeign(['userid']);
        });

        Schema::table('site2user', function (Blueprint $table) {
            $table->integer('userid')->change();
            $table->dropForeign(['userid']);
        });

        Schema::table('user2project', function (Blueprint $table) {
            $table->integer('userid')->change();
            $table->dropForeign(['userid']);
        });

        Schema::table('user2repository', function (Blueprint $table) {
            $table->integer('userid')->change();
            $table->dropForeign(['userid']);
        });

        Schema::table('userstatistics', function (Blueprint $table) {
            $table->integer('userid')->change();
            $table->dropForeign(['userid']);
        });
    }
};
