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
