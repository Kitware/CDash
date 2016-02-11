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
require_once("include/common.php");

use \Suin\RSSWriter\Channel;
use \Suin\RSSWriter\Feed;
use \Suin\RSSWriter\Item;

function CreateRSSFeed($projectid)
{
    // Checks
    if (!isset($projectid) || !is_numeric($projectid)) {
        echo "Not a valid projectid!";
        return;
    }

    // Find the project name
    $project = pdo_query("SELECT public,name FROM project WHERE id='$projectid'");
    $project_array = pdo_fetch_array($project);
    $projectname = $project_array["name"];

    // Don't create RSS feed for private projects
    if ($project_array["public"]!=1) {
        return;
    }

    global $CDASH_ROOT_DIR;
    $filename = $CDASH_ROOT_DIR.'/public/rss/SubmissionRSS'.$projectname.'.xml';

    $currentURI = get_server_URI();
    $currenttime = time();

    $feed = new Feed();
    $channel = new Channel();
    $channel->title("CDash for $projectname")
        ->url("$currentURI/index.php?project=$projectname")
        ->description("Recent CDash submissions for $projectname")
        ->language("en-US")
        ->lastBuildDate($currenttime)
        ->appendTo($feed);

    // Get the last 24hrs submissions
    $beginning_timestamp = $currenttime-(24*3600);
    $end_timestamp = $currenttime;
    $builds = pdo_query("SELECT * FROM build
                         WHERE UNIX_TIMESTAMP(starttime)<$end_timestamp AND UNIX_TIMESTAMP(starttime)>$beginning_timestamp
                         AND projectid='$projectid'
                         ");
    while ($build_array = pdo_fetch_array($builds)) {
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

        $item = new Item();
        $item->guid($currentURI."/buildSummary.php?buildid=".$buildid)
            ->title($title)
            ->url($link)
            ->description($description)
            ->pubDate($currenttime)
            ->appendTo($channel);
    }

    if (file_put_contents($filename, $feed) === false) {
        add_log('CreateRSSFeed', 'Cannot write file '.$filename, LOG_ERR, $projectid);
    }
}
