<?php
/*=========================================================================

  Program:   CDash - Cross-Platform Dashboard System
  Module:    $Id$
  Language:  PHP
  Date:      $Date$
  Version:   $Revision$

  Copyright (c) 2002 Kitware, Inc.  All rights reserved.
  See Copyright.txt or http://www.cmake.org/HTML/Copyright.html for details.

     This software is distributed WITHOUT ANY WARRANTY; without even
     the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR
     PURPOSE.  See the above copyright notices for more information.

=========================================================================*/
include("cdash/config.php");
require_once("cdash/pdo.php");
include('login.php');
include_once("cdash/common.php");
include("cdash/version.php");
include("models/project.php");
include("models/user.php");
include_once("models/errorlog.php");

if ($session_OK) {
    @$buildid = $_GET["buildid"];
    if ($buildid != null) {
        $buildid = pdo_real_escape_numeric($buildid);
    }

    @$projectid = $_GET["projectid"];
    if ($projectid != null) {
        $projectid = pdo_real_escape_numeric($projectid);
    }

    @$date = $_GET["date"];
    if ($date != null) {
        $date = htmlspecialchars(pdo_real_escape_string($date));
    }

// Checks if the project id is set
if (!isset($projectid) || !is_numeric($projectid)) {
    checkUserPolicy(@$_SESSION['cdash']['loginid'], 0);
} else {
    checkUserPolicy(@$_SESSION['cdash']['loginid'], $projectid);
}

    $db = pdo_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN", "$CDASH_DB_PASS");
    pdo_select_db("$CDASH_DB_NAME", $db);

    $userid = $_SESSION['cdash']['loginid'];
    $User = new User;
    $User->Id = $userid;
  
    $Project = new Project;
    $role = 0;

    if ($projectid) {
        $project = pdo_query("SELECT name FROM project WHERE id='$projectid'");
        if (pdo_num_rows($project)>0) {
            $project_array = pdo_fetch_array($project);
            $projectname = $project_array["name"];
        }
        $Project->Id = $projectid;
        $role = $Project->GetUserRole($userid);
    } else {
        $projectname = 'Global';
    }

// If we should delete the log
if (($User->IsAdmin() || $role>1) && isset($_POST["deletelogs"])) {
    $ErrorLog = new ErrorLog();
    $ErrorLog->Clean(0, $projectid);
} elseif (isset($_POST["deletelogs"])) {
    echo "You don't have the privileges to delete these logs.";
    exit();
}

    $xml = begin_XML_for_XSLT();
    $xml .= "<title>Error Log - ".$projectname."</title>";

    if ($buildid) {
        $xml .= get_cdash_dashboard_xml(get_project_name($projectid), $date);
  // Get the errors
  $query = pdo_query("SELECT resourcetype,date,resourceid,description,type,buildid,projectid
                     FROM errorlog WHERE projectid=".qnum($projectid)." AND buildid=".qnum($buildid)." ORDER BY date DESC");
    } elseif ($projectid) {
        $xml .= get_cdash_dashboard_xml(get_project_name($projectid), $date);

        $sql = '';
        if ($date) {
            $sql = "AND date>'".$date."'";
        }
  // Get the errors
  $query = pdo_query("SELECT resourcetype,date,resourceid,description,type,buildid,projectid
                     FROM errorlog WHERE projectid=".qnum($projectid).$sql." ORDER BY date DESC");
    } else {
        $query = pdo_query("SELECT resourcetype,date,resourceid,errorlog.description,type,buildid,projectid,project.name AS projectname
                     FROM errorlog LEFT JOIN project ON (project.id=errorlog.projectid) ORDER BY date DESC");
        echo pdo_error();
    }

    while ($query_array = pdo_fetch_array($query)) {
        $xml .= "<error>";
        $xml .= add_XML_value("date", $query_array["date"]);
        $xml .= add_XML_value("resourceid", $query_array["resourceid"]);
        $xml .= add_XML_value("resourcetype", $query_array["resourcetype"]);
        $xml .= add_XML_value("description", $query_array["description"]);
        $xml .= add_XML_value("type", $query_array["type"]);
        $xml .= add_XML_value("buildid", $query_array["buildid"]);
        $xml .= add_XML_value("projectid", $query_array["projectid"]);

        if (isset($query_array["projectname"])) {
            $xml .= add_XML_value("projectname", $query_array["projectname"]);
        }

        $xml .= "</error>";
    }

    $xml .= add_XML_value("admin", $User->IsAdmin());
    $xml .= add_XML_value("role", $role);

    $xml .= "</cdash>";

// Now doing the xslt transition
generate_XSLT($xml, "viewErrorLog");
} //endif session OK;

