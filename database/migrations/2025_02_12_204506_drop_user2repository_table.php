<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('user2repository')) {
            Schema::table('user2repository', function (Blueprint $table) {
                $table->dropForeign(['projectid']);
                $table->dropForeign(['userid']);
                $table->drop();
            });
        }
    }

    public function down(): void
    {
        // No way to reverse the deletion of credentials
        if (!Schema::hasTable('user2repository')) {
            Schema::create('user2repository', function (Blueprint $table) {
                $table->integer('userid')->index();
                $table->string('credential', 255)->index();
                $table->integer('projectid')->default(0)->index();
                $table->foreign('userid')->references('id')->on('users')->cascadeOnDelete();
                $table->foreign('projectid')->references('id')->on('project')->cascadeOnDelete();
            });
        }
    }
};
