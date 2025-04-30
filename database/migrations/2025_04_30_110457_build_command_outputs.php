<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('buildcommandoutputs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('buildcommandid')->nullable()->index()->references('id')->on('buildcommands')->cascadeOnDelete();
            $table->bigInteger('size');
            $table->text('name')->nullable(false);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('buildcommandoutputs');
    }
};
