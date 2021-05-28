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

require_once 'include/cdashmail.php';

use CDash\Config;
use CDash\Log;
use CDash\Messaging\Notification\Email\EmailBuilder;
use CDash\Messaging\Notification\Email\EmailNotificationFactory;
use CDash\Messaging\Notification\Email\Mail;
use CDash\Messaging\Notification\Mailer;
use CDash\Messaging\Notification\NotificationCollection;
use CDash\Messaging\Notification\NotificationDirector;
use CDash\Messaging\Subscription\CommitAuthorSubscriptionBuilder;
use CDash\Messaging\Subscription\SubscriptionCollection;
use CDash\Messaging\Subscription\UserSubscriptionBuilder;
use CDash\Model\Build;
use CDash\Model\BuildEmail;
use CDash\Model\BuildGroup;
use CDash\Model\BuildConfigure;
use CDash\Model\BuildUpdate;
use CDash\Model\DynamicAnalysis;
use CDash\Model\LabelEmail;
use CDash\Model\Project;
use CDash\Model\Site;
use CDash\Model\UserProject;

/** Check for errors for a given build. Return false if no errors */
function check_email_errors($buildid, $checktesttimeingchanged, $testtimemaxstatus, $checkpreviouserrors)
{
    $errors = [];
    $errors['errors'] = true;
    $errors['hasfixes'] = false;

    // Configure errors
    $BuildConfigure = new BuildConfigure();
    $BuildConfigure->BuildId = $buildid;
    $errors['configure_errors'] = $BuildConfigure->ComputeErrors();

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
        $errors['test_errors'] += $Build->GetFailedTimeStatusTests(0, $testtimemaxstatus);
    }

    // Dynamic analysis errors
    $DynamicAnalysis = new DynamicAnalysis();
    $DynamicAnalysis->BuildId = $buildid;
    $errors['dynamicanalysis_errors'] = $DynamicAnalysis->GetNumberOfErrors();

    // Green build we return
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
function check_email_update_errors($buildid)
{
    $errors = array();
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
function get_email_summary($buildid, $errors, $errorkey, $maxitems, $maxchars, $testtimemaxstatus, $emailtesttimingchanged)
{
    $config = Config::getInstance();
    $build = new Build();
    $build->Id = $buildid;

    $serverURI = get_server_URI();
    $information = '';

    // Update information
    if ($errorkey == 'update_errors') {
        $information = "\n\n*Update*\n";

        $buildUpdate = new BuildUpdate();
        $buildUpdate->BuildId = $buildid;
        $update = $buildUpdate->GetUpdateForBuild(PDO::FETCH_OBJ);

        $information .= "Status: {$update->status} ({$serverURI}/viewUpdate.php?buildid={$buildid})\n";
        $information .= 'Command: ';
        $information .= substr($update->command, 0, $maxchars);
        $information .= "\n";
    } elseif ($errorkey == 'configure_errors') {
        // Configure information

        $information = "\n\n*Configure*\n";

        $buildConfigure = new BuildConfigure();
        $buildConfigure->BuildId = $buildid;
        $configure = $buildConfigure->GetConfigureForBuild(PDO::FETCH_OBJ);

        // If this is false pdo_execute called in BuildConfigure will
        // have already logged the error.
        if (is_object($configure)) {
            $information .= "Status: {$configure->status} ({$serverURI}/build/{$buildid})\n/configure";
            $information .= 'Output: ';
            $information .= substr($configure->log, 0, $maxchars);
            $information .= "\n";
        }
    } elseif ($errorkey == 'build_errors') {
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
    } elseif ($errorkey == 'build_warnings') {
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
    } elseif ($errorkey == 'test_errors') {

        // Local function to add a set of tests to our email message body.
        // This reduces copied & pasted code below.
        $AddTestsToEmail = function ($tests, $section_title) use ($buildid, $maxchars, $maxitems, $serverURI) {
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
    } elseif ($errorkey == 'dynamicanalysis_errors') {
        $da_query = pdo_query("SELECT name,id FROM dynamicanalysis WHERE status IN ('failed','notrun') AND buildid="
            . qnum($buildid) . " ORDER BY name LIMIT $maxitems");
        add_last_sql_error('sendmail');
        $numrows = pdo_num_rows($da_query);

        if ($numrows > 0) {
            $information .= "\n\n*Dynamic analysis tests failing or not run*";
            if ($numrows == $maxitems) {
                $information .= ' (first ' . $maxitems . ')';
            }
            $information .= "\n";

            while ($test_array = pdo_fetch_array($da_query)) {
                $info = $test_array['name'] . ' (' . $serverURI . '/viewDynamicAnalysisFile.php?id=' . $test_array['id'] . ")\n";
                $information .= substr($info, 0, $maxchars);
            }
            $information .= "\n";
        }
    } elseif ($errorkey === 'missing_tests') {
        // sanity check
        $missing = isset($errors['missing_tests']['count']) ? $errors['missing_tests']['count'] : 0;

        if ($missing) {
            $information .= "\n\n*Missing tests*";
            $length = $missing;
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

// Generate the title and body for a broken build.
//
function generate_broken_build_message($emailtext, $Build, $Project)
{
    include_once 'include/common.php';

    $serverURI = get_server_URI();

    $preamble = 'A submission to CDash for the project ' . $Project->Name . ' has ';
    $titleerrors = '(';

    $i = 0;
    foreach ($emailtext['category'] as $key => $value) {
        if ($key != 'update_errors'
            && $key != 'configure_errors'
            && $key != 'build_warnings'
            && $key != 'build_errors'
            && $key != 'test_errors'
            && $key != 'dynamicanalysis_errors'
            && $key != 'missing_tests'
        ) {
            continue;
        }

        if ($i > 0) {
            $preamble .= ' and ';
            $titleerrors .= ', ';
        }

        switch ($key) {
            case 'update_errors':
                $preamble .= 'update errors';
                $titleerrors .= 'u=' . $value;
                break;
            case 'configure_errors':
                $preamble .= 'configure errors';
                $titleerrors .= 'c=' . $value;
                break;
            case 'build_warnings':
                $preamble .= 'build warnings';
                $titleerrors .= 'w=' . $value;
                break;
            case 'build_errors':
                $preamble .= 'build errors';
                $titleerrors .= 'b=' . $value;
                break;
            case 'test_errors':
                $preamble .= 'failing tests';
                $titleerrors .= 't=' . $value;
                break;
            case 'dynamicanalysis_errors':
                $preamble .= 'failing dynamic analysis tests';
                $titleerrors .= 'd=' . $value;
                break;
            case 'missing_tests':
                $missing = $value['count'];
                if ($missing) {
                    $preamble .= 'missing tests';
                    $titleerrors .= 'm=' . $missing;
                }
                break;
        }
        $i++;
    }

    // Nothing to send so we stop.
    if ($i == 0) {
        return false;
    }

    // Title
    $titleerrors .= '):';
    $title = 'FAILED ' . $titleerrors . ' ' . $Project->Name;
    // In the sendmail.php file, in the sendmail function configure errors are now handled
    // with their own logic, and the sendmail logic removes the 'configure_error' key therefore
    // we should be able to verify that the following is a configure category by checking to see
    // if the 'configure_error' key exists in the category array of keys.
    $categories = array_keys($emailtext['category']);

    $useSubProjectName = $Build->GetSubProjectName() &&
        !in_array('configure_errors', $categories);

    // Because a configure error is not subproject specific, remove this from the output
    // if this is a configure_error.
    if ($useSubProjectName) {
        $title .= '/' . $Build->GetSubProjectName();
    }
    $title .= ' - ' . $Build->Name . ' - ' . $Build->Type;

    $preamble .= ".\n";
    $preamble .= 'You have been identified as one of the authors who ';
    $preamble .= 'have checked in changes that are part of this submission ';
    $preamble .= "or you are listed in the default contact list.\n\n";

    $body = 'Details on the submission can be found at ';

    $body .= $serverURI;
    $body .= "/build/{$Build->Id}";
    $body .= "\n\n";

    $body .= 'Project: ' . $Project->Name . "\n";

    // Because a configure error is not subproject specific, remove this from the output
    // if this is a configure_error.
    if ($useSubProjectName) {
        $body .= 'SubProject: ' . $Build->GetSubProjectName() . "\n";
    }

    $Site = new Site();
    $Site->Id = $Build->SiteId;

    $body .= 'Site: ' . $Site->GetName() . "\n";
    $body .= 'Build Name: ' . $Build->Name . "\n";
    $body .= 'Build Time: ' . date(FMT_DATETIMETZ, strtotime($Build->StartTime . ' UTC')) . "\n";
    $body .= 'Type: ' . $Build->Type . "\n";

    foreach ($emailtext['category'] as $key => $value) {
        switch ($key) {
            case 'update_errors':
                $body .= "Update errors: $value\n";
                break;
            case 'configure_errors':
                $body .= "Configure errors: $value\n";
                break;
            case 'build_warnings':
                $body .= "Warnings: $value\n";
                break;
            case 'build_errors':
                $body .= "Errors: $value\n";
                break;
            case 'test_errors':
                $body .= "Tests not passing: $value\n";
                break;
            case 'dynamicanalysis_errors':
                $body .= "Dynamic analysis tests failing: $value\n";
                break;
            case 'missing_tests':
                $missing = $value['count'];
                if ($missing) {
                    $body .= "Missing tests: {$missing}\n";
                }
        }
    }

    foreach ($emailtext['summary'] as $summary) {
        $body .= $summary;
    }

    $config = Config::getInstance();
    $serverName = $config->getServer();

    $footer = "\n-CDash on " . $serverName . "\n";
    return ['title' => $title, 'preamble' => $preamble, 'body' => $body,
            'footer' => $footer];
}

/** function to send email to site maintainers when the update
 * step fails */
function send_update_email($handler, $projectid)
{
    include_once 'include/common.php';
    require_once 'include/pdo.php';

    $Project = new Project();
    $Project->Id = $projectid;
    $Project->Fill();

    // If we shouldn't sent any emails we stop
    if ($Project->EmailBrokenSubmission == 0) {
        return;
    }

    // If the handler has a buildid (it should), we use it
    if (isset($handler->BuildId) && $handler->BuildId > 0) {
        $buildid = $handler->BuildId;
    } else {
        // Get the build id
        $name = $handler->getBuildName();
        $stamp = $handler->getBuildStamp();
        $sitename = $handler->getSiteName();
        $buildid = get_build_id($name, $stamp, $projectid, $sitename);
    }

    if ($buildid < 0) {
        return;
    }

    //  Check if the group as no email
    $Build = new Build();
    $Build->Id = $buildid;
    $groupid = $Build->GetGroup();

    $BuildGroup = new BuildGroup();
    $BuildGroup->SetId($groupid);

    // If we specified no email we stop here
    if ($BuildGroup->GetSummaryEmail() == 2) {
        return;
    }

    // Send out update errors to site maintainers
    $update_errors = check_email_update_errors($buildid);
    if ($update_errors['errors']) {
        // Find the site maintainer(s)
        $sitename = $handler->getSiteName();
        $siteid = $handler->getSiteId();
        $recipients = [];
        $email_addresses =
            pdo_query('SELECT email FROM ' . qid('user') . ',site2user WHERE ' . qid('user') . ".id=site2user.userid AND site2user.siteid='$siteid'");
        while ($email_addresses_array = pdo_fetch_array($email_addresses)) {
            $recipients[] = $email_addresses_array['email'];
        }

        if (!empty($recipients)) {
            $serverURI = get_server_URI();

            // Generate the email to send
            $subject = 'CDash [' . $Project->Name . '] - Update Errors for ' . $sitename;

            $update_info = pdo_query('SELECT command,status FROM buildupdate AS u,build2update AS b2u
                              WHERE b2u.updateid=u.id AND b2u.buildid=' . qnum($buildid));
            $update_array = pdo_fetch_array($update_info);

            $body = "$sitename has encountered errors during the Update step and you have been identified as the maintainer of this site.\n\n";
            $body .= "*Update Errors*\n";
            $body .= 'Status: ' . $update_array['status'] . ' (' . $serverURI . '/viewUpdate.php?buildid=' . $buildid . ")\n";
            if (cdashmail($recipients, $subject, $body)) {
                add_log('email sent to: ' . implode(', ', $recipients), 'send_update_email');
            } else {
                add_log('cannot send email to: ' . implode(', ', $recipients), 'send_update_email');
            }
        }
    }
}

/** Main function to send email if necessary */
function sendemail(ActionableBuildInterface $handler, $projectid)
{
    $Project = new Project();
    $Project->Id = $projectid;
    $Project->Fill();

    // If we shouldn't send any emails we stop
    if ($Project->EmailBrokenSubmission == 0) {
        return;
    }

    $buildGroup = $handler->GetBuildGroup();
    if ($buildGroup->GetSummaryEmail() == 2) {
        return;
    }

    $subscriptions = new SubscriptionCollection();

    foreach ($handler->GetSubscriptionBuilderCollection() as $builder) {
        $builder->build($subscriptions);
    }

    // TODO: remove NotificationCollection then pass subscriptions to constructor
    $builder = new EmailBuilder(new EmailNotificationFactory(), new NotificationCollection());
    $builder->setSubscriptions($subscriptions);

    $director = new NotificationDirector();
    $notifications = $director->build($builder);
    Mailer::send($notifications);
}
