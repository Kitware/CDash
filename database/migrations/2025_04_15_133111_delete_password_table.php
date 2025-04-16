<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::dropIfExists('password');

        Schema::table('users', function (Blueprint $table) {
            $table->dateTimeTz('password_updated_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('password')) {
            Schema::create('password', function (Blueprint $table) {
                $table->id();
                $table->timestamps(); // Unused?
                $table->integer('userid')->index();
                $table->string('password')->default('');
                $table->timestamp('date')->default(DB::raw('CURRENT_TIMESTAMP'));
            });
        }

        if (Schema::hasColumn('users', 'password_updated_at')) {
            Schema::dropColumns('users', ['password_updated_at']);
        }
    }
};
