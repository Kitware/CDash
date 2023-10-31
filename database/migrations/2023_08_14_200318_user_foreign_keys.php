<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

require_once 'include/common.php';

return new class extends Migration {
    private const tables_to_modify = [
        'authtoken',
        'buildemail',
        'buildnote',
        'coveragefile2user',
        'labelemail',
        'lockout',
        'password',
        'site2user',
        'user2project',
        'user2repository',
        'userstatistics',
    ];

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        foreach (self::tables_to_modify as $table) {
            echo "Adding userid foreign key to $table table...";
            $user_table = qid('user');
            $num_deleted = DB::delete("DELETE FROM $table WHERE userid NOT IN (SELECT id FROM $user_table)");
            echo $num_deleted . ' invalid rows deleted' . PHP_EOL;
            Schema::table($table, function (Blueprint $table) {
                $table->integer('userid')->nullable(false)->change();
                $table->foreign('userid')->references('id')->on('user')->cascadeOnDelete();
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        foreach (self::tables_to_modify as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->integer('userid')->change();
                $table->dropForeign(['userid']);
            });
        }
    }
};
