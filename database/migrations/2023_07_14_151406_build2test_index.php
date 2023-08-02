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
        Schema::table('build2test', function (Blueprint $table) {
            $table->index(['testid', 'buildid']);
            $table->index(['buildid', 'testid']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('build2test', function (Blueprint $table) {
            $table->dropIndex(['testid', 'buildid']);
            $table->dropIndex(['buildid', 'testid']);
        });
    }
};
