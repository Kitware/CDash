<?php 
/*=========================================================================

  Program:   CDash - Cross-Platform Dashboard System
  Module:    $RCSfile: common.php,v $
  Language:  PHP
  Date:      $Date$
  Version:   $Revision$

  Copyright (c) 2002 Kitware, Inc.  All rights reserved.
  See Copyright.txt or http://www.cmake.org/HTML/Copyright.html for details.

     This software is distributed WITHOUT ANY WARRANTY; without even 
     the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR 
     PURPOSE.  See the above copyright notices for more information.

=========================================================================*/

function CreateRSSFeed($projectid)
{
  include('config.php');
  $db = mysql_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN","$CDASH_DB_PASS");
  mysql_select_db("$CDASH_DB_NAME",$db);

  // Find the project name
  $project = mysql_query("SELECT name FROM project WHERE id='$projectid'");
  $project_array = mysql_fetch_array($project);
  $projectname = $project_array["name"];
  
  $serverbase = substr($_SERVER['SCRIPT_FILENAME'],0,strrpos($_SERVER['SCRIPT_FILENAME'],"/"));
  $filename = $serverbase."/rss/SubmissionRSS".$projectname.".xml";

  if (!($fp = fopen($filename, 'w')))
   {
   echo "Cannot write file ".$filename;
   return;
   }
   
  $urlbase = "http://".$_SERVER['HTTP_HOST'].$_SERVER['SCRIPT_NAME'];
  
  fputs($fp,"<?xml version=\"1.0\" encoding=\"ISO-8859-1\" ?>\n");
  fputs($fp,"<rss version=\"2.0\" xmlns:dc=\"http://purl.org/dc/elements/1.1/\">\n");
  fputs($fp,"<channel>\n");
  fputs($fp,"<title>Recent CDash submissions for $projectname</title>\n");
  fputs($fp,"<link>$urlbase/index.php?project=$projectname</link>\n");
  fputs($fp,"<description>CDash for $projectname</description>\n");
  fputs($fp,"<generator>CDash</generator>\n");
  fputs($fp,"<language>en-us</language>\n");
  fputs($fp,"<image>\n");
  fputs($fp," <title>Recent CDash submissions for $projectname</title>\n");
  fputs($fp," <link>$urlbase/index.php?project=$projectname</link>\n");
  fputs($fp," <url>$urlbase/images/cdash.gif</url>\n");
  fputs($fp,"</image>\n");
  $date = date('r');
  fputs($fp,"<lastBuildDate>$date</lastBuildDate>\n");

  // Get the last 24hrs submissions
  $currenttime = time();
  $beginning_timestamp = $currenttime-(24*3600);
  $end_timestamp = $currenttime;
  $builds = mysql_query("SELECT * FROM build 
                         WHERE UNIX_TIMESTAMP(starttime)<$end_timestamp AND UNIX_TIMESTAMP(starttime)>$beginning_timestamp
                         AND projectid='$projectid'
                         ");
  while($build_array = mysql_fetch_array($builds))
    {
    $siteid = $build_array["siteid"];
    $buildid = $build_array["id"];
    $site_array = mysql_fetch_array(mysql_query("SELECT name FROM site WHERE id='$siteid'"));
 
    // Find the number of errors and warnings
    $builderror = mysql_query("SELECT buildid FROM builderror WHERE buildid='$buildid' AND type='0'");
    $nerrors = mysql_num_rows($builderror);
    $buildwarning = mysql_query("SELECT buildid FROM builderror WHERE buildid='$buildid' AND type='1'");
    $nwarnings = mysql_num_rows($buildwarning);
    $nnotrun = mysql_num_rows(mysql_query("SELECT buildid FROM build2test WHERE buildid='$buildid' AND status='notrun'"));
    $nfail = mysql_num_rows(mysql_query("SELECT buildid FROM build2test WHERE buildid='$buildid' AND status='failed'"));
      
    $title = "CDash(".$projectname.") - ".$site_array["name"]." - ".$build_array["name"]." - ".$build_array["type"];
    $title .= " - ".$build_array["submittime"]." - ".$nerrors." errors, ".$nwarnings." warnings, ".$nnotrun." not run, ".$nfail." failed.";
    
    // Should link to the errors...
    $link = $urlbase."/index.php?project=".$projectname;
 
    $description = "A new ".$build_array["type"]." submission from ".$site_array["name"]." - ".$build_array["name"]." is available: ";
    $description .= $nerrors."errors, ".$nwarnings." warnings, ".$nnotrun." not run, ".$nfail."failed.";
 
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
