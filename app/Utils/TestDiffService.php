<?php

declare(strict_types=1);

namespace App\Utils;

use App\Enums\TestDiffType;
use App\Models\TestDiff;
use CDash\Model\Build;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * This class is responsible for populating the `testdiff` table.
 * It also sets `build2test::newstatus` = 1 as appropriate.
 **/

class TestDiffService
{
    public static function computeDifferences(Build $build) : bool
    {
        // Populate previous build.
        $previous_build_id = $build->GetPreviousBuildId();
        if ($previous_build_id === 0) {
            return false;
        }
        $previous_build = new Build();
        $previous_build->Id = $previous_build_id;
        $previous_build->FillFromId($previous_build->Id);

        return self::computeDifferencesForType($build, $previous_build, TestDiffType::NotRun->value) &&
            self::computeDifferencesForType($build, $previous_build, TestDiffType::Failed->value) &&
            self::computeDifferencesForType($build, $previous_build, TestDiffType::Passed->value) &&
            self::computeDifferencesForType($build, $previous_build, TestDiffType::FailedTimeStatus->value);
    }

    private static function computeDifferencesForType(Build $build, Build $previous_build, int $type) : bool
    {
        $tests = [];
        $previous_tests = [];

        switch ($type) {
            case TestDiffType::NotRun->value:
                $tests = $build->GetNotRunTests();
                $previous_tests = $previous_build->GetNotRunTests();
                break;
            case TestDiffType::Failed->value:
                $tests = $build->GetFailedTests();
                $previous_tests = $previous_build->GetFailedTests();
                break;
            case TestDiffType::Passed->value:
                $tests = $build->GetPassedTests();
                $previous_tests = $previous_build->GetPassedTests();
                break;
            case TestDiffType::FailedTimeStatus->value:
                $tests = $build->GetFailedTimeStatusTests();
                $previous_tests = $previous_build->GetFailedTimeStatusTests();
                break;
            default:
                return false;
        }

        // Number of tests for this build that newly achieved this status.
        $num_positive = 0;

        // Number of tests for this build that no longer have this status when compared to the previous build.
        $num_negative = 0;

        // Array of build2test::ids corresponding to tests from this build whose statuses
        // has changed since the previous build.
        $newstatus_b2t_ids = [];

        foreach ($tests as $test) {
            if (array_search($test['name'], array_column($previous_tests, 'name')) === false) {
                // This test was found for the current build but not the previous build.
                $num_positive += 1;
                $newstatus_b2t_ids[] = $test['buildtestid'];
            }
        }

        // Set build2test::newstatus=1 for tests whose status has changed since this previous build.
        DB::table('build2test')
            ->whereIntegerInRaw('id', $newstatus_b2t_ids)
            ->update(['newstatus' => 1]);

        foreach ($previous_tests as $previous_test) {
            if (array_search($previous_test['name'], array_column($tests, 'name')) === false) {
                // This test had status=$type in the previous build, but it doesn't any more.
                $num_negative += 1;
            }
        }

        // Insert/update the testdiff row inside a transaction to (hopefully) gracefully
        // handle race conditions & such.
        DB::transaction(function () use ($build, $type, $num_positive, $num_negative) {
            // Check if a testdiff record already exists for this build and type.
            $existing_testdiff = DB::table('testdiff')
                ->where('buildid', $build->Id)
                ->where('type', $type)
                ->lockForUpdate()
                ->first();

            $existing_num_positive = 0;
            $existing_num_negative = 0;
            if ($existing_testdiff) {
                $existing_num_positive = $existing_testdiff->difference_positive;
                $existing_num_negative = $existing_testdiff->difference_negative;
            }

            // Don't log if no diff.
            if ($num_positive === 0 && $num_negative === 0 && $existing_num_positive === 0 && $existing_num_negative === 0) {
                return;
            }

            // UPDATE or INSERT a new record as necessary.
            if ($existing_testdiff) {
                DB::table('testdiff')
                    ->where('buildid', $build->Id)
                    ->where('type', $type)
                    ->update([
                        'difference_positive' => $num_positive,
                        'difference_negative' => $num_negative,
                    ]);
            } else {
                DB::table('testdiff')
                    ->insertOrIgnore([[
                        'buildid' => $build->Id,
                        'type' => $type,
                        'difference_positive' => $num_positive,
                        'difference_negative' => $num_negative,
                    ]]);
            }
        }, 5);

        return true;
    }
}
