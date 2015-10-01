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
include("cdash/version.php");
require_once("cdash/common.php");

set_time_limit(0);

$db = pdo_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN", "$CDASH_DB_PASS");
if (!$db) {
    echo pdo_error();
}
if (pdo_select_db("$CDASH_DB_NAME", $db) === false) {
    echo pdo_error();
    return;
}

checkUserPolicy(@$_SESSION['cdash']['loginid'], 0); // only admin

@$projectid = $_GET["projectid"];
if ($projectid != null) {
    $projectid = pdo_real_escape_numeric($projectid);
}

$xml = begin_XML_for_XSLT();

//get date info here
@$dayTo = pdo_real_escape_numeric($_POST["dayFrom"]);
if (empty($dayTo)) {
    $time = strtotime("2000-01-01 00:00:00");

    if (isset($projectid)) {
        // find the first and last builds

    $sql = "SELECT starttime FROM build WHERE projectid=".qnum($projectid)." ORDER BY starttime ASC LIMIT 1";
        $startttime = pdo_query($sql);
        if ($startttime_array = pdo_fetch_array($startttime)) {
            $time = strtotime($startttime_array['starttime']);
        }
    }
    $dayFrom = date('d', $time);
    $monthFrom = date('m', $time);
    $yearFrom = date('Y', $time);
    $dayTo = date('d');
    $yearTo = date('Y');
    $monthTo = date('m');
} else {
    $dayFrom = pdo_real_escape_numeric($_POST["dayFrom"]);
    $monthFrom = pdo_real_escape_numeric($_POST["monthFrom"]);
    $yearFrom = pdo_real_escape_numeric($_POST["yearFrom"]);
    $dayTo = pdo_real_escape_numeric($_POST["dayTo"]);
    $monthTo = pdo_real_escape_numeric($_POST["monthTo"]);
    $yearTo = pdo_real_escape_numeric($_POST["yearTo"]);
}

$xml = "<cdash>";
$xml .= "<cssfile>".$CDASH_CSS_FILE."</cssfile>";
$xml .= "<version>".$CDASH_VERSION."</version>";
$xml .= "<title>CDash - Remove Builds</title>";
$xml .= "<menutitle>CDash</menutitle>";
$xml .= "<menusubtitle>Remove Builds</menusubtitle>";
$xml .= "<backurl>manageBackup.php</backurl>";

// List the available projects
$sql = "SELECT id,name FROM project";
$projects = pdo_query($sql);
while ($projects_array = pdo_fetch_array($projects)) {
    $xml .= "<availableproject>";
    $xml .= add_XML_value("id", $projects_array['id']);
    $xml .= add_XML_value("name", $projects_array['name']);
    if ($projects_array['id']==$projectid) {
        $xml .= add_XML_value("selected", "1");
    }
    $xml .= "</availableproject>";
}

$xml .= "<dayFrom>".$dayFrom."</dayFrom>";
$xml .= "<monthFrom>".$monthFrom."</monthFrom>";
$xml .= "<yearFrom>".$yearFrom."</yearFrom>";
$xml .= "<dayTo>".$dayTo."</dayTo>";
$xml .= "<monthTo>".$monthTo."</monthTo>";
$xml .= "<yearTo>".$yearTo."</yearTo>";

@$submit = $_POST["Submit"];

// Delete the builds
if (isset($submit)) {
    $begin = $yearFrom."-".$monthFrom."-".$dayFrom." 00:00:00";
    $end = $yearTo."-".$monthTo."-".$dayTo." 00:00:00";
    $sql = "SELECT id FROM build WHERE projectid=".qnum($projectid)." AND starttime<='$end' AND starttime>='$begin' ORDER BY starttime ASC";

    $build = pdo_query($sql);

    $builds = array();
    while ($build_array = pdo_fetch_array($build)) {
        $builds[] = $build_array['id'];
    }

    remove_build($builds);
    $xml .= add_XML_value("alert", "Removed ".count($builds)." builds.");
}

$xml .= "</cdash>";
generate_XSLT($xml, "removeBuilds");
