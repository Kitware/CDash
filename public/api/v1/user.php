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

include dirname(dirname(dirname(__DIR__))) . '/config/config.php';
require_once 'include/pdo.php';

$noforcelogin = 1;
$SessionCachePolicy = 'nocache';
include 'public/login.php';

include_once 'include/common.php';
redirect_to_https();

include 'include/version.php';

use CDash\Model\Project;
use CDash\Model\AuthToken;
use CDash\Model\ClientJobSchedule;
use CDash\Model\ClientSite;
use CDash\Model\ClientJob;
use CDash\Model\Build;
use CDash\Model\BuildConfigure;
use CDash\Model\Site;
use CDash\Model\User;
use CDash\Model\UserProject;
use CDash\Model\Job;

$response = [];
if (!$session_OK || !isset($_SESSION['cdash']) || !isset($_SESSION['cdash']['loginid'])) {
    $response['requirelogin'] = 1;
    // Special handling for the fact that this is where new users are sent
    // when they first create their account.
    if (@$_GET['note'] == 'register') {
        $response['registered'] = 1;
    } else {
        $response['registered'] = 0;
    }
    http_response_code(401);
    echo json_encode($response);
    return;
}

$script_start_time = microtime_float();
$PDO = get_link_identifier()->getPdo();

$userid = $_SESSION['cdash']['loginid'];
$xml = begin_XML_for_XSLT();
$xml .= add_XML_value('manageclient', $CDASH_MANAGE_CLIENTS);

$userid = $_SESSION['cdash']['loginid'];
$response = begin_JSON_response();
$response['manageclient'] = $CDASH_MANAGE_CLIENTS;
$response['title'] = 'CDash - My Profile';

$user = new User();
$user->Id = $userid;
$user->Fill();
$response['user_name'] = $user->FirstName;
$response['user_is_admin'] = $user->Admin;

if ($CDASH_USER_CREATE_PROJECTS) {
    $response['user_can_create_projects'] = 1;
} else {
    $response['user_can_create_projects'] = 0;
}

// Go through the list of project the user is part of.
$showAuthTokenSection = false;
$UserProject = new UserProject();
$UserProject->UserId = $userid;
$project_rows = $UserProject->GetProjects();
$start = gmdate(FMT_DATETIME, strtotime(date('r')) - (3600 * 24));
$Project = new Project();
$projects_response = [];
foreach ($project_rows as $project_row) {
    if ($project_row['authenticatesubmissions']) {
        $showAuthTokenSection = true;
    }
    $Project->Id = $project_row['id'];
    $Project->Name = $project_row['name'];
    $project_response = [];
    $project_response['id'] = $Project->Id;
    $project_response['role'] = $project_row['role']; // 0 is normal user, 1 is maintainer, 2 is administrator
    $project_response['name'] = $Project->Name;
    $project_response['name_encoded'] = urlencode($Project->Name);
    $project_response['nbuilds'] = $Project->GetTotalNumberOfBuilds();
    $project_response['average_builds'] = round($Project->GetBuildsDailyAverage(gmdate(FMT_DATETIME, time() - (3600 * 24 * 7)), gmdate(FMT_DATETIME), 2));
    $project_response['success'] = $Project->GetNumberOfPassingBuilds($start, gmdate(FMT_DATETIME));
    $project_response['error'] = $Project->GetNumberOfErrorBuilds($start, gmdate(FMT_DATETIME));
    $project_response['warning'] = $Project->GetNumberOfWarningBuilds($start, gmdate(FMT_DATETIME));
    $projects_response[] = $project_response;
}
$response['projects'] = $projects_response;

$authTokens = AuthToken::getTokensForUser($userid);
if (!empty($authTokens)) {
    $showAuthTokenSection = true;
}
$response['authtokens'] = $authTokens;
$response['showauthtokens'] = $showAuthTokenSection;

// Go through the jobs
if ($CDASH_MANAGE_CLIENTS) {
    $ClientJobSchedule = new ClientJobSchedule();
    $userJobSchedules = $ClientJobSchedule->getAll($userid, 1000);
    $schedule_response = [];
    foreach ($userJobSchedules as $scheduleid) {
        $ClientJobSchedule = new ClientJobSchedule();
        $ClientJobSchedule->Id = $scheduleid;
        $projectid = $ClientJobSchedule->GetProjectId();
        $Project = new Project();
        $Project->Id = $projectid;

        $status = 'Scheduled';
        $lastrun = 'NA';

        $lastjobid = $ClientJobSchedule->GetLastJobId();
        if ($lastjobid) {
            $ClientJob = new ClientJob();
            $ClientJob->Id = $lastjobid;
            switch ($ClientJob->GetStatus()) {
                case Job::RUNNING:
                    $status = 'Running';
                    $ClientSite = new ClientSite();
                    $ClientSite->Id = $ClientJob->GetSite();
                    $status .= ' (' . $ClientSite->GetName() . ')';
                    $lastrun = $ClientJob->GetStartDate();
                    break;
                case Job::FINISHED:
                    $status = 'Finished';
                    $ClientSite = new ClientSite();
                    $ClientSite->Id = $ClientJob->GetSite();
                    $status .= ' (' . $ClientSite->GetName() . ')';
                    $lastrun = $ClientJob->GetEndDate();
                    break;
                case Job::FAILED:
                    $status = 'Failed';
                    $ClientSite = new ClientSite();
                    $ClientSite->Id = $ClientJob->GetSite();
                    $status .= ' (' . $ClientSite->GetName() . ')';
                    $lastrun = $ClientJob->GetEndDate();
                    break;
                case Job::ABORTED:
                    $status = 'Aborted';
                    $lastrun = $ClientJob->GetEndDate();
                    break;
            }
        }
        $job_response = [];
        $job_response['id'] = $scheduleid;
        $job_response['projectid'] = $Project->Id;
        $job_response['projectname'] = $Project->GetName();
        $job_response['status'] = $status;
        $job_response['lastrun'] = $lastrun;
        $job_response['description'] = $ClientJobSchedule->GetDescription();
        if (strlen($job_response['description']) === 0) {
            $job_response['description'] = 'NA';
        }
        $schedule_response[] = $job_response;
    }
    $response['jobschedule'] = $schedule_response;
}

// Find all the public projects that this user is not subscribed to.
$stmt = $PDO->prepare(
    'SELECT name, id FROM project
    WHERE public = 1
    AND id NOT IN (SELECT projectid AS id FROM user2project WHERE userid = ?)
    ORDER BY name');
pdo_execute($stmt, [$userid]);

if ($CDASH_USE_LOCAL_DIRECTORY == '1') {
    if (file_exists('local/user.php')) {
        include_once 'local/user.php';
    }
}
$publicprojects_response = [];
while ($row = $stmt->fetch()) {
    $publicproject_response = [];
    $publicproject_response['id'] = $row['id'];
    $publicproject_response['name'] = $row['name'];
    $publicprojects_response[] = $publicproject_response;
}
$response['publicprojects'] = $publicprojects_response;

//Go through the claimed sites
$claimedsiteprojects = [];
$siteidwheresql = '';
$claimedsites = [];
$stmt = $PDO->prepare('SELECT siteid FROM site2user WHERE userid = ?');
pdo_execute($stmt, [$userid]);
while ($row = $stmt->fetch()) {
    $siteid = $row['siteid'];
    $Site = new Site();
    $Site->Id = $siteid;
    $Site->Fill();

    $site_response = [];
    $site_response['id'] = $Site->Id;
    $site_response['name'] = $Site->Name;
    $site_response['outoforder'] = $Site->OutOfOrder;
    $claimedsites[] = $site_response;

    if (strlen($siteidwheresql) > 0) {
        $siteidwheresql .= ' OR ';
    }
    $siteidwheresql .= " siteid='$siteid' ";
}

// Look for all the projects
if (count($claimedsites) > 0) {
    $stmt = $PDO->prepare(
        "SELECT b.projectid FROM build b
        JOIN user2project u2p ON (b.projectid = u2p.projectid)
        WHERE ($siteidwheresql) AND u2p.userid = ? AND u2p.role > 0
        GROUP BY b.projectid");
    pdo_execute($stmt, [$userid]);
    while ($row = $stmt->fetch()) {
        $Project = new Project();
        $Project->Id = $row['projectid'];
        $Project->Fill();

        $claimedproject = [];
        $claimedproject['id'] = $Project->Id;
        $claimedproject['name'] = $Project->Name;
        $claimedproject['nightlytime'] = $Project->NightlyTime;
        $claimedsiteprojects[] = $claimedproject;
    }
}

/** Report statistics about the last build */
function ReportLastBuild($type, $projectid, $siteid, $projectname, $nightlytime)
{
    $response = [];
    $nightlytime = strtotime($nightlytime);

    // Find the last build
    global $PDO;
    $stmt = $PDO->prepare(
        'SELECT starttime, id FROM build
        WHERE siteid = :siteid AND projectid = :projectid AND type = :type
        ORDER BY submittime DESC LIMIT 1');
    $stmt->bindParam(':siteid', $siteid);
    $stmt->bindParam(':projectid', $projectid);
    $stmt->bindParam(':type', $type);
    pdo_execute($stmt);
    $row = $stmt->fetch();
    if ($row) {
        $buildid = $row['id'];

        // Express the date in terms of days (makes more sens)
        $buildtime = strtotime($row['starttime'] . ' UTC');
        $builddate = $buildtime;

        if (date(FMT_TIME, $buildtime) > date(FMT_TIME, $nightlytime)) {
            $builddate += 3600 * 24; //next day
        }

        if (date(FMT_TIME, $nightlytime) < '12:00:00') {
            $builddate -= 3600 * 24; // previous date
        }

        $date = date(FMT_DATE, $builddate);
        $days = ((time() - strtotime($date)) / (3600 * 24));

        if ($days < 1) {
            $day = 'today';
        } elseif ($days > 1 && $days < 2) {
            $day = 'yesterday';
        } else {
            $day = round($days) . ' days';
        }
        $response['date'] = $day;
        $response['datelink'] = 'index.php?project=' . urlencode($projectname) . '&date=' . $date;

        // Configure
        $BuildConfigure = new BuildConfigure();
        $BuildConfigure->BuildId = $buildid;
        $configure_row = $BuildConfigure->GetConfigureForBuild();
        if ($configure_row) {
            $response['configure'] = $configure_row['status'];
            if ($configure_row['status'] != 0) {
                $response['configureclass'] = 'error';
            } else {
                $response['configureclass'] = 'normal';
            }
        } else {
            $response['configure'] = '-';
            $response['configureclass'] = 'normal';
        }

        // Update
        $nupdates = 0;
        $updateclass = 'normal';
        $BuildUpdate = new BuildUpdate();
        $BuildUpdate->BuildId = $buildid;
        $update_row = $BuildUpdate->GetUpdateForBuild();
        if ($update_row) {
            $nupdates = $update_row['nfiles'];
            if ($nupdates < 0) {
                $nupdates = 0;
            }
            if ($update_row['warnings'] > 0) {
                $updateclass = 'error';
            }
        }
        $response['update'] = $nupdates;
        $response['updateclass'] = $updateclass;

        // Find the number of errors and warnings
        $Build = new Build();
        $Build->Id = $buildid;
        $nerrors = $Build->GetNumberOfErrors();
        $response['error'] = $nerrors;
        $nwarnings = $Build->GetNumberOfWarnings();
        $response['warning'] = $nwarnings;

        // Set the color
        if ($nerrors > 0) {
            $response['errorclass'] = 'error';
        } elseif ($nwarnings > 0) {
            $response['errorclass'] = 'warning';
        } else {
            $response['errorclass'] = 'normal';
        }

        // Find the test
        $nnotrun = $Build->GetNumberOfNotRunTests();
        $nfail = $Build->GetNumberOfFailedTests();

        // Display the failing tests then the not run
        if ($nfail > 0) {
            $response['testfail'] = $nfail;
            $response['testfailclass'] = 'error';
        } elseif ($nnotrun > 0) {
            $response['testfail'] = $nnotrun;
            $response['testfailclass'] = 'warning';
        } else {
            $response['testfail'] = '0';
            $response['testfailclass'] = 'normal';
        }
        $response['NA'] = '0';
    } else {
        $response['NA'] = '1';
    }

    return $response;
}

// List the claimed sites
$claimedsites_response = [];
foreach ($claimedsites as $site) {
    $claimedsite_response = [];
    $claimedsite_response['id'] = $site['id'];
    $claimedsite_response['name'] = $site['name'];
    $claimedsite_response['outoforder'] = $site['outoforder'];

    $siteid = $site['id'];

    $siteprojects_response = [];
    foreach ($claimedsiteprojects as $project) {
        $siteproject_response = [];

        $projectid = $project['id'];
        $projectname = $project['name'];
        $nightlytime = $project['nightlytime'];

        $siteproject_response['nightly'] =
            ReportLastBuild('Nightly', $projectid, $siteid, $projectname, $nightlytime);
        $siteproject_response['continuous'] =
            ReportLastBuild('Continuous', $projectid, $siteid, $projectname, $nightlytime);
        $siteproject_response['experimental'] =
            ReportLastBuild('Experimental', $projectid, $siteid, $projectname, $nightlytime);
        $siteprojects_response[] = $siteproject_response;
    }
    $claimedsite_response['projects'] = $siteprojects_response;
    $claimedsites_response[] = $claimedsite_response;
}
$response['claimedsites'] = $claimedsites_response;

// Use to build the site/project matrix
$claimedsiteprojects_response = [];
foreach ($claimedsiteprojects as $project) {
    $claimedsiteproject_response = [];
    $claimedsiteproject_response['id'] = $project['id'];
    $claimedsiteproject_response['name'] = $project['name'];
    $claimedsiteproject_response['name_encoded'] = urlencode($project['name']);
    $claimedsiteprojects_response[] = $claimedsiteproject_response;
}
$response['claimedsiteprojects'] = $claimedsiteprojects_response;

if (@$_GET['note'] == 'subscribedtoproject') {
    $response['message'] = 'You have subscribed to a project.';
} elseif (@$_GET['note'] == 'subscribedtoproject') {
    $response['message'] = 'You have been unsubscribed from a project.';
}

$script_end_time = microtime_float();
$response['generationtime'] = round($script_end_time - $script_start_time, 3);
echo json_encode(cast_data_for_JSON($response));
