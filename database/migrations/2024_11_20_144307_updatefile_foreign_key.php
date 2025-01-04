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
        echo 'Deleting invalid rows for updatefile' . PHP_EOL;

        $done = false;
        $error_msg = "Could not determine min & max updateid for updatefile table.\n";
        $error_msg .= "Manually set \$start and \$max and rerun the migration\n";

        $start = DB::table('updatefile')->min('updateid');
        $max = DB::table('updatefile')->max('updateid');
        if (!is_numeric($start) || !is_numeric($max)) {
            if (DB::table('updatefile')->count() > 0) {
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
                if (DB::table('updatefile')->count() > 0) {
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
                DELETE FROM updatefile
                WHERE updateid BETWEEN {$start} AND {$end}
                      AND NOT EXISTS
                      (SELECT 1 FROM buildupdate WHERE buildupdate.id = updatefile.updateid)");
            $num_done += 50000;
            if ($end >= $max) {
                $done = true;
            } else {
                usleep(1);
                $start += 50000;
                // Calculate percentage of work completed so far.
                $percent = round(($num_done / $total) * 100, -1);
                if ($percent > $next_report) {
                    echo "Cleaning `updatefile`: {$next_report}%" . PHP_EOL;
                    $next_report = $next_report + 10;
                }
            }
        }

        echo "{$num_deleted} rows deleted from `updatefile`" . PHP_EOL;
        echo 'Adding foreign key constraint updatefile(updateid)->buildupdate(id)...';

        Schema::table('updatefile', function (Blueprint $table) {
            $table->foreign('updateid')->references('id')->on('buildupdate')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('updatefile', function (Blueprint $table) {
            $table->dropForeign(['updateid']);
        });
    }
};
