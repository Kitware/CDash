<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::dropIfExists('dailyupdatefile');
    }

    public function down(): void
    {
        if (!Schema::hasTable('dailyupdatefile')) {
            Schema::create('dailyupdatefile', function (Blueprint $table) {
                $table->integer('dailyupdateid')->default(0)->index();
                $table->string('filename')->default('');
                $table->timestamp('checkindate')->default(DB::raw('CURRENT_TIMESTAMP'));
                $table->string('author')->default('')->index();
                $table->string('email')->default('');
                $table->text('log');
                $table->string('revision', 60)->default('0');
                $table->string('priorrevision', 60)->default('0');
            });
        }
    }
};
