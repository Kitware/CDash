<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('user_invitations', function (Blueprint $table) {
            $table->id();
            $table->string('email', 255)->index()->nullable(false)->index();
            $table->foreignId('invited_by_id')->index()->nullable(false)->references('id')->on('users')->cascadeOnDelete();
            $table->foreignId('project_id')->index()->nullable(false)->references('id')->on('project')->cascadeOnDelete();
            $table->enum('role', ['USER', 'ADMINISTRATOR'])->nullable(false);
            $table->timestampTz('invitation_timestamp')->index()->nullable(false)->useCurrent();

            $table->unique(['email', 'project_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_invitations');
    }
};
