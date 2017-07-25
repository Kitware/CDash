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

/** Check the email preferences for errors */
function checkEmailPreferences($emailcategory, $errors, $fixes = false)
{
    include_once 'include/common.php';

    if ($fixes) {
        $configures = $errors['fixes']['configure_fixes'];
        $builderrors = $errors['fixes']['builderror_fixes'];
        $buildwarnings = $errors['fixes']['buildwarning_fixes'];
        $tests = $errors['fixes']['test_fixes'];
    } else {
        $configures = $errors['configure_errors'];
        $builderrors = $errors['build_errors'];
        $buildwarnings = $errors['build_warnings'];
        $tests = $errors['test_errors'];
        $dynamicanalysis = $errors['dynamicanalysis_errors'];
    }

    if ($configures > 0 && check_email_category('configure', $emailcategory)) {
        return true;
    }
    if ($buildwarnings > 0 && check_email_category('warning', $emailcategory)) {
        return true;
    }
    if ($builderrors > 0 && check_email_category('error', $emailcategory)) {
        return true;
    }
    if ($tests > 0 && check_email_category('test', $emailcategory)) {
        return true;
    }
    if ($dynamicanalysis > 0 && check_email_category('dynamicanalysis', $emailcategory)) {
        return true;
    }
    return false;
}

/** Given a user check if we should send an email based on labels */
function checkEmailLabel($projectid, $userid, $buildid, $emailcategory = 62)
{
    include_once 'models/labelemail.php';
    include_once 'models/build.php';
    $LabelEmail = new LabelEmail();
    $LabelEmail->UserId = $userid;
    $LabelEmail->ProjectId = $projectid;

    $labels = $LabelEmail->GetLabels();
    if (count($labels) == 0) {
        // if the number of subscribed labels is zero we send the email
        return true;
    }

    $Build = new Build();
    $Build->Id = $buildid;

    $labelarray = array();

    if (check_email_category('update', $emailcategory)) {
        $labelarray['update']['errors'] = 1;
    }
    if (check_email_category('configure', $emailcategory)) {
        $labelarray['configure']['errors'] = 1;
    }
    if (check_email_category('warning', $emailcategory)) {
        $labelarray['build']['warnings'] = 1;
    }
    if (check_email_category('error', $emailcategory)) {
        $labelarray['build']['errors'] = 1;
    }
    if (check_email_category('test', $emailcategory)) {
        $labelarray['test']['errors'] = 1;
    }

    $buildlabels = $Build->GetLabels($labelarray);
    if (count(array_intersect($labels, $buildlabels)) > 0) {
        return true;
    }
    return false;
}

/** Check for errors for a given build. Return false if no errors */
function check_email_errors($buildid, $checktesttimeingchanged, $testtimemaxstatus, $checkpreviouserrors)
{
    // Includes
    require_once 'models/buildconfigure.php';
    require_once 'models/build.php';
    require_once 'models/buildtest.php';
    require_once 'models/dynamicanalysis.php';

    $errors = array();
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
    $BuildTest = new BuildTest();
    $BuildTest->BuildId = $buildid;
    $errors['test_errors'] = $BuildTest->GetNumberOfFailures($checktesttimeingchanged, $testtimemaxstatus);

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
    // Includes
    require_once 'models/buildupdate.php';
    require_once 'models/build.php';

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

/** Return the list of user id and committer emails who should get emails */
function lookup_emails_to_send($errors, $buildid, $projectid, $buildtype, $fixes = false, $collectUnregisteredCommitters = false)
{
    require_once 'models/user.php';
    require_once 'models/userproject.php';

    $userids = array();
    $committeremails = array();

    // Check if we know to whom we should send the email
    $updatefiles = pdo_query('SELECT author,email,committeremail FROM updatefile AS uf,build2update AS b2u
                            WHERE b2u.updateid=uf.updateid AND b2u.buildid=' . qnum($buildid));
    add_last_sql_error('sendmail', $projectid, $buildid);
    while ($updatefiles_array = pdo_fetch_array($updatefiles)) {
        $author = $updatefiles_array['author'];

        // Skip the Local User, old CVS/SVN issue
        if ($author == 'Local User') {
            continue;
        }

        $emails = array();
        $authorEmail = $updatefiles_array['email'];
        $emails[] = $authorEmail;
        $committerEmail = $updatefiles_array['committeremail'];
        if ($committerEmail != '' && $committerEmail != $authorEmail) {
            $emails[] = $committerEmail;
        }

        foreach ($emails as $email) {
            $UserProject = new UserProject();
            $UserProject->RepositoryCredential = $author;
            $UserProject->ProjectId = $projectid;

            $filled = false;
            if ($email != '') {
                $user = new User();
                $id = $user->GetIdFromEmail($email);
                if ($id) {
                    $UserProject->UserId = $id;
                    $filled = $UserProject->FillFromUserId();
                }
            }

            if (!$filled && !$UserProject->FillFromRepositoryCredential()) {
                global $CDASH_WARN_ABOUT_UNREGISTERED_COMMITTERS;
                global $CDASH_TESTING_MODE;
                if (!$CDASH_TESTING_MODE && $CDASH_WARN_ABOUT_UNREGISTERED_COMMITTERS) {
                    $name = $email == '' ? $author : $email;
                    // Daily updates send an email to tell adminsitrator that the user
                    // is not registered but we log anyway
                    add_log('User: ' . $name . ' is not registered (or has no email) for the project ' . $projectid,
                        'SendEmail', LOG_WARNING, $projectid, $buildid);
                }
                if ($collectUnregisteredCommitters && $email != '' &&
                    !in_array($email, $committeremails)
                ) {
                    $committeremails[] = $email;
                }
                continue;
            }

            // If we already have this user in the array, don't bother checking
            // user preferences again... (avoid below calls to checkEmail*
            // functions)
            if (in_array($UserProject->UserId, $userids)) {
                continue;
            }

            // If the user doesn't want to receive email
            if ($fixes && !$UserProject->EmailSuccess) {
                continue;
            }

            // Check the categories
            if (!checkEmailPreferences($UserProject->EmailCategory, $errors, $fixes)) {
                continue;
            }

            // Check if the labels are defined for this user
            if (!checkEmailLabel($projectid, $UserProject->UserId, $buildid, $UserProject->EmailCategory)) {
                continue;
            }

            $userids[] = $UserProject->UserId;
        }
    }

    // If it's fixes only concerned users should get the email
    if ($fixes) {
        $result = array();
        $result['userids'] = $userids;
        $result['committeremails'] = $committeremails;
        return $result;
    }

    // Select the users who want to receive all emails
    $user = pdo_query('SELECT emailtype,emailcategory,userid FROM user2project WHERE user2project.projectid=' . qnum($projectid) . ' AND user2project.emailtype>1');
    add_last_sql_error('sendmail');
    while ($user_array = pdo_fetch_array($user)) {
        if (in_array($user_array['userid'], $userids)) {
            continue;
        }

        // If the user doesn't want to receive email
        if (!checkEmailPreferences($user_array['emailcategory'], $errors)) {
            continue;
        }

        // Check if the labels are defined for this user
        if (!checkEmailLabel($projectid, $user_array['userid'], $buildid, $user_array['emailcategory'])) {
            continue;
        }

        // Nightly build notification
        if ($user_array['emailtype'] == 2 && $buildtype == 'Nightly') {
            $userids[] = $user_array['userid'];
        } elseif ($user_array['emailtype'] == 3) {
            // want to receive all emails

            $userids[] = $user_array['userid'];
        }
    }

    $result = array();
    $result['userids'] = $userids;
    $result['committeremails'] = $committeremails;
    return $result;
}

/** Return a summary for a category of error */
function get_email_summary($buildid, $errors, $errorkey, $maxitems, $maxchars, $testtimemaxstatus, $emailtesttimingchanged)
{
    include 'config/config.php';
    include_once 'models/build.php';
    include_once 'models/buildconfigure.php';
    include_once 'models/buildupdate.php';

    global $CDASH_BASE_URL, $CDASH_ASYNCHRONOUS_SUBMISSION;

    $build = new Build();
    $build->Id = $buildid;

    $serverURI = get_server_URI();
    // In the case of asynchronous submission, the serverURI contains /cdash
    // we need to remove it
    if ($CDASH_BASE_URL == '' && $CDASH_ASYNCHRONOUS_SUBMISSION) {
        $serverURI = substr($serverURI, 0, strrpos($serverURI, '/'));
    }

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
            $information .= "Status: {$configure->status} ({$serverURI}/viewConfigure.php?buildid={$buildid})\n";
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
                $info .= "{$error->sourcefile} line {$error->sourceline} ({$serverURI}/viewBuildError.php?{$buildid})";
                $info .= "{$error->text}\n";
            } else {
                $info .= "{$error->text}\n{$error->postcontext}\n";
            }
            $information .= substr($info, 0, $maxchars);
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
                $info .= "{$fail->sourcefile} ({$serverURI}/viewBuildError.php?type=0&build={$buildid})\n";
            }
            if (strlen($fail->stdoutput) > 0) {
                $info .= "{$fail->stdoutput}\n";
            }
            if (strlen($fail->stderror) > 0) {
                $info .= "{$fail->stderror}\n";
            }
            $information .= substr($info, 0, $maxchars);
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
        $AddTestsToEmail = function ($tests, $section_title) use ($maxitems, $buildid, $maxchars, $maxitems, $serverURI) {
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
                $info = "{$test['name']} | {$test['details']} | ({$serverURI}/testDetails.php?test={$test['id']}&build={$buildid})\n";
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

/** Send a summary email */
function sendsummaryemail($projectid, $groupid, $errors, $buildid)
{
    include 'config/config.php';
    require_once 'models/userproject.php';
    require_once 'models/user.php';
    require_once 'models/project.php';
    require_once 'models/build.php';
    require_once 'models/site.php';

    $Project = new Project();
    $Project->Id = $projectid;
    $Project->Fill();

    // Check if the email has been sent
    $date = ''; // now
    list($previousdate, $currentstarttime, $nextdate, $today) = get_dates($date, $Project->NightlyTime);
    $dashboarddate = gmdate(FMT_DATE, $currentstarttime);

    // If we already have it we return
    if (pdo_num_rows(pdo_query("SELECT buildid FROM summaryemail WHERE date='$dashboarddate' AND groupid=" . qnum($groupid))) == 1) {
        return;
    }

    // Update the summaryemail table to specify that we have send the email
    // We also delete any previous rows from that groupid
    pdo_query("DELETE FROM summaryemail WHERE groupid=$groupid");
    pdo_query("INSERT INTO summaryemail (buildid,date,groupid) VALUES ($buildid,'$dashboarddate',$groupid)");
    add_last_sql_error('sendmail');

    // If the trigger for SVN/CVS diff is not done yet we specify that the asynchronous trigger should
    // send an email
    $dailyupdatequery = pdo_query('SELECT status FROM dailyupdate WHERE projectid=' . qnum($projectid) . " AND date='$dashboarddate'");
    add_last_sql_error('sendmail');

    if (pdo_num_rows($dailyupdatequery) == 0) {
        return;
    }

    $dailyupdate_array = pdo_fetch_array($dailyupdatequery);
    $dailyupdate_status = $dailyupdate_array['status'];
    if ($dailyupdate_status == 0) {
        pdo_query("UPDATE dailyupdate SET status='2' WHERE projectid=" . qnum($projectid) . " AND date='$dashboarddate'");
        return;
    }

    // Find the current updaters from the night using the dailyupdatefile table
    $summaryEmail = '';
    $query = 'SELECT ' . qid('user') . '.email,up.emailcategory,' . qid('user') . '.id
                          FROM ' . qid('user') . ',user2project AS up,user2repository AS ur,
                           dailyupdate,dailyupdatefile WHERE
                           up.projectid=' . qnum($projectid) . '
                           AND up.userid=' . qid('user') . '.id
                           AND ur.userid=up.userid
                           AND (ur.projectid=0 OR ur.projectid=' . qnum($projectid) . ")
                           AND ur.credential=dailyupdatefile.author
                           AND dailyupdatefile.dailyupdateid=dailyupdate.id
                           AND dailyupdate.date='$dashboarddate'
                           AND dailyupdate.projectid=" . qnum($projectid) . '
                           AND up.emailtype>0
                           ';
    $user = pdo_query($query);
    add_last_sql_error('sendmail');

    // Loop through the users and add them to the email array
    while ($user_array = pdo_fetch_array($user)) {
        // If the user is already in the list we quit
        if (strpos($summaryEmail, $user_array['email']) !== false) {
            continue;
        }

        // If the user doesn't want to receive email
        if (!checkEmailPreferences($user_array['emailcategory'], $errors)) {
            continue;
        }

        // Check if the labels are defined for this user
        if (!checkEmailLabel($projectid, $user_array['id'], $buildid, $user_array['emailcategory'])) {
            continue;
        }

        if ($summaryEmail != '') {
            $summaryEmail .= ', ';
        }
        $summaryEmail .= $user_array['email'];
    }

    // Select the users that are part of this build
    $authors = pdo_query('SELECT author FROM updatefile AS uf,build2update AS b2u
                        WHERE b2u.updateid=uf.updateid AND b2u.buildid=' . qnum($buildid));
    add_last_sql_error('sendmail');
    while ($authors_array = pdo_fetch_array($authors)) {
        $author = $authors_array['author'];
        if ($author == 'Local User') {
            continue;
        }

        $UserProject = new UserProject();
        $UserProject->RepositoryCredential = $author;
        $UserProject->ProjectId = $projectid;

        if (!$UserProject->FillFromRepositoryCredential()) {
            continue;
        }

        // If the user doesn't want to receive email
        if (!checkEmailPreferences($UserProject->EmailCategory, $errors)) {
            continue;
        }

        // Check if the labels are defined for this user
        if (!checkEmailLabel($projectid, $UserProject->UserId, $buildid, $UserProject->EmailCategory)) {
            continue;
        }

        // Find the email
        $User = new User();
        $User->Id = $UserProject->UserId;
        $useremail = $User->GetEmail();

        // If the user is already in the list we quit
        if (strpos($summaryEmail, $useremail) !== false) {
            continue;
        }

        if ($summaryEmail != '') {
            $summaryEmail .= ', ';
        }
        $summaryEmail .= $useremail;
    }

    // In the case of asynchronous submission, the serverURI contains /cdash
    // we need to remove it
    $currentURI = get_server_URI();
    if ($CDASH_BASE_URL == '' && $CDASH_ASYNCHRONOUS_SUBMISSION) {
        $currentURI = substr($currentURI, 0, strrpos($currentURI, '/'));
    }

    // Select the users who want to receive all emails
    $user = pdo_query('SELECT ' . qid('user') . '.email,user2project.emailtype,' . qid('user') . '.id  FROM ' . qid('user') . ',user2project
                     WHERE user2project.projectid=' . qnum($projectid) . '
                     AND user2project.userid=' . qid('user') . '.id AND user2project.emailtype>1');
    add_last_sql_error('sendsummaryemail');
    while ($user_array = pdo_fetch_array($user)) {
        // If the user is already in the list we quit
        if (strpos($summaryEmail, $user_array['email']) !== false) {
            continue;
        }

        // Check if the labels are defined for this user
        if (!checkEmailLabel($projectid, $user_array['id'], $buildid)) {
            continue;
        }

        if ($summaryEmail != '') {
            $summaryEmail .= ', ';
        }
        $summaryEmail .= $user_array['email'];
    }

    // Send the email
    if ($summaryEmail != '') {
        $Build = new Build();
        $Build->FillFromId($buildid);
        $Site = new Site();
        $Site->Id = $Build->SiteId;

        $summaryemail_array = pdo_fetch_array(pdo_query("SELECT name FROM buildgroup WHERE id=$groupid"));
        add_last_sql_error('sendsummaryemail');

        $title = 'CDash [' . $Project->Name . '] - ' . $summaryemail_array['name'] . ' Failures';

        $messagePlainText = 'The "' . $summaryemail_array['name'] . "\" group has either errors, warnings or test failures.\n";
        $messagePlainText .= 'You have been identified as one of the authors who have checked in changes that are part of this submission ';
        $messagePlainText .= "or you are listed in the default contact list.\n\n";

        $messagePlainText .= 'Site name: ' . $Site->GetName() . "\n";
        $messagePlainText .= 'Build name: ' . $Build->Name . ' (' . $Build->Type . ")\n";
        $messagePlainText .= "To see this dashboard:\n";
        $messagePlainText .= $currentURI;
        $messagePlainText .= '/index.php?project=' . urlencode($Project->Name) . '&date=' . $today;
        $messagePlainText .= "\n\n";

        $messagePlainText .= "Summary of the first build failure:\n";
        // Check if an email has been sent already for this user
        foreach ($errors as $errorkey => $nerrors) {
            $messagePlainText .= get_email_summary($buildid, $errors, $errorkey, $Project->EmailMaxItems,
                $Project->EmailMaxChars, $Project->TestTimeMaxStatus,
                $Project->EmailTestTimingChanged);
        }
        $messagePlainText .= "\n\n";

        $serverName = $CDASH_SERVER_NAME;
        if (strlen($serverName) == 0) {
            $serverName = $_SERVER['SERVER_NAME'];
        }

        $messagePlainText .= "\n-CDash on " . $serverName . "\n";

        // If this is the testing
        if ($CDASH_TESTING_MODE) {
            add_log($summaryEmail, 'TESTING: EMAIL', LOG_DEBUG);
            add_log($title, 'TESTING: EMAILTITLE', LOG_DEBUG);
            add_log($messagePlainText, 'TESTING: EMAILBODY', LOG_DEBUG);
        } else {
            // Send the email
            if (cdashmail("$summaryEmail", $title, $messagePlainText)) {
                add_log('summary email sent to: ' . $summaryEmail, 'sendemail ' . $Project->Name, LOG_INFO);
                return;
            } else {
                add_log('cannot send summary email to: ' . $summaryEmail, 'sendemail ' . $Project->Name, LOG_ERR);
            }
        }
    }
}

/** Check if the email has already been sent for that category */
function set_email_sent($userid, $buildid, $emailtext)
{
    foreach ($emailtext['category'] as $key => $value) {
        $category = 0;
        switch ($key) {
            case 'update_errors':
                $category = 1;
                break;
            case 'configure_errors':
                $category = 2;
                break;
            case 'build_warnings':
                $category = 3;
                break;
            case 'build_errors':
                $category = 4;
                break;
            case 'test_errors':
                $category = 5;
                break;
            case 'update_fixes':
                $category = 6;
                break;
            case 'configure_fixes':
                $category = 7;
                break;
            case 'buildwarning_fixes':
                $category = 8;
                break;
            case 'builderror_fixes':
                $category = 9;
                break;
            case 'test_fixes':
                $category = 10;
                break;
            case 'dynamicanalysis_errors':
                $category = 11;
                break;
        }

        if ($category > 0) {
            $today = date(FMT_DATETIME);
            pdo_query('INSERT INTO buildemail (userid,buildid,category,time) VALUES (' . qnum($userid) . ',' . qnum($buildid) . ',' . qnum($category) . ",'" . $today . "')");
            add_last_sql_error('sendmail');
        }
    }
}

/** Check if the email has already been sent for that category */
function check_email_sent($userid, $buildid, $errorkey)
{
    if ($userid == 0) {
        return false;
    }

    $category = 0;
    switch ($errorkey) {
        case 'update_errors':
            $category = 1;
            break;
        case 'configure_errors':
            $category = 2;
            break;
        case 'build_warnings':
            $category = 3;
            break;
        case 'build_errors':
            $category = 4;
            break;
        case 'test_errors':
            $category = 5;
            break;
        case 'update_fixes':
            $category = 6;
            break;
        case 'configure_fixes':
            $category = 7;
            break;
        case 'buildwarning_fixes':
            $category = 8;
            break;
        case 'builderror_fixes':
            $category = 9;
            break;
        case 'test_fixes':
            $category = 10;
            break;
        case 'dynamicanalysis_errors':
            $category = 11;
            break;
    }

    if ($category == 0) {
        return false;
    }

    $query = pdo_query('SELECT count(*) FROM buildemail WHERE userid=' . qnum($userid) . ' AND buildid=' . qnum($buildid) . ' AND category=' . qnum($category));
    $query_array = pdo_fetch_array($query);
    if ($query_array[0] > 0) {
        return true;
    }
    return false;
}

/** Send the email to the user when he fixed something */
function send_email_fix_to_user($userid, $emailtext, $Build, $Project)
{
    include 'config/config.php';
    include_once 'include/common.php';
    require_once 'models/site.php';
    require_once 'models/user.php';

    $serverURI = get_server_URI();
    // In the case of asynchronous submission, the serverURI contains /cdash
    // we need to remove it
    if ($CDASH_BASE_URL == '' && $CDASH_ASYNCHRONOUS_SUBMISSION) {
        $serverURI = substr($serverURI, 0, strrpos($serverURI, '/'));
    }

    $messagePlainText = 'Congratulations, a submission to CDash for the project ' . $Project->Name . ' has ';
    $titleerrors = '(';

    $i = 0;
    foreach ($emailtext['category'] as $key => $value) {
        if ($i > 0) {
            $messagePlainText .= ' and ';
            $titleerrors .= ', ';
        }

        switch ($key) {
            case 'update_fixes':
                $messagePlainText .= 'fixed update errors';
                $titleerrors .= 'u=' . $value;
                break;
            case 'configure_fixes':
                $messagePlainText .= 'fixed configure errors';
                $titleerrors .= 'c=' . $value;
                break;
            case 'buildwarning_fixes':
                $messagePlainText .= 'fixed build warnings';
                $titleerrors .= 'w=' . $value;
                break;
            case 'builderror_fixes':
                $messagePlainText .= 'fixed build errors';
                $titleerrors .= 'b=' . $value;
                break;
            case 'test_fixes':
                $messagePlainText .= 'fixed failing tests';
                $titleerrors .= 't=' . $value;
                break;
        }

        $i++;
    }

    // Title
    $titleerrors .= '):';
    $title = 'PASSED ' . $titleerrors . ' ' . $Project->Name;

    if ($Build->GetSubProjectName()) {
        $title .= '/' . $Build->GetSubProjectName();
    }
    $title .= ' - ' . $Build->Name . ' - ' . $Build->Type;

    $messagePlainText .= ".\n";
    $messagePlainText .= 'You have been identified as one of the authors who have checked in changes that are part of this submission ';
    $messagePlainText .= "or you are listed in the default contact list.\n\n";
    $messagePlainText .= 'Details on the submission can be found at ';

    $messagePlainText .= $serverURI;
    $messagePlainText .= '/buildSummary.php?buildid=' . $Build->Id;
    $messagePlainText .= "\n\n";

    $messagePlainText .= 'Project: ' . $Project->Name . "\n";
    if ($Build->GetSubProjectName()) {
        $messagePlainText .= 'SubProject: ' . $Build->GetSubProjectName() . "\n";
    }

    $Site = new Site();
    $Site->Id = $Build->SiteId;

    $messagePlainText .= 'Site: ' . $Site->GetName() . "\n";
    $messagePlainText .= 'Build Name: ' . $Build->Name . "\n";
    $messagePlainText .= 'Build Time: ' . date(FMT_DATETIMETZ, strtotime($Build->StartTime . ' UTC')) . "\n";
    $messagePlainText .= 'Type: ' . $Build->Type . "\n";

    foreach ($emailtext['category'] as $key => $value) {
        switch ($key) {
            case 'update_fixes':
                $messagePlainText .= 'Update error fixed: ' . $value . "\n";
                break;
            case 'configure_fixes':
                $messagePlainText .= 'Configure error fixed: ' . $value . "\n";
                break;
            case 'buildwarning_fixes':
                $messagePlainText .= 'Warning fixed: ' . $value . "\n";
                break;
            case 'builderror_fixes':
                $messagePlainText .= 'Error fixed: ' . $value . "\n";
                break;
            case 'test_fixes':
                $messagePlainText .= 'Tests fixed: ' . $value . "\n";
                break;
        }
    }

    $serverName = $CDASH_SERVER_NAME;
    if (strlen($serverName) == 0) {
        $serverName = $_SERVER['SERVER_NAME'];
    }
    $messagePlainText .= "\n-CDash on " . $serverName . "\n";

    // Find the email
    $User = new User();
    $User->Id = $userid;
    $email = $User->GetEmail();

    // If this is the testing
    if ($CDASH_TESTING_MODE) {
        add_log($email, 'TESTING: EMAIL', LOG_DEBUG);
        add_log($title, 'TESTING: EMAILTITLE', LOG_DEBUG);
        add_log($messagePlainText, 'TESTING: EMAILBODY', LOG_DEBUG);
        // Record that we have send the email
        set_email_sent($userid, $Build->Id, $emailtext);
    } else {
        // Send the email
        if (cdashmail("$email", $title, $messagePlainText)) {
            add_log('email sent to: ' . $email . ' with fixes ' . $titleerrors . ' for build ' . $Build->Id, 'sendemail ' . $Project->Name, LOG_INFO);

            // Record that we have send the email
            set_email_sent($userid, $Build->Id, $emailtext);
        } else {
            add_log('cannot send email to: ' . $email, 'sendemail ' . $Project->Name, LOG_ERR);
        }
    }
}

// Generate the title and body for a broken build.
//
function generate_broken_build_message($emailtext, $Build, $Project)
{
    include 'config/config.php';
    include_once 'include/common.php';
    require_once 'models/site.php';

    $serverURI = get_server_URI();
    // In the case of asynchronous submission, the serverURI contains /cdash
    // we need to remove it
    if ($CDASH_BASE_URL == '' && $CDASH_ASYNCHRONOUS_SUBMISSION) {
        $serverURI = substr($serverURI, 0, strrpos($serverURI, '/'));
    }

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

    if ($Build->GetSubProjectName()) {
        $title .= '/' . $Build->GetSubProjectName();
    }
    $title .= ' - ' . $Build->Name . ' - ' . $Build->Type;

    $preamble .= ".\n";
    $preamble .= 'You have been identified as one of the authors who ';
    $preamble .= 'have checked in changes that are part of this submission ';
    $preamble .= "or you are listed in the default contact list.\n\n";

    $body = 'Details on the submission can be found at ';

    $body .= $serverURI;
    $body .= '/buildSummary.php?buildid=' . $Build->Id;
    $body .= "\n\n";

    $body .= 'Project: ' . $Project->Name . "\n";
    if ($Build->GetSubProjectName()) {
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

    $serverName = $CDASH_SERVER_NAME;
    if (!$CDASH_TESTING_MODE && strlen($serverName) == 0) {
        $serverName = $_SERVER['SERVER_NAME'];
    }

    $footer = "\n-CDash on " . $serverName . "\n";
    return ['title' => $title, 'preamble' => $preamble, 'body' => $body,
            'footer' => $footer];
}


// Send one broken submission email to one email address
//
function send_email_to_address($emailaddress, $emailtext, $Build, $Project)
{
    include 'config/config.php';

    $message = generate_broken_build_message($emailtext, $Build, $Project);
    if (!$message) {
        return false;
    }

    $title = $message['title'];
    $body = $message['preamble'] . $message['body'] . $message['footer'];

    $sent = false;

    // If this is the testing
    if ($CDASH_TESTING_MODE) {
        add_log($emailaddress, 'TESTING: EMAIL', LOG_DEBUG);
        add_log($title, 'TESTING: EMAILTITLE', LOG_DEBUG);
        add_log($body, 'TESTING: EMAILBODY', LOG_DEBUG);
        $sent = true;
    } else {
        // Send the email
        if (cdashmail("$emailaddress", $title, $body)) {
            add_log('email sent to: ' . $emailaddress . ' with errors ' . $title . ' for build ' . $Build->Id, 'sendemail ' . $Project->Name, LOG_INFO);
            $sent = true;
        } else {
            add_log('cannot send email to: ' . $emailaddress, 'sendemail ' . $Project->Name, LOG_ERR);
        }
    }
    return $sent;
}

function send_email_to_user($userid, $emailtext, $Build, $Project)
{
    require_once 'models/user.php';

    $User = new User();
    $User->Id = $userid;
    $email = $User->GetEmail();

    $sent = send_email_to_address($email, $emailtext, $Build, $Project);
    if ($sent) {
        // Record that we have sent the email
        set_email_sent($userid, $Build->Id, $emailtext);
    }
}

function send_error_email($userid, $emailaddress, $sendEmail, $errors,
                          $Build, $Project, $prefix = 'none')
{
    include 'config/config.php';
    $emailtext = array();
    $emailtext['nerror'] = 0;

    if ($userid != 0) {
        // For registered users, tune the error array based on user preferences
        // to make sure he doesn't get emails that are unwanted/unnecessary
        $UserProject = new UserProject();
        $UserProject->UserId = $userid;
        $UserProject->ProjectId = $Project->Id;
        $useremailcategory = $UserProject->GetEmailCategory();
    }

    // Check if an email has been sent already for this user
    foreach ($errors as $errorkey => $nerrors) {
        if ($nerrors == 0 || $errorkey == 'errors') {
            continue;
        }

        $stop = false;

        if ($userid != 0) {
            // If the user doesn't want to get the email
            switch ($errorkey) {
                case 'update_errors':
                    if (!check_email_category('update', $useremailcategory)) {
                        $stop = true;
                    }
                    break;
                case 'configure_errors':
                    if (!check_email_category('configure', $useremailcategory)) {
                        $stop = true;
                    }
                    break;
                case 'build_errors':
                    if (!check_email_category('error', $useremailcategory)) {
                        $stop = true;
                    }
                    break;
                case 'build_warnings':
                    if (!check_email_category('warning', $useremailcategory)) {
                        $stop = true;
                    }
                    break;
                case 'test_errors':
                    if (!check_email_category('test', $useremailcategory)) {
                        $stop = true;
                    }
                    break;
                case 'dynamicanalysis_errors':
                    if (!check_email_category('dynamicanalysis', $useremailcategory)) {
                        $stop = true;
                    }
                    break;
            }
        } else {
            // For committers, only send emails when the errorkey starts with the
            // prefix associated with the current handler calling us.
            // (So stop if the errorkey does not begin with the prefix...)
            // This minimizes sending out possibly near-duplicate emails to the
            // same committers...
            //
            if (0 !== strpos($errorkey, $prefix)) {
                $stop = true;
            }
        }

        if ($stop) {
            continue;
        }

        if (0 == $userid || !check_email_sent($userid, $Build->Id, $errorkey)) {
            $emailtext['summary'][$errorkey] = get_email_summary($Build->Id, $errors, $errorkey, $Project->EmailMaxItems,
                $Project->EmailMaxChars, $Project->TestTimeMaxStatus,
                $Project->EmailTestTimingChanged);
            $emailtext['category'][$errorkey] = $nerrors;
            $emailtext['nerror'] = 1;
        }
    }

    // Send the email
    if ($emailtext['nerror'] == 1) {
        if ($userid != 0) {
            send_email_to_user($userid, $emailtext, $Build, $Project);

            if ($CDASH_USE_LOCAL_DIRECTORY && file_exists('local/sendemail.php')) {
                $sendEmail->UserId = $userid;
                $sendEmail->Text = $emailtext;
                $sendEmail->SendToUser();
            }
        } else {
            send_email_to_address($emailaddress, $emailtext, $Build, $Project);
            //
            // Do we still need a "$sendEmail->" call here even if
            // there is no UserId...?
            //
        }
    }
}

function getHandlerErrorKeyPrefix($handler)
{
    if ($handler instanceof UpdateHandler) {
        return 'update_';
    }

    if ($handler instanceof TestingHandler) {
        return 'test_';
    }

    if ($handler instanceof BuildHandler) {
        return 'build_';
    }

    if ($handler instanceof ConfigureHandler) {
        return 'configure_';
    }

    if ($handler instanceof DynamicAnalysisHandler) {
        return 'dynamicanalysis_';
    }
    return 'none';
}

/** function to send email to site maintainers when the update
 * step fails */
function send_update_email($handler, $projectid)
{
    include 'config/config.php';
    include_once 'include/common.php';
    require_once 'include/pdo.php';
    require_once 'models/build.php';
    require_once 'models/project.php';
    require_once 'models/buildgroup.php';

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
        $to_address = '';
        $email_addresses =
            pdo_query('SELECT email FROM ' . qid('user') . ',site2user WHERE ' . qid('user') . ".id=site2user.userid AND site2user.siteid='$siteid'");
        while ($email_addresses_array = pdo_fetch_array($email_addresses)) {
            if ($to_address != '') {
                $to_address .= ', ';
            }
            $to_address .= $email_addresses_array['email'];
        }

        if ($to_address != '') {
            $serverURI = get_server_URI();
            // In the case of asynchronous submission, the serverURI contains /cdash
            // we need to remove it
            if ($CDASH_BASE_URL == '' && $CDASH_ASYNCHRONOUS_SUBMISSION) {
                $serverURI = substr($serverURI, 0, strrpos($serverURI, '/'));
            }

            // Generate the email to send
            $subject = 'CDash [' . $Project->Name . '] - Update Errors for ' . $sitename;

            $update_info = pdo_query('SELECT command,status FROM buildupdate AS u,build2update AS b2u
                              WHERE b2u.updateid=u.id AND b2u.buildid=' . qnum($buildid));
            $update_array = pdo_fetch_array($update_info);

            $body = "$sitename has encountered errors during the Update step and you have been identified as the maintainer of this site.\n\n";
            $body .= "*Update Errors*\n";
            $body .= 'Status: ' . $update_array['status'] . ' (' . $serverURI . '/viewUpdate.php?buildid=' . $buildid . ")\n";

            if ($CDASH_TESTING_MODE) {
                add_log($to_address, 'TESTING: EMAIL', LOG_DEBUG);
                add_log($subject, 'TESTING: EMAILTITLE', LOG_DEBUG);
                add_log($body, 'TESTING: EMAILBODY', LOG_DEBUG);
            } else {
                if (cdashmail("$to_address", $subject, $body)) {
                    add_log('email sent to: ' . $to_address, 'sendEmailExpectedBuilds');
                    return;
                } else {
                    add_log('cannot send email to: ' . $to_address, 'sendEmailExpectedBuilds');
                }
            }
        }
    }
}

/** Main function to send email if necessary */
function sendemail($handler, $projectid)
{
    include 'config/config.php';
    include_once 'include/common.php';
    require_once 'include/pdo.php';
    require_once 'models/build.php';
    require_once 'models/project.php';
    require_once 'models/buildgroup.php';

    $Project = new Project();
    $Project->Id = $projectid;
    $Project->Fill();

    $sendEmail = null;

    if ($CDASH_USE_LOCAL_DIRECTORY && file_exists('local/sendemail.php')) {
        include_once 'local/sendemail.php';
        $sendEmail = new SendEmail();
        $sendEmail->SetProjectId($projectid);
    }

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

    //add_log("Buildid ".$buildid,"sendemail ".$Project->Name,LOG_INFO);

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

    $emailCommitters = $BuildGroup->GetEmailCommitters();

    $errors = check_email_errors($buildid, $Project->EmailTestTimingChanged,
        $Project->TestTimeMaxStatus, !$Project->EmailRedundantFailures);

    // We have some fixes
    if ($errors['hasfixes']) {
        $Build->FillFromId($Build->Id);
        // Get the list of person who should get the email
        $lookup_result = lookup_emails_to_send($errors, $buildid, $projectid,
            $Build->Type, true, $emailCommitters);
        $userids = $lookup_result['userids'];
        foreach ($userids as $userid) {
            $emailtext = array();
            $emailtext['nfixes'] = 0;

            // Check if an email has been sent already for this user
            foreach ($errors['fixes'] as $fixkey => $nfixes) {
                if ($nfixes == 0) {
                    continue;
                }

                if (!check_email_sent($userid, $buildid, $fixkey)) {
                    $emailtext['category'][$fixkey] = $nfixes;
                    $emailtext['nfixes'] = 1;
                }
            }

            // Send the email
            if ($emailtext['nfixes'] == 1) {
                send_email_fix_to_user($userid, $emailtext, $Build, $Project);
            }
        }
    }

    // Check for missing tests
    if ($handler instanceof TestingHandler) {
        $Build->FillFromId($Build->Id);
        $missing = $Build->GetNumberOfMissingTests();

        if ($missing > 0) {
            $errors['missing_tests'] = [
                'count' => $missing,
                'list' => $Build->MissingTests
            ];
            $errors['errors'] = true;
        }
    }

    // No error we return
    if (!$errors['errors']) {
        return;
    }

    if ($CDASH_USE_LOCAL_DIRECTORY && file_exists('local/sendemail.php')) {
        $sendEmail->BuildId = $Build->Id;
        $sendEmail->Errors = $errors;
    }

    // If we should send a summary email
    if ($BuildGroup->GetSummaryEmail() == 1) {
        // Send the summary email
        sendsummaryemail($projectid, $groupid, $errors, $buildid);

        if ($CDASH_USE_LOCAL_DIRECTORY && file_exists('local/sendemail.php')) {
            $sendEmail->SendSummary();
        }
        return;
    }

    $Build->FillFromId($Build->Id);

    // Send build error
    if ($CDASH_USE_LOCAL_DIRECTORY && file_exists('local/sendemail.php')) {
        $sendEmail->SendBuildError();
    }

    // Lookup the list of people who should get the email, both registered
    // users *and* committers:
    //
    $lookup_result = lookup_emails_to_send($errors, $buildid, $projectid,
        $Build->Type, false, $emailCommitters);

    // Loop through the *registered* users:
    //
    $userids = $lookup_result['userids'];
    foreach ($userids as $userid) {
        send_error_email($userid, '', $sendEmail, $errors,
            $Build, $Project);
    }

    // Loop through "other" users, if necessary:
    //
    // ...people who committed code, but are *not* registered CDash users, but
    // only if the 'emailcommitters' field is on for this build group.
    //
    if ($emailCommitters) {
        $committeremails = $lookup_result['committeremails'];
        foreach ($committeremails as $committeremail) {
            send_error_email(0, $committeremail, $sendEmail, $errors,
                $Build, $Project, getHandlerErrorKeyPrefix($handler));
        }
    }
}
