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
require_once("cdash/common.php");

function CreateRSSFeed($projectid)
{
  // Checks
  if(!isset($projectid) || !is_numeric($projectid))
    {
    echo "Not a valid projectid!";
    return;
    }

  // Find the project name
  $project = pdo_query("SELECT public,name FROM project WHERE id='$projectid'");
  $project_array = pdo_fetch_array($project);
  $projectname = $project_array["name"];

  // Don't create RSS feed for private projects
  if($project_array["public"]!=1)
    {
    return;
    }

  global $CDASH_ROOT_DIR;
  $filename = $CDASH_ROOT_DIR."/rss/SubmissionRSS".$projectname.".xml";

  if (!($fp = fopen($filename, 'w')))
   {
   add_log("CreateRSSFeed", "Cannot write file ".$filename,
     LOG_ERR, $projectid);
   return;
   }

  $currentURI = get_server_URI();

  fputs($fp,"<?xml version=\"1.0\" encoding=\"UTF-8\" ?>\n");
  fputs($fp,"<rss version=\"2.0\" xmlns:dc=\"http://purl.org/dc/elements/1.1/\">\n");
  fputs($fp,"<channel>\n");
  fputs($fp,"<title>Recent CDash submissions for $projectname</title>\n");
  fputs($fp,"<link>$currentURI/index.php?project=$projectname</link>\n");
  fputs($fp,"<description>CDash for $projectname</description>\n");
  fputs($fp,"<generator>CDash</generator>\n");
  fputs($fp,"<language>en-us</language>\n");
  fputs($fp,"<image>\n");
  fputs($fp," <title>Recent CDash submissions for $projectname</title>\n");
  fputs($fp," <link>$currentURI/index.php?project=$projectname</link>\n");
  fputs($fp," <url>$currentURI/images/cdash.gif</url>\n");
  fputs($fp,"</image>\n");
  $date = date('r');
  fputs($fp,"<lastBuildDate>$date</lastBuildDate>\n");

  // Get the last 24hrs submissions
  $currenttime = time();
  $beginning_timestamp = $currenttime-(24*3600);
  $end_timestamp = $currenttime;
  $builds = pdo_query("SELECT * FROM build
                         WHERE UNIX_TIMESTAMP(starttime)<$end_timestamp AND UNIX_TIMESTAMP(starttime)>$beginning_timestamp
                         AND projectid='$projectid'
                         ");
  while($build_array = pdo_fetch_array($builds))
    {
    $siteid = $build_array["siteid"];
    $buildid = $build_array["id"];
    $site_array = pdo_fetch_array(pdo_query("SELECT name FROM site WHERE id='$siteid'"));

    // Find the number of errors and warnings
    $builderror = pdo_query("SELECT buildid FROM builderror WHERE buildid='$buildid' AND type='0'");
    $nerrors = pdo_num_rows($builderror);
    $buildwarning = pdo_query("SELECT buildid FROM builderror WHERE buildid='$buildid' AND type='1'");
    $nwarnings = pdo_num_rows($buildwarning);
    $nnotrun = pdo_num_rows(pdo_query("SELECT buildid FROM build2test WHERE buildid='$buildid' AND status='notrun'"));
    $nfail = pdo_num_rows(pdo_query("SELECT buildid FROM build2test WHERE buildid='$buildid' AND status='failed'"));

    $title = "CDash(".$projectname.") - ".$site_array["name"]." - ".$build_array["name"]." - ".$build_array["type"];
    $title .= " - ".$build_array["submittime"]." - ".$nerrors." errors, ".$nwarnings." warnings, ".$nnotrun." not run, ".$nfail." failed.";

    // Should link to the errors...
    $link = $currentURI."/buildSummary.php?buildid=".$buildid;

    $description = "A new ".$build_array["type"]." submission from ".$site_array["name"]." - ".$build_array["name"]." is available: ";
    $description .= $nerrors." errors, ".$nwarnings." warnings, ".$nnotrun." not run, ".$nfail." failed.";

    $pubDate = date('m/d/y h:i:s A');
    $date = date('m/d/y');

    fputs($fp,"<item>\n");
    fputs($fp,"  <title>$title</title>");
    fputs($fp,"  <link>$link</link>");
    fputs($fp,"  <description>$description</description>\n");
    fputs($fp,"  <pubDate>$pubDate</pubDate>\n");
    fputs($fp,"  <dc:creator>CDash</dc:creator>\n");
    fputs($fp,"  <dc:date>$date</dc:date>\n");
    fputs($fp,"</item>\n");
    }
  fputs($fp, "</channel>\n");
  fputs($fp, "</rss>\n");
  fclose($fp);
}

?>
