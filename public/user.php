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

include dirname(__DIR__) . '/config/config.php';
require_once 'include/pdo.php';
$SessionCachePolicy = 'nocache';
include 'public/login.php';

include_once 'include/common.php';
redirect_to_https();

include 'include/version.php';
include_once 'models/project.php';
include_once 'models/clientjobschedule.php';
include_once 'models/clientsite.php';
include_once 'models/clientjob.php';
include_once 'models/build.php';
include_once 'models/user.php';
include_once 'models/site.php';

if (!$session_OK) {
    return;
}

$userid = $_SESSION['cdash']['loginid'];
$xml = begin_XML_for_XSLT();
$xml .= add_XML_value('manageclient', $CDASH_MANAGE_CLIENTS);

$xml .= add_XML_value('title', 'CDash - My Profile');

$user = new User();
$user->Id = $userid;
$user->Fill();
$xml .= add_XML_value('user_name', $user->FirstName);
$xml .= add_XML_value('user_is_admin', $user->Admin);

if ($CDASH_USER_CREATE_PROJECTS) {
    $xml .= add_XML_value('user_can_create_projects', 1);
} else {
    $xml .= add_XML_value('user_can_create_projects', 0);
}

// Go through the list of project the user is part of.
$UserProject = new UserProject();
$UserProject->UserId = $userid;
$project_rows = $UserProject->GetProjects();
$start = gmdate(FMT_DATETIME, strtotime(date('r')) - (3600 * 24));
$Project = new Project();
foreach ($project_rows as $project_row) {
    $Project->Id = $project_row['id'];
    $Project->Name = $project_row['name'];
    $xml .= '<project>';
    $xml .= add_XML_value('id', $Project->Id);
    $xml .= add_XML_value('role', $project_row['role']); // 0 is normal user, 1 is maintainer, 2 is administrator
    $xml .= add_XML_value('name', $Project->Name);
    $xml .= add_XML_value('name_encoded', urlencode($Project->Name));
    $xml .= add_XML_value('nbuilds', $Project->GetTotalNumberOfBuilds());
    $xml .= add_XML_value('average_builds', round($Project->GetBuildsDailyAverage(gmdate(FMT_DATETIME, time() - (3600 * 24 * 7)), gmdate(FMT_DATETIME), 2)));
    $xml .= add_XML_value('success', $Project->GetNumberOfPassingBuilds($start, gmdate(FMT_DATETIME)));
    $xml .= add_XML_value('error', $Project->GetNumberOfErrorBuilds($start, gmdate(FMT_DATETIME)));
    $xml .= add_XML_value('warning', $Project->GetNumberOfWarningBuilds($start, gmdate(FMT_DATETIME)));
    $xml .= '</project>';
}

// Go through the jobs
if ($CDASH_MANAGE_CLIENTS) {
    $ClientJobSchedule = new ClientJobSchedule();
    $userJobSchedules = $ClientJobSchedule->getAll($userid, 1000);
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
                case CDASH_JOB_RUNNING:
                    $status = 'Running';
                    $ClientSite = new ClientSite();
                    $ClientSite->Id = $ClientJob->GetSite();
                    $status .= ' (' . $ClientSite->GetName() . ')';
                    $lastrun = $ClientJob->GetStartDate();
                    break;
                case CDASH_JOB_FINISHED:
                    $status = 'Finished';
                    $ClientSite = new ClientSite();
                    $ClientSite->Id = $ClientJob->GetSite();
                    $status .= ' (' . $ClientSite->GetName() . ')';
                    $lastrun = $ClientJob->GetEndDate();
                    break;
                case CDASH_JOB_FAILED:
                    $status = 'Failed';
                    $ClientSite = new ClientSite();
                    $ClientSite->Id = $ClientJob->GetSite();
                    $status .= ' (' . $ClientSite->GetName() . ')';
                    $lastrun = $ClientJob->GetEndDate();
                    break;
                case CDASH_JOB_ABORTED:
                    $status = 'Aborted';
                    $lastrun = $ClientJob->GetEndDate();
                    break;
            }
        }

        $xml .= '<jobschedule>';
        $xml .= add_XML_value('id', $scheduleid);
        $xml .= add_XML_value('projectid', $Project->Id);
        $xml .= add_XML_value('projectname', $Project->GetName());
        $xml .= add_XML_value('status', $status);
        $xml .= add_XML_value('lastrun', $lastrun);
        $xml .= add_XML_value('description', $ClientJobSchedule->GetDescription());
        $xml .= '</jobschedule>';
    }
}

// Find all the public projects that this user is not subscribed to.
$pdo = get_link_identifier()->getPdo();
$stmt = $pdo->prepare(
    'SELECT name, id FROM project
    WHERE public = 1
    AND id NOT IN (SELECT projectid AS id FROM user2project WHERE userid = ?)
    ORDER BY name');
pdo_execute($stmt, [$userid]);

$j = 0;
if ($CDASH_USE_LOCAL_DIRECTORY == '1') {
    if (file_exists('local/user.php')) {
        include_once 'local/user.php';
    }
}
while ($row = $stmt->fetch()) {
    $xml .= '<publicproject>';
    if ($j % 2 == 0) {
        $xml .= add_XML_value('trparity', 'trodd');
    } else {
        $xml .= add_XML_value('trparity', 'treven');
    }
    if (function_exists('getAdditionalPublicProject')) {
        $xml .= getAdditionalPublicProject($row['id']);
    }
    $xml .= add_XML_value('id', $row['id']);
    $xml .= add_XML_value('name', $row['name']);
    $xml .= '</publicproject>';
    $j++;
}

//Go through the claimed sites
$claimedsiteprojects = [];
$siteidwheresql = '';
$claimedsites = [];
$stmt = $pdo->prepare('SELECT siteid FROM site2user WHERE userid = ?');
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
if (pdo_num_rows($site2user) > 0) {
    $site2project = pdo_query("SELECT build.projectid FROM build,user2project WHERE ($siteidwheresql)
                 AND user2project.projectid=build.projectid AND user2project.userid='$userid'
                 AND user2project.role>0
                 GROUP BY build.projectid");
    while ($site2project_array = pdo_fetch_array($site2project)) {
        $projectid = $site2project_array['projectid'];
        $project_array = pdo_fetch_array(pdo_query("SELECT name,nightlytime FROM project WHERE id='$projectid'"));
        $claimedproject = array();
        $claimedproject['id'] = $projectid;
        $claimedproject['name'] = $project_array['name'];
        $claimedproject['nightlytime'] = $project_array['nightlytime'];
        $claimedsiteprojects[] = $claimedproject;
    }
}

/** Report statistics about the last build */
function ReportLastBuild($type, $projectid, $siteid, $projectname, $nightlytime)
{
    $xml = '<' . strtolower($type) . '>';
    $nightlytime = strtotime($nightlytime);

    // Find the last build
    $build = pdo_query("SELECT starttime,id FROM build WHERE siteid='$siteid' AND projectid='$projectid' AND type='$type' ORDER BY submittime DESC LIMIT 1");
    if (pdo_num_rows($build) > 0) {
        $build_array = pdo_fetch_array($build);
        $buildid = $build_array['id'];

        // Express the date in terms of days (makes more sens)
        $buildtime = strtotime($build_array['starttime'] . ' UTC');
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
        $xml .= add_XML_value('date', $day);
        $xml .= add_XML_value('datelink', 'index.php?project=' . urlencode($projectname) . '&date=' . $date);

        // Configure
        $configure = pdo_query(
                "SELECT status FROM configure c
                JOIN build2configure b2c ON b2c.configureid=c.id
                WHERE buildid='$buildid'");
        if (pdo_num_rows($configure) > 0) {
            $configure_array = pdo_fetch_array($configure);
            $xml .= add_XML_value('configure', $configure_array['status']);
            if ($configure_array['status'] != 0) {
                $xml .= add_XML_value('configureclass', 'error');
            } else {
                $xml .= add_XML_value('configureclass', 'normal');
            }
        } else {
            $xml .= add_XML_value('configure', '-');
            $xml .= add_XML_value('configureclass', 'normal');
        }

        // Update
        $update = pdo_query('SELECT uf.updateid FROM updatefile AS uf,build2update AS b2u WHERE uf.updateid=b2u.updateid AND b2u.buildid=' . $buildid);
        $nupdates = pdo_num_rows($update);
        $xml .= add_XML_value('update', $nupdates);

        // Find locally modified files
        $updatelocal = pdo_query('SELECT uf.updateid FROM updatefile AS uf,build2update AS b2u WHERE uf.updateid=b2u.updateid AND b2u.buildid=' . $buildid .
            " AND uf.author='Local User'");

        // Set the color
        if (pdo_num_rows($updatelocal) > 0) {
            $xml .= add_XML_value('updateclass', 'error');
        } else {
            $xml .= add_XML_value('updateclass', 'normal');
        }

        // Find the number of errors and warnings
        $Build = new Build();
        $Build->Id = $buildid;
        $nerrors = $Build->GetNumberOfErrors();
        $xml .= add_XML_value('error', $nerrors);
        $nwarnings = $Build->GetNumberOfWarnings();
        $xml .= add_XML_value('warning', $nwarnings);

        // Set the color
        if ($nerrors > 0) {
            $xml .= add_XML_value('errorclass', 'error');
        } elseif ($nwarnings > 0) {
            $xml .= add_XML_value('errorclass', 'warning');
        } else {
            $xml .= add_XML_value('errorclass', 'normal');
        }

        // Find the test
        $nnotrun = $Build->GetNumberOfNotRunTests();
        $nfail = $Build->GetNumberOfFailedTests();

        // Display the failing tests then the not run
        if ($nfail > 0) {
            $xml .= add_XML_value('testfail', $nfail);
            $xml .= add_XML_value('testfailclass', 'error');
        } elseif ($nnotrun > 0) {
            $xml .= add_XML_value('testfail', $nnotrun);
            $xml .= add_XML_value('testfailclass', 'warning');
        } else {
            $xml .= add_XML_value('testfail', '0');
            $xml .= add_XML_value('testfailclass', 'normal');
        }
        $xml .= add_XML_value('NA', '0');
    } else {
        $xml .= add_XML_value('NA', '1');
    }

    $xml .= '</' . strtolower($type) . '>';
    return $xml;
}

// List the claimed sites
foreach ($claimedsites as $site) {
    $xml .= '<claimedsite>';
    $xml .= add_XML_value('id', $site['id']);
    $xml .= add_XML_value('name', $site['name']);
    $xml .= add_XML_value('outoforder', $site['outoforder']);

    $siteid = $site['id'];

    foreach ($claimedsiteprojects as $project) {
        $xml .= '<project>';

        $projectid = $project['id'];
        $projectname = $project['name'];
        $nightlytime = $project['nightlytime'];

        $xml .= ReportLastBuild('Nightly', $projectid, $siteid, $projectname, $nightlytime);
        $xml .= ReportLastBuild('Continuous', $projectid, $siteid, $projectname, $nightlytime);
        $xml .= ReportLastBuild('Experimental', $projectid, $siteid, $projectname, $nightlytime);

        $xml .= '</project>';
    }

    $xml .= '</claimedsite>';
}

// Use to build the site/project matrix
foreach ($claimedsiteprojects as $project) {
    $xml .= '<claimedsiteproject>';
    $xml .= add_XML_value('id', $project['id']);
    $xml .= add_XML_value('name', $project['name']);
    $xml .= add_XML_value('name_encoded', urlencode($project['name']));
    $xml .= '</claimedsiteproject>';
}

if (@$_GET['note'] == 'subscribedtoproject') {
    $xml .= '<message>You have subscribed to a project.</message>';
} elseif (@$_GET['note'] == 'subscribedtoproject') {
    $xml .= '<message>You have been unsubscribed from a project.</message>';
}

$xml .= '</cdash>';

// Now doing the xslt transition
if (!isset($NoXSLGenerate)) {
    generate_XSLT($xml, 'user');
}
