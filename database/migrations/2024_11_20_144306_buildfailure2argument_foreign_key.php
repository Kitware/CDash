<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        echo 'Deleting invalid rows for buildfailure2argument' . PHP_EOL;

        $done = false;
        $error_msg = "Could not determine min & max buildfailureid for buildfailure2argument table.\n";
        $error_msg .= "Manually set \$start and \$max and rerun the migration\n";

        $start = DB::table('buildfailure2argument')->min('buildfailureid');
        $max = DB::table('buildfailure2argument')->max('buildfailureid');
        if (!is_numeric($start) || !is_numeric($max)) {
            if (DB::table('buildfailure2argument')->count() > 0) {
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
                if (DB::table('buildfailure2argument')->count() > 0) {
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
                DELETE FROM buildfailure2argument
                WHERE buildfailureid BETWEEN {$start} AND {$end}
                      AND NOT EXISTS
                      (SELECT 1 FROM buildfailure WHERE buildfailure.id = buildfailure2argument.buildfailureid)");
            $num_done += 50000;
            if ($end >= $max) {
                $done = true;
            } else {
                usleep(1);
                $start += 50000;
                // Calculate percentage of work completed so far.
                $percent = round(($num_done / $total) * 100, -1);
                if ($percent > $next_report) {
                    echo "Cleaning `buildfailure2argument`: {$next_report}%" . PHP_EOL;
                    $next_report = $next_report + 10;
                }
            }
        }

        echo "{$num_deleted} rows deleted from `buildfailure2argument`" . PHP_EOL;
        echo "Adding foreign key constraint buildfailure2argument(buildfailureid)->buildfailure(id)...";

        Schema::table('buildfailure2argument', function (Blueprint $table) {
            $table->foreign('buildfailureid')->references('id')->on('buildfailure')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('buildfailure2argument', function (Blueprint $table) {
            $table->dropForeign(['buildfailureid']);
        });
    }
};
