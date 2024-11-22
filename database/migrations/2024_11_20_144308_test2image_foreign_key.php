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
        echo 'Deleting invalid rows for test2image' . PHP_EOL;

        $done = false;
        $error_msg = "Could not determine min & max outputid for test2image table.\n";
        $error_msg .= "Manually set \$start and \$max and rerun the migration\n";

        $start = DB::table('test2image')->min('outputid');
        $max = DB::table('test2image')->max('outputid');
        if (!is_numeric($start) || !is_numeric($max)) {
            if (DB::table('test2image')->count() > 0) {
                throw new Exception($error_msg);
            } else {
                $done = true;
            }
        }

        if (!$done) {
            $start = intval($start);
            $max = intval($max);
            $total = $max - $start + 1;
            if ($total < 1) {
                if (DB::table('test2image')->count() > 0) {
                    throw new Exception($error_msg);
                } else {
                    $done = true;
                }
            }
        }

        $num_done = 0;
        $num_deleted = 0;
        $next_report = 10;
        while (!$done) {
            $end = $start + 49999;
            $num_deleted += DB::delete("
                DELETE FROM test2image
                WHERE outputid BETWEEN {$start} AND {$end}
                      AND NOT EXISTS
                      (SELECT 1 FROM testoutput WHERE testoutput.id = test2image.outputid)");
            $num_done += 50000;
            if ($end >= $max) {
                $done = true;
            } else {
                usleep(1);
                $start += 50000;
                // Calculate percentage of work completed so far.
                $percent = round(($num_done / $total) * 100, -1);
                if ($percent > $next_report) {
                    echo "Cleaning `test2image`: {$next_report}%" . PHP_EOL;
                    $next_report = $next_report + 10;
                }
            }
        }

        echo "{$num_deleted} rows deleted from `test2image`" . PHP_EOL;
        echo "Adding foreign key constraint test2image(outputid)->testoutput(id)...";

        Schema::table('test2image', function (Blueprint $table) {
            $table->foreign('outputid')->references('id')->on('testoutput')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('test2image', function (Blueprint $table) {
            $table->dropForeign(['outputid']);
        });
    }
};
