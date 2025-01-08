<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $num_rules_edited = 0;
        $num_rules_deleted = 0;

        echo PHP_EOL;

        $rules_with_wildcards = DB::select("
            SELECT *
            FROM build2grouprule b2gr
            INNER JOIN buildgroup bg ON (bg.id = b2gr.groupid)
            WHERE
                b2gr.buildname LIKE '%\%%'
                AND bg.type = 'Latest'
        ");

        foreach ($rules_with_wildcards as $b2gr) {
            $extra_where_clauses = '';
            $extra_params = [];

            $parentgroupid_set = $b2gr->parentgroupid > 0;
            if ($parentgroupid_set) {
                $extra_where_clauses .= ' AND b2g.groupid = ? ';
                $extra_params[] = $b2gr->parentgroupid;
            }

            $siteid_set = $b2gr->siteid > 0;
            if ($siteid_set) {
                $extra_where_clauses .= ' AND b.siteid = ? ';
                $extra_params[] = $b2gr->siteid;
            }

            $most_recent_build_name_for_rule = DB::select("
                SELECT b.name
                FROM build b
                LEFT JOIN build2group b2g ON (b.id = b2g.buildid)
                WHERE
                    b.projectid = ?
                    AND b.name LIKE ?
                    $extra_where_clauses
                ORDER BY b.submittime DESC
                LIMIT 1
            ", array_merge([
                $b2gr->projectid,
                $b2gr->buildname,
            ], $extra_params))[0] ?? [];

            if ($most_recent_build_name_for_rule === []) {
                echo "Rule '$b2gr->buildname' is unused for projectid $b2gr->projectid. Deleting rule...";
                DB::delete('
                    DELETE FROM build2grouprule
                    WHERE
                        groupid = ?
                        AND buildname = ?
                        AND siteid = ?
                        AND parentgroupid = ?
                ', [
                    $b2gr->groupid,
                    $b2gr->buildname,
                    $b2gr->siteid,
                    $b2gr->parentgroupid,
                ]);
                echo 'done.' . PHP_EOL;
                $num_rules_deleted++;
            } else {
                echo "Replacing rule '$b2gr->buildname' with rule '$most_recent_build_name_for_rule->name'...";
                DB::update('
                    UPDATE build2grouprule
                    SET buildname = ?
                    WHERE
                        groupid = ?
                        AND buildname = ?
                        AND siteid = ?
                        AND parentgroupid = ?
                ', [
                    $most_recent_build_name_for_rule->name,
                    $b2gr->groupid,
                    $b2gr->buildname,
                    $b2gr->siteid,
                    $b2gr->parentgroupid,
                ]);
                echo 'done.' . PHP_EOL;
                $num_rules_edited++;
            }
        }

        echo PHP_EOL . "$num_rules_edited rules edited." . PHP_EOL;
        echo "$num_rules_deleted rules deleted." . PHP_EOL . PHP_EOL;
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // This migration is irreversible.
    }
};
