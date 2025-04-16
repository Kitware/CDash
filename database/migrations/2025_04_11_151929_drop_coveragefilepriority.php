<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::dropIfExists('coveragefilepriority');
    }

    public function down(): void
    {
        if (!Schema::hasTable('coveragefilepriority')) {
            Schema::create('coveragefilepriority', function (Blueprint $table) {
                $table->bigInteger('id', true);
                $table->tinyInteger('priority')->index();
                $table->string('fullpath')->index();
                $table->integer('projectid')->index();
            });
        }
    }
};
