<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::dropIfExists('userstatistics');
    }

    public function down(): void
    {
        if (!Schema::hasTable('userstatistics')) {
            Schema::create('userstatistics', function (Blueprint $table) {
                $table->integer('userid')->index();
                $table->smallInteger('projectid')->index();
                $table->timestamp('checkindate')->default(DB::raw('CURRENT_TIMESTAMP'))->index();
                $table->bigInteger('totalupdatedfiles');
                $table->bigInteger('totalbuilds');
                $table->bigInteger('nfixedwarnings');
                $table->bigInteger('nfailedwarnings');
                $table->bigInteger('nfixederrors');
                $table->bigInteger('nfailederrors');
                $table->bigInteger('nfixedtests');
                $table->bigInteger('nfailedtests');
            });
        }
    }
};
