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

require_once dirname(__DIR__) . '/config/config.php';
require_once 'include/pdo.php';
include 'public/login.php';
include_once 'include/common.php';
include 'include/version.php';

use CDash\Model\ClientJobSchedule;
use CDash\Model\ClientSite;
use CDash\Model\ClientOS;
use CDash\Model\ClientCMake;
use CDash\Model\ClientCompiler;
use CDash\Model\ClientLibrary;
use CDash\Model\Project;
use CDash\Model\User;
use CDash\Model\UserProject;

if ($session_OK) {
    if (!$CDASH_MANAGE_CLIENTS) {
        echo 'CDash has not been setup to allow client management';
        return;
    }

    $userid = $_SESSION['cdash']['loginid'];
    $User = new User();
    $User->Id = $userid;

    /* If we should remove a job */
    if (isset($_GET['removeschedule'])) {
        $ClientJobSchedule = new ClientJobSchedule();
        $ClientJobSchedule->Id = pdo_real_escape_numeric($_GET['removeschedule']);

        if (!$User->IsAdmin() && $ClientJobSchedule->GetOwner() != $userid) {
            echo 'You cannot access this job';
            return;
        }
        $ClientJobSchedule->Remove();
        echo "<script language=\"javascript\">window.location='user.php'</script>";
    }

    if (!isset($_GET['projectid']) && !isset($_GET['scheduleid'])) {
        echo 'Projectid or Schedule id not set';
        return;
    }

    if (isset($_GET['projectid'])) {
        $projectid = pdo_real_escape_numeric($_GET['projectid']);
    } else {
        $scheduleid = pdo_real_escape_numeric($_GET['scheduleid']);
        $ClientJobSchedule = new ClientJobSchedule();
        $ClientJobSchedule->Id = $scheduleid;
        $projectid = $ClientJobSchedule->GetProjectId();
    }

    if (!$User->IsAdmin()) {
        // Make sure user has project admin privileges to use this page
        $UserProject = new UserProject();
        $UserProject->ProjectId = $projectid;
        $projectAdmins = $UserProject->GetUsers(2); //get project admin users
        if (!in_array($userid, $projectAdmins)) {
            echo 'You are not a project administrator!';
            return;
        }
    }

    $xml = begin_XML_for_XSLT();
    $xml .= add_XML_value('manageclient', $CDASH_MANAGE_CLIENTS);

    $db = pdo_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN", "$CDASH_DB_PASS");
    pdo_select_db("$CDASH_DB_NAME", $db);
    $xml .= add_XML_value('title', 'CDash - Schedule Build');
    $xml .= add_XML_value('menutitle', 'CDash');
    $xml .= add_XML_value('menusubtitle', 'Schedule Build');

    $xml .= '<hostname>' . $_SERVER['SERVER_NAME'] . '</hostname>';
    $xml .= '<date>' . date('r') . '</date>';
    $xml .= '<backurl>user.php</backurl>';

    $userid = $_SESSION['cdash']['loginid'];
    $user = new User();
    $user->Id = $userid;
    $user->Fill();
    $xml .= '<user>';
    $xml .= add_XML_value('id', $userid);
    $xml .= add_XML_value('admin', $user->Admin);
    $xml .= '</user>';

    if (isset($scheduleid)) {
        $xml .= add_XML_value('edit', '1');
        $xml .= add_XML_value('startdate', $ClientJobSchedule->GetStartDate());
        $xml .= add_XML_value('enddate', $ClientJobSchedule->GetEndDate());
        $xml .= add_XML_value('starttime', $ClientJobSchedule->GetStartTime());
        $xml .= add_XML_value('type', $ClientJobSchedule->GetType());
        $xml .= add_XML_value('repeat', $ClientJobSchedule->GetRepeatTime());
        $xml .= add_XML_value('cmakecache', $ClientJobSchedule->GetCMakeCache());
        $xml .= add_XML_value('clientscript', $ClientJobSchedule->GetClientScript());
        $xml .= add_XML_value('enable', $ClientJobSchedule->GetEnable());
        $xml .= add_XML_value('module', $ClientJobSchedule->GetModule());
        $xml .= add_XML_value('tag', $ClientJobSchedule->GetTag());
        $xml .= add_XML_value('buildnamesuffix', $ClientJobSchedule->GetBuildNameSuffix());
        $xml .= add_XML_value('builconfiguration', $ClientJobSchedule->GetBuildConfiguration());
        $xml .= add_XML_value('description', $ClientJobSchedule->GetDescription());
        $libraries = $ClientJobSchedule->GetLibraries();
        $cmakes = $ClientJobSchedule->GetCMakes();
        $compilers = $ClientJobSchedule->GetCompilers();
        $sites = $ClientJobSchedule->GetSites();
        $systems = $ClientJobSchedule->GetSystems();
        $repository = $ClientJobSchedule->GetRepository();

    /*$builds = $ClientJobSchedule->GetAssociatedBuilds();
    foreach($builds as $buildid)
      {
      $xml .= '<build>';
      $xml .= add_XML_value("id", $buildid);
      $xml .= '</build>';
      }*/
    } else {
        $xml .= add_XML_value('startdate', date('Y-m-d H:i:s'));
        $xml .= add_XML_value('enddate', date('1980-01-01 00:00:00'));
        $xml .= add_XML_value('starttime', '21:00:00');
        $xml .= add_XML_value('type', '0'); // experimental
        $xml .= add_XML_value('cmakecache', '');
        $xml .= add_XML_value('description', '');
        $xml .= add_XML_value('clientscript', '');
        $xml .= add_XML_value('repeat', '0');
        $xml .= add_XML_value('enable', '1');
        $xml .= add_XML_value('builconfiguration', '0'); // debug
        $repository = '';
    }

    $inprojectrepository = false;
    $Project = new Project();
    $Project->Id = $projectid;
    $repositories = $Project->GetRepositories();
    $xml .= '<project>';
    $xml .= add_XML_value('name', $Project->getName());
    $xml .= add_XML_value('name_encoded', urlencode($Project->getName()));
    $xml .= add_XML_value('id', $Project->Id);
    foreach ($repositories as $projectrepository) {
        $xml .= '<repository>';
        $xml .= add_XML_value('url', $projectrepository['url']);

        if (isset($scheduleid) && $repository == $projectrepository['url']) {
            $inprojectrepository = true;
            $xml .= add_XML_value('selected', 1);
        }

        $xml .= '</repository>';
    }
    $xml .= '</project>';

    if (isset($scheduleid) && !$inprojectrepository) {
        $xml .= add_XML_value('otherrepository', $repository);
    }

    // Build configurations
    $jobschedule = new ClientJobSchedule();
    foreach ($jobschedule->BuildConfigurations as $key => $value) {
        $xml .= '<buildconfiguration>';
        $xml .= add_XML_value('name', $value);
        $xml .= add_XML_value('id', $key);
        if (isset($scheduleid) && $key == $ClientJobSchedule->GetBuildConfiguration()) {
            $xml .= add_XML_value('selected', '1');
        }
        $xml .= '</buildconfiguration>';
    }

    // OS versions
    $clientOS = new ClientOS();
    $osids = $clientOS->getAll();
    foreach ($osids as $osid) {
        $xml .= '<os>';
        $clientOS->Id = $osid;
        $xml .= add_XML_value('name', $clientOS->GetName() . '-' . $clientOS->GetVersion() . '-' . $clientOS->GetBits() . 'bits');
        $xml .= add_XML_value('id', $osid);
        if (isset($systems) && array_search($osid, $systems) !== false) {
            $xml .= add_XML_value('selected', '1');
        }
        $xml .= '</os>';
    }

    // Compiler versions
    $Compiler = new ClientCompiler();
    $compilerids = $Compiler->getAll();
    foreach ($compilerids as $compilerid) {
        $xml .= '<compiler>';
        $Compiler->Id = $compilerid;
        $xml .= add_XML_value('name', $Compiler->GetName() . '-' . $Compiler->GetVersion());
        $xml .= add_XML_value('id', $compilerid);
        if (isset($compilers) && array_search($compilerid, $compilers) !== false) {
            $xml .= add_XML_value('selected', '1');
        }
        $xml .= '</compiler>';
    }

    // CMake versions
    $CMake = new ClientCMake();
    $cmakeids = $CMake->getAll();
    foreach ($cmakeids as $cmakeid) {
        $xml .= '<cmake>';
        $CMake->Id = $cmakeid;
        $xml .= add_XML_value('version', $CMake->GetVersion());
        $xml .= add_XML_value('id', $cmakeid);
        if (isset($cmakes) && array_search($cmakeid, $cmakes) !== false) {
            $xml .= add_XML_value('selected', '1');
        }
        $xml .= '</cmake>';
    }

    // Sites
    $Site = new ClientSite();
    $siteids = $Site->GetAllForProject($projectid);
    foreach ($siteids as $siteid) {
        $xml .= '<site>';
        $Site->Id = $siteid;

        $lastping = $Site->GetLastPing();
        $time = time() - (5 * 60);
        if (strtotime($lastping) < $time) {
            $lastseen = 0;
        } else {
            $lastseen = 1;
        }

        // Check when the site was last seen
        $lastpingtime = '';
        $diff = time() - strtotime($lastping);
        $days = $diff / (3600 * 24);
        if (floor($days) > 0) {
            $lastpingtime .= floor($days) . ' days';
            $diff = $diff - (floor($days) * 3600 * 24);
        }
        $hours = $diff / (3600);
        if (floor($hours) > 0) {
            if ($lastpingtime != '') {
                $lastpingtime .= ', ';
            }
            $lastpingtime .= floor($hours) . ' hours';
            $diff = $diff - (floor($hours) * 3600);
        }
        $minutes = $diff / (60);
        if ($minutes > 0) {
            if ($lastpingtime != '') {
                $lastpingtime .= ', ';
            }
            $lastpingtime .= floor($minutes) . ' minutes';
        }

        $xml .= add_XML_value('name', $Site->GetName() . '-' . $Site->GetSystemName() . ' (' . $lastpingtime . ' ago)');
        $xml .= add_XML_value('id', $siteid);
        $xml .= add_XML_value('availablenow', $lastseen); // Have we seen it in the past 5 minutes
        if (isset($sites) && array_search($siteid, $sites) !== false) {
            $xml .= add_XML_value('selected', '1');
        }
        $xml .= '</site>';
    }

    // Libraries
    $Library = new ClientLibrary();
    $libraryids = $Library->getAll();

    foreach ($libraryids as $libraryid) {
        $xml .= '<library>';
        $Library->Id = $libraryid;
        $xml .= add_XML_value('name', $Library->GetName() . '-' . $Library->GetVersion());
        $xml .= add_XML_value('id', $libraryid);
        if (isset($libraries) && array_search($libraryid, $libraries) !== false) {
            $xml .= add_XML_value('selected', '1');
        }
        $xml .= '</library>';
    }
    $xml .= '</cdash>';

    // Schedule the build
    if (!empty($_POST['submit']) || !empty($_POST['update'])) {
        $clientJobSchedule = new ClientJobSchedule();
        $clientJobSchedule->UserId = $userid;
        $clientJobSchedule->ProjectId = $Project->Id;
        $clientJobSchedule->BuildNameSuffix = htmlspecialchars(pdo_real_escape_string($_POST['buildnamesuffix']));
        $clientJobSchedule->BuildConfiguration = htmlspecialchars(pdo_real_escape_string($_POST['buildconfiguration']));
        $clientJobSchedule->Tag = htmlspecialchars(pdo_real_escape_string($_POST['tag']));
        $clientJobSchedule->Enable = 1;

        if (strlen($_POST['module']) > 0) {
            $clientJobSchedule->Module = htmlspecialchars(pdo_real_escape_string($_POST['module']));
        }

        if (strlen($_POST['otherrepository']) > 0) {
            $clientJobSchedule->Repository = htmlspecialchars(pdo_real_escape_string($_POST['otherrepository']));
        } else {
            $clientJobSchedule->Repository = htmlspecialchars(pdo_real_escape_string($_POST['repository']));
        }

        if (!isset($_POST['enable'])) {
            $clientJobSchedule->Enable = 0;
        }
        $clientJobSchedule->StartDate = htmlspecialchars(pdo_real_escape_string($_POST['startdate']));
        if (empty($clientJobSchedule->StartDate)) {
            $clientJobSchedule->StartDate = date('Y-m-d H:i:s');
        }
        $clientJobSchedule->EndDate = htmlspecialchars(pdo_real_escape_string($_POST['enddate']));
        if (empty($clientJobSchedule->EndDate)) {
            $clientJobSchedule->EndDate = '1980-01-01 00:00:00';
        }
        $clientJobSchedule->StartTime = htmlspecialchars(pdo_real_escape_string($_POST['starttime']));
        $clientJobSchedule->Type = htmlspecialchars(pdo_real_escape_string($_POST['type']));
        $clientJobSchedule->RepeatTime = htmlspecialchars(pdo_real_escape_string($_POST['repeat']));
        $clientJobSchedule->CMakeCache = stripslashes_if_gpc_magic_quotes($_POST['cmakecache']);
        $clientJobSchedule->Description = stripslashes_if_gpc_magic_quotes($_POST['description']);
        $clientJobSchedule->ClientScript = stripslashes_if_gpc_magic_quotes($_POST['clientscript']);

        if (!empty($_POST['update'])) {
            $clientJobSchedule->Id = $scheduleid;
        }

        $clientJobSchedule->Save();

        // Remove everything and add them back in
        $clientJobSchedule->RemoveDependencies();

        // Add the os
        if (isset($_POST['system'])) {
            foreach ($_POST['system'] as $osid) {
                $clientJobSchedule->AddOS($osid);
            }
        }

        // Add the compiler
        if (isset($_POST['compiler'])) {
            foreach ($_POST['compiler'] as $compilerid) {
                $clientJobSchedule->AddCompiler($compilerid);
            }
        }

        // Add the cmake
        if (isset($_POST['cmake'])) {
            foreach ($_POST['cmake'] as $cmakeid) {
                $clientJobSchedule->AddCMake($cmakeid);
            }
        }

        // Add the site
        if (isset($_POST['site'])) {
            foreach ($_POST['site'] as $siteid) {
                if (in_array($siteid, $siteids)) {
                    $clientJobSchedule->AddSite($siteid);
                }
            }
        }

        // Add the libraries
        if (isset($_POST['library'])) {
            foreach ($_POST['library'] as $libraryid) {
                $clientJobSchedule->AddLibrary($libraryid);
            }
        }

        echo "<script language=\"javascript\">window.location='user.php'</script>";
    }
    generate_XSLT($xml, 'manageClient', true);
}
