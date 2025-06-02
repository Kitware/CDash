<?php

/*=========================================================================
  Program:   CDash - Cross-Platform Dashboard System
  Module:    $Id$
  Language:  PHP
  Date:      $Date$
  Version:   $Revision$

  Copyright (c) Kitware, Inc. All rights reserved.
  See LICENSE or http://www.cdash.org/licensing/ for details.

  This software is distributed WITHOUT ANY WARRANTY; without even
  the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR
  PURPOSE. See the above copyright notices for more information.
=========================================================================*/

use App\Models\Configure;
use CDash\Database;
use CDash\Model\Build;
use CDash\Model\BuildUpdate;
use CDash\Model\DynamicAnalysis;

/** Check for errors for a given build. Return false if no errors */
function check_email_errors(int $buildid, bool $checktesttimeingchanged, int $testtimemaxstatus, bool $checkpreviouserrors): array
{
    $errors = [];
    $errors['errors'] = true;
    $errors['hasfixes'] = false;

    // Configure errors
    /** @var Configure $BuildConfigure */
    $BuildConfigure = App\Models\Build::findOrFail($buildid)->configure()->first();
    $errors['configure_errors'] = $BuildConfigure->status ?? 0;

    // Build errors and warnings
    $Build = new Build();
    $Build->Id = $buildid;
    $Build->FillFromId($buildid);
    $errors['build_errors'] = $Build->GetNumberOfErrors();
    $errors['build_warnings'] = $Build->GetNumberOfWarnings();

    // Test errors
    $errors['test_errors'] = $Build->GetNumberOfFailedTests();
    $errors['test_errors'] += $Build->GetNumberOfNotRunTests();
    if ($checktesttimeingchanged) {
        $errors['test_errors'] += count($Build->GetFailedTimeStatusTests(0, $testtimemaxstatus));
    }

    // Dynamic analysis errors
    $DynamicAnalysis = new DynamicAnalysis();
    $DynamicAnalysis->BuildId = $buildid;
    $errors['dynamicanalysis_errors'] = $DynamicAnalysis->GetNumberOfErrors();

    // Check if this is a clean build.
    if ($errors['configure_errors'] == 0
        && $errors['build_errors'] == 0
        && $errors['build_warnings'] == 0
        && $errors['test_errors'] == 0
        && $errors['dynamicanalysis_errors'] == 0
    ) {
        $errors['errors'] = false;
    }

    // look for the previous build
    $previousbuildid = $Build->GetPreviousBuildId();
    if ($previousbuildid > 0) {
        $error_differences = $Build->GetErrorDifferences($buildid);
        if ($errors['errors'] && $checkpreviouserrors && $errors['dynamicanalysis_errors'] == 0) {
            // If the builderroddiff positive and configureerrordiff and testdiff positive are zero we don't send an email
            // we don't send any emails
            if ($error_differences['buildwarningspositive'] <= 0
                && $error_differences['builderrorspositive'] <= 0
                && $error_differences['configurewarnings'] <= 0
                && $error_differences['configureerrors'] <= 0
                && $error_differences['testfailedpositive'] <= 0
                && $error_differences['testnotrunpositive'] <= 0
            ) {
                $errors['errors'] = false;
            }
        }

        if ($error_differences['buildwarningsnegative'] > 0
            || $error_differences['builderrorsnegative'] > 0
            || $error_differences['configurewarnings'] < 0
            || $error_differences['configureerrors'] < 0
            || $error_differences['testfailednegative'] > 0
            || $error_differences['testnotrunnegative'] > 0
        ) {
            $errors['hasfixes'] = true;
            $errors['fixes']['configure_fixes'] = $error_differences['configurewarnings'] + $error_differences['configureerrors'];
            $errors['fixes']['builderror_fixes'] = $error_differences['builderrorsnegative'];
            $errors['fixes']['buildwarning_fixes'] = $error_differences['buildwarningsnegative'];
            $errors['fixes']['test_fixes'] = $error_differences['testfailednegative'] + $error_differences['testnotrunnegative'];
        }
    }
    return $errors;
}

/** Check for update errors for a given build. */
function check_email_update_errors(int $buildid): array
{
    $errors = [];
    $errors['errors'] = true;
    $errors['hasfixes'] = false;

    // Update errors
    $BuildUpdate = new BuildUpdate();
    $BuildUpdate->BuildId = $buildid;
    $errors['update_errors'] = $BuildUpdate->GetNumberOfErrors();

    // Green build we return
    if ($errors['update_errors'] == 0) {
        $errors['errors'] = false;
    }
    return $errors;
}

/** Return a summary for a category of error */
function get_email_summary(int $buildid, array $errors, $errorkey, int $maxitems, int $maxchars, int $testtimemaxstatus, bool $emailtesttimingchanged): string
{
    $build = new Build();
    $build->Id = $buildid;

    $serverURI = url('/');
    $information = '';

    // Update information
    if ($errorkey === 'update_errors') {
        $information = "\n\n*Update*\n";

        $buildUpdate = new BuildUpdate();
        $buildUpdate->BuildId = $buildid;
        $update = $buildUpdate->GetUpdateForBuild();
        if ($update === false) {
            throw new RuntimeException('Error querying update status for build ' . $buildid);
        }

        $information .= "Status: {$update['status']} ({$serverURI}/build/{$buildid}/update)\n";
        $information .= 'Command: ';
        $information .= substr($update['command'], 0, $maxchars);
        $information .= "\n";
    } elseif ($errorkey == 'configure_errors') {
        // Configure information

        $information = "\n\n*Configure*\n";

        /** @var Configure $configure */
        $configure = App\Models\Build::findOrFail($buildid)->configure()->first();

        // If this is false pdo_execute called in BuildConfigure will
        // have already logged the error.
        if ($configure !== null) {
            $information .= "Status: {$configure->status} ({$serverURI}/build/{$buildid})\n/configure";
            $information .= 'Output: ';
            $information .= substr($configure->log, 0, $maxchars);
            $information .= "\n";
        }
    } elseif ($errorkey === 'build_errors') {
        $information .= "\n\n*Error*";

        // type 0 = error
        // type 1 = warning
        // filter out errors of type error
        $errors = $build->GetErrors(['type' => Build::TYPE_ERROR], PDO::FETCH_OBJ);

        if (count($errors) > $maxitems) {
            $errors = array_slice($errors, 0, $maxitems);
            $information .= ' (first ' . $maxitems . ')';
        }

        $information .= "\n";

        foreach ($errors as $error) {
            $info = '';
            if (strlen($error->sourcefile) > 0) {
                $info .= "{$error->sourcefile} line {$error->sourceline} ({$serverURI}/viewBuildError.php?buildid={$buildid})";
                $info .= "{$error->text}\n";
            } else {
                $info .= "{$error->text}\n{$error->postcontext}\n";
            }
            $information .= mb_substr($info, 0, $maxchars);
        }

        // filter out just failures of type error
        $failures = $build->GetFailures(['type' => Build::TYPE_ERROR], PDO::FETCH_OBJ);

        // not yet accounted for in integration tests
        if (count($failures) > $maxitems) {
            $failures = array_slice($failures, 0, $maxitems);
            $information .= " (first {$maxitems})";
        }

        foreach ($failures as $fail) {
            $info = '';
            if (strlen($fail->sourcefile) > 0) {
                $info .= "{$fail->sourcefile} ({$serverURI}/viewBuildError.php?type=0&buildid={$buildid})\n";
            }
            if (strlen($fail->stdoutput) > 0) {
                $info .= "{$fail->stdoutput}\n";
            }
            if (strlen($fail->stderror) > 0) {
                $info .= "{$fail->stderror}\n";
            }
            $information .= mb_substr($info, 0, $maxchars);
        }
        $information .= "\n";
    } elseif ($errorkey === 'build_warnings') {
        $information .= "\n\n*Warnings*";

        $warnings = $build->GetErrors(['type' => Build::TYPE_WARN], PDO::FETCH_OBJ);

        if (count($warnings) > $maxitems) {
            $information .= ' (first ' . $maxitems . ')';
            $warnings = array_slice($warnings, 0, $maxitems);
        }

        if (!empty($warnings)) {
            $information .= "\n";
        }

        foreach ($warnings as $warning) {
            $info = '';
            if (strlen($warning->sourcefile) > 0) {
                $info .= "{$warning->sourcefile} line {$warning->sourceline} ({$serverURI}/viewBuildError.php?type=1&buildid={$buildid})\n";
                $info .= "{$warning->text}\n";
            } else {
                $info .= "{$warning->text}\n{$warning->postcontext}\n";
            }
            $information .= substr($info, 0, $maxchars);
        }

        $failures = $build->GetFailures(['type' => Build::TYPE_WARN], PDO::FETCH_OBJ);

        if (count($failures) > $maxitems) {
            $information .= ' (first ' . $maxitems . ')';
            $failures = array_slice($failures, 0, $maxitems);
        }

        if (!empty($failures)) {
            $information .= "\n";
        }

        foreach ($failures as $fail) {
            $info = '';
            if (strlen($fail->sourcefile) > 0) {
                $info .= "{$fail->sourcefile} ({$serverURI}/viewBuildError.php?type=1&buildid={$buildid})\n";
            }
            if (strlen($fail->stdoutput) > 0) {
                $info .= "{$fail->stdoutput}\n";
            }
            if (strlen($fail->stderror) > 0) {
                $info .= "{$fail->stderror}\n";
            }
            $information .= substr($info, 0, $maxchars) . "\n";
        }
        $information .= "\n";
    } elseif ($errorkey === 'test_errors') {
        // Local function to add a set of tests to our email message body.
        // This reduces copied & pasted code below.
        $AddTestsToEmail = function ($tests, $section_title) use ($maxchars, $maxitems, $serverURI) {
            $num_tests = count($tests);
            if ($num_tests < 1) {
                return '';
            }

            $information = "\n\n*$section_title*";
            if ($num_tests == $maxitems) {
                $information .= " (first $maxitems)";
            }
            $information .= "\n";

            foreach ($tests as $test) {
                $info = "{$test['name']} | {$test['details']} | ({$serverURI}/test/{$test['buildtestid']})\n";
                $information .= substr($info, 0, $maxchars);
            }
            $information .= "\n";
            return $information;
        };

        $information .= $AddTestsToEmail($build->GetFailedTests($maxitems), 'Tests failing');
        if ($emailtesttimingchanged) {
            $information .= $AddTestsToEmail($build->GetFailedTimeStatusTests($maxitems, $testtimemaxstatus), 'Tests failing time status');
        }
        $information .= $AddTestsToEmail($build->GetNotRunTests($maxitems), 'Tests not run');
    } elseif ($errorkey === 'dynamicanalysis_errors') {
        $db = Database::getInstance();
        $da_query = $db->executePrepared("
                        SELECT name, id
                        FROM dynamicanalysis
                        WHERE
                            status IN ('failed', 'notrun')
                            AND buildid=?
                        ORDER BY name
                        LIMIT $maxitems
                    ", [$buildid]);
        add_last_sql_error('sendmail');
        $numrows = count($da_query);

        if ($numrows > 0) {
            $information .= "\n\n*Dynamic analysis tests failing or not run*";
            if ($numrows === $maxitems) {
                $information .= ' (first ' . $maxitems . ')';
            }
            $information .= "\n";

            foreach ($da_query as $test_array) {
                $info = $test_array['name'] . ' (' . $serverURI . '/viewDynamicAnalysisFile.php?id=' . $test_array['id'] . ")\n";
                $information .= substr($info, 0, $maxchars);
            }
            $information .= "\n";
        }
    } elseif ($errorkey === 'missing_tests') {
        // sanity check
        $missing = $errors['missing_tests']['count'] ?? 0;

        if ($missing) {
            $information .= "\n\n*Missing tests*";
            if ($errors['missing_tests']['count'] > $maxitems) {
                $information .= " (first {$maxitems})";
            }

            $list = array_slice($errors['missing_tests']['list'], 0, $maxitems);
            $information .= PHP_EOL;
            $url = "({$serverURI}/viewTest.php?buildid={$buildid})";
            $information .= implode(" {$url}\n", array_values($list));
            $information .= $url;
            $information .= PHP_EOL;
        }
    }

    return $information;
}
