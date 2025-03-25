<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * @throws DivisionByZeroError
     */
    public function up(): void
    {
        echo 'Deleting invalid rows for build2uploadfile' . PHP_EOL;

        $done = false;
        $error_msg = "Could not determine min & max fileid for uploadfile table.\n";
        $error_msg .= "Manually set \$start and \$max and rerun the migration\n";

        $start = DB::table('uploadfile')->min('id');
        $max = DB::table('uploadfile')->max('id');
        if (!is_numeric($start) || !is_numeric($max)) {
            if (DB::table('uploadfile')->count() > 0) {
                throw new Exception($error_msg);
            } else {
                $done = true;
            }
        }

        $start = (int) $start;
        $max = (int) $max;
        $total = $max - $start + 1;

        if (!$done && $total < 1) {
            if (DB::table('uploadfile')->count() > 0) {
                throw new Exception($error_msg);
            } else {
                $done = true;
            }
        }

        $num_done = 0;
        $num_deleted = 0;
        $next_report = 10;
        while (!$done) {
            $end = $start + 49999;
            $num_deleted += DB::delete("
                DELETE FROM build2uploadfile
                WHERE fileid BETWEEN {$start} AND {$end}
                    AND NOT EXISTS (SELECT 1 FROM uploadfile WHERE uploadfile.id = build2uploadfile.fileid)");
            $num_done += 50000;
            if ($end >= $max) {
                $done = true;
            } else {
                usleep(1);
                $start += 50000;
                // Calculate percentage of work completed so far.
                $percent = round(($num_done / $total) * 100, -1);
                if ($percent > $next_report) {
                    echo "Cleaning `build2uploadfile`: {$next_report}%" . PHP_EOL;
                    $next_report = $next_report + 10;
                }
            }
        }

        echo "{$num_deleted} rows deleted from `build2uploadfile`" . PHP_EOL;
        echo 'Adding foreign key constraint build2uploadfile(fileid)->uploadfile(id)...';

        Schema::table('build2uploadfile', function (Blueprint $table) {
            // Change the pivot column type to match the uploadfile ID type and add a foreign-key constraint.
            $table->integer('fileid')->nullable(false)->change();
            $table->foreign('fileid')->references('id')->on('uploadfile')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('build2uploadfile', function (Blueprint $table) {
            $table->dropForeign(['fileid']);
            $table->bigInteger('fileid')->nullable(false)->change();
        });
    }
};
