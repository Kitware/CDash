<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('project_invitations', function (Blueprint $table) {
            $table->id();
            $table->string('email', 255)->index()->nullable(false)->index();
            $table->integer('invited_by_id')->index()->nullable(false);
            $table->foreign('invited_by_id')->references('id')->on('users')->cascadeOnDelete();
            $table->integer('project_id')->index()->nullable(false);
            $table->foreign('project_id')->references('id')->on('project')->cascadeOnDelete();
            $table->enum('role', ['USER', 'ADMINISTRATOR'])->nullable(false);
            $table->timestampTz('invitation_timestamp')->index()->nullable(false)->useCurrent();

            $table->unique(['email', 'project_id']);
        });
        DB::insert('INSERT INTO project_invitations SELECT * from user_invitations');
        Schema::drop('user_invitations');

        Schema::create('global_invitations', function (Blueprint $table) {
            $table->id();
            $table->string('email', 255)->unique()->nullable(false);
            $table->integer('invited_by_id')->index()->nullable(false);
            $table->foreign('invited_by_id')->references('id')->on('users')->cascadeOnDelete();
            $table->enum('role', ['USER', 'ADMINISTRATOR'])->nullable(false);
            $table->timestampTz('invitation_timestamp')->index()->nullable(false)->useCurrent();
            $table->text('password')->nullable(false);
        });
    }

    public function down(): void
    {
        Schema::create('user_invitations', function (Blueprint $table) {
            $table->id();
            $table->string('email', 255)->index()->nullable(false)->index();
            $table->integer('invited_by_id')->index()->nullable(false);
            $table->foreign('invited_by_id')->references('id')->on('users')->cascadeOnDelete();
            $table->integer('project_id')->index()->nullable(false);
            $table->foreign('project_id')->references('id')->on('project')->cascadeOnDelete();
            $table->enum('role', ['USER', 'ADMINISTRATOR'])->nullable(false);
            $table->timestampTz('invitation_timestamp')->index()->nullable(false)->useCurrent();

            $table->unique(['email', 'project_id']);
        });
        DB::insert('INSERT INTO user_invitations SELECT * from project_invitations');
        Schema::drop('project_invitations');

        Schema::drop('global_invitations');
    }
};
