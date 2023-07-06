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
        Schema::table('coverage', function (Blueprint $table) {
            $table->integer('id', true);
            $table->renameColumn('branchstested', 'branchestested');
            $table->renameColumn('branchsuntested', 'branchesuntested');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('coverage', function (Blueprint $table) {
            $table->dropColumn('id');
            $table->renameColumn('branchestested', 'branchstested');
            $table->renameColumn('branchesuntested', 'branchsuntested');
        });
    }
};
