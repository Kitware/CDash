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
        Schema::table('buildnote', function (Blueprint $table) {
            $table->dropForeign(['buildid']);
            $table->dropForeign(['userid']);
        });

        Schema::rename('buildnote', 'comments');

        Schema::table('comments', function (Blueprint $table) {
            $table->increments('id');
            $table->timestamp('timestamp')->default('CURRENT_TIMESTAMP')->change();
            $table->renameColumn('note', 'text');
            $table->foreign(['userid'])->references('id')->on('user');
            $table->foreign(['buildid'])->references('id')->on('build');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('comments', function (Blueprint $table) {
            $table->dropForeign(['buildid']);
            $table->dropForeign(['userid']);
        });

        Schema::rename('comments', 'buildnote');

        Schema::table('buildnote', function (Blueprint $table) {
            $table->dropColumn('id');
            $table->timestamp('timestamp')->change();
            $table->renameColumn('text', 'note');
            $table->foreign(['userid'])->references('id')->on('user');
            $table->foreign(['buildid'])->references('id')->on('build');
        });
    }
};
