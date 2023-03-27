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

namespace CDash\Api\v1\User;

require_once 'include/pdo.php';
include_once 'include/common.php';

// TODO: dunno what this is or why it seems to exist here and there willy nilly, RWEP.
redirect_to_https();

use App\Services\AuthTokenService;
use App\Services\PageTimer;
use CDash\Config;
use CDash\Database;
use CDash\Model\Project;
use CDash\Model\Build;
use CDash\Model\BuildConfigure;
use CDash\Model\BuildUpdate;
use CDash\Model\Site;
use CDash\Model\UserProject;
use Illuminate\Support\Facades\Auth;

$config = Config::getInstance();
$response = [];
if (!Auth::check()) {
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

$pageTimer = new PageTimer();

/** @var \App\Models\User $user */
$user = Auth::user();
$userid = $user->id;

$PDO = Database::getInstance()->getPdo();

$xml = begin_XML_for_XSLT();

$response = begin_JSON_response();
$response['title'] = 'CDash - My Profile';

$response['user_name'] = $user->firstname;
$response['user_is_admin'] = $user->admin;

if ($config->get('CDASH_USER_CREATE_PROJECTS')) {
    $response['user_can_create_projects'] = 1;
} else {
    $response['user_can_create_projects'] = 0;
}

// Go through the list of project the user is part of.
$UserProject = new UserProject();
$UserProject->UserId = $userid;
$project_rows = $UserProject->GetProjects();
$start = gmdate(FMT_DATETIME, strtotime(date('r')) - (3600 * 24));
$Project = new Project();
$projects_response = [];
foreach ($project_rows as $project_row) {
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

$response['authtokens'] = AuthTokenService::getTokensForUser($userid);
$response['allow_full_access_tokens'] = config('cdash.allow_full_access_tokens') === true;
$response['allow_submit_only_tokens'] = config('cdash.allow_submit_only_tokens') === true;

// Find all the public projects that this user is not subscribed to.
$stmt = $PDO->prepare(
    'SELECT name, id FROM project
    WHERE public = 1
    AND id NOT IN (SELECT projectid AS id FROM user2project WHERE userid = ?)
    ORDER BY name');
pdo_execute($stmt, [$userid]);

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
function ReportLastBuild($type, $projectid, $siteid, $projectname, $nightlytime, $PDO)
{
    $response = [];
    $nightlytime = strtotime($nightlytime);

    // Find the last build
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
            ReportLastBuild('Nightly', $projectid, $siteid, $projectname, $nightlytime, $PDO);
        $siteproject_response['continuous'] =
            ReportLastBuild('Continuous', $projectid, $siteid, $projectname, $nightlytime, $PDO);
        $siteproject_response['experimental'] =
            ReportLastBuild('Experimental', $projectid, $siteid, $projectname, $nightlytime, $PDO);
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

$pageTimer->end($response);
echo json_encode(cast_data_for_JSON($response));
