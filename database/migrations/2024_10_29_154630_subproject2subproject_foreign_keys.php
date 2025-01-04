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
        echo 'Adding subprojectid foreign key to subproject2subproject table...';
        $num_deleted = DB::delete('DELETE FROM subproject2subproject WHERE subprojectid NOT IN (SELECT id FROM subproject)');
        echo $num_deleted . ' invalid rows deleted' . PHP_EOL;

        echo 'Adding dependsonid foreign key to subproject2subproject table...';
        $num_deleted = DB::delete('DELETE FROM subproject2subproject WHERE dependsonid NOT IN (SELECT id FROM subproject)');
        echo $num_deleted . ' invalid rows deleted' . PHP_EOL;

        Schema::table('subproject2subproject', function (Blueprint $table) {
            $table->bigInteger('subprojectid')->change();
            $table->bigInteger('dependsonid')->change();
            $table->foreign('subprojectid')->references('id')->on('subproject')->cascadeOnDelete();
            $table->foreign('dependsonid')->references('id')->on('subproject')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subproject2subproject', function (Blueprint $table) {
            $table->integer('subprojectid')->change();
            $table->integer('dependsonid')->change();
            $table->dropForeign(['subprojectid']);
            $table->dropForeign(['dependsonid']);
        });
    }
};
