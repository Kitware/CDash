<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

require_once 'include/common.php';

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Delete any duplicate email addresses, keeping the oldest userid for each one.
        $user_table = qid('user');
        $num_deleted = DB::delete(
            "DELETE FROM $user_table WHERE id NOT IN (SELECT * FROM (SELECT MIN(id) FROM $user_table GROUP BY email) AS A)");
        echo $num_deleted . ' duplicate accounts deleted' . PHP_EOL;

        // Add the unique constraint.
        Schema::table('user', function (Blueprint $table) {
            $table->unique('email');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user', function (Blueprint $table) {
            $table->dropUnique(['email']);
        });
    }
};
