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
// get_related_dates takes a projectname and basedate as input
// and produces an array of related dates and times based on:
// the input, the project's nightly start time, now
//

include("cdash/config.php");
require_once("cdash/pdo.php");
include_once("cdash/common.php");

set_time_limit(0);

function get_related_dates($projectnightlytime, $basedate)
{
  $dates = array();
  $nightlytime = $projectnightlytime;

  if(!isset($basedate) || strlen($basedate)==0)
    {
    $basedate = gmdate(FMT_DATE);
    }

  // Convert the nightly time into GMT
  $nightlytime = gmdate(FMT_TIME,strtotime($nightlytime)); 

  $nightlyhour = time2hour($nightlytime);
  $nightlyminute = time2minute($nightlytime);
  $nightlysecond = time2second($nightlytime);
  $basemonth = date2month($basedate);
  $baseday = date2day($basedate);
  $baseyear = date2year($basedate);

  $dates['nightly+2'] = gmmktime($nightlyhour, $nightlyminute, $nightlysecond,
    $basemonth, $baseday+2, $baseyear);
  $dates['nightly+1'] = gmmktime($nightlyhour, $nightlyminute, $nightlysecond,
    $basemonth, $baseday+1, $baseyear);
  $dates['nightly-0'] = gmmktime($nightlyhour, $nightlyminute, $nightlysecond,
    $basemonth, $baseday, $baseyear);
  $dates['nightly-1'] = gmmktime($nightlyhour, $nightlyminute, $nightlysecond,
    $basemonth, $baseday-1, $baseyear);
  $dates['nightly-2'] = gmmktime($nightlyhour, $nightlyminute, $nightlysecond,
    $basemonth, $baseday-2, $baseyear);

  // Snapshot of "now"
  //
  $currentgmtime = time();
  $currentgmdate = gmdate(FMT_DATE, $currentgmtime);

  // Find the most recently past nightly time:
  //
  $todaymonth = date2month($currentgmdate);
  $todayday = date2day($currentgmdate);
  $todayyear = date2year($currentgmdate);
  $currentnightly = gmmktime($nightlyhour, $nightlyminute, $nightlysecond,
    $todaymonth, $todayday, $todayyear);
  while ($currentnightly>$currentgmtime)
    {
    $todayday = $todayday - 1;
    $currentnightly = gmmktime($nightlyhour, $nightlyminute, $nightlysecond,
      $todaymonth, $todayday, $todayyear);
    }

  $dates['now'] = $currentgmtime;
  $dates['most-recent-nightly'] = $currentnightly;
  $dates['today_utc'] = $currentgmdate;
  $dates['basedate'] = gmdate(FMT_DATE, $dates['nightly-0']);

  // CDash equivalent of DART1's "last rollup time"
  if ($dates['basedate'] === $dates['today_utc'])
    {
    // If it's today, it's now:
    $dates['last-rollup-time'] = $dates['now'];
    }
  else
   {
    // If it's not today, it's the nightly time on the basedate:
    $dates['last-rollup-time'] = $dates['nightly-0'];
    }

  return $dates;
}


/** */
function remove_directory_from_filename(&$filename)
{
  $npos = strrpos($filename, "/");

  if ($npos === FALSE || $npos === 0)
  {
    $dir = ".";
  }
  else
  {
    $dir = substr($filename, 0, $npos);
    $filename = substr($filename, $npos+1);
  }

  return $dir;
}


// If the string $root begins with one of the known cvs protocol
// indicators, then return TRUE. Otherwise, return FALSE.
//
function is_cvs_root($root)
{
  $npos = strpos($root, ":pserver:");
  if ($npos !== FALSE && $npos === 0)
  {
    return TRUE;
  }

  $npos = strpos($root, ":ext:");
  if ($npos !== FALSE && $npos === 0)
  {
    return TRUE;
  }

  return FALSE;
}

/** */
function get_cvs_repository_commits($cvsroot, $dates)
{
  $commits = array();

  // Compute time stamp range expressed as $fromtime and $totime for cvs
  //
  $fromtime = gmdate(FMT_DATETIMESTD, $dates['nightly-1']+1) . " GMT";
  $totime = gmdate(FMT_DATETIMESTD, $dates['nightly-0']) . " GMT";

  $npos = strpos($cvsroot, "/");
  $npos2 = strlen($cvsroot);
  $module = substr($cvsroot, $npos + strlen("/cvsroot/"));
  $Idbase = substr($cvsroot, $npos, $npos2 - $npos) . '/'; // . $module . '/';

  // Do a shell_exec of a cvs rlog call to get the changes in the requested
  // date range:
  //
  $raw_output = `cvs -d $cvsroot rlog -S -N -d "$fromtime<$totime" $module 2>&1`;

  // Process as an array of lines:
  //
  $lines = explode("\n", $raw_output);

  // Compute summary data:
  //
  $current_author = "";
  $current_comment = "";
  $current_directory = "";
  $current_filename = "";
  $current_revision = "";
  $current_time = 0;

  $line_number = 0;
  $in_revision_chunk = 0;
  $in_revision_chunk_line_number = 0;
  $total_revisions = 0;

  foreach($lines as $vv)
    {
    $num_revisions = 0;
    $line_number = $line_number + 1;

    $npos = strpos($vv, "--------------------");
    if ($npos !== FALSE && $npos === 0)
      {
      if ($in_revision_chunk === 1)
        {
        $commit = array();
        $commit['directory'] = $current_directory;
        $commit['filename'] = $current_filename;
        $commit['revision'] = $current_revision;
        $commit['priorrevision'] = "";
        $commit['time'] = $current_time;
        $commit['author'] = $current_author;
        $commit['comment'] = $current_comment;
        $commits[$current_directory . "/" . $current_filename . ";" . $current_revision] = $commit;
        }

      $current_comment = "";
      $in_revision_chunk = 1;
      $in_revision_chunk_line_number = $line_number;

      $total_revisions = $total_revisions + 1;
      }  

    if ($in_revision_chunk === 0)
      {
      $npos = strpos($vv, "RCS file: " . $Idbase);
      if ($npos !== FALSE && $npos === 0)
        {
        $npos = strlen("RCS file: " . $Idbase);
        $npos2 = strlen($vv) - 2; // 2 == strlen(",v") at the end of the "RCS file:" line

        $current_filename = substr($vv, $npos, $npos2 - $npos);
       
        // We need to remove the current directory
        // which is the directory of the project
        $p = strpos($current_filename,"/");
        if($p !== FALSE)
          {
          $current_filename = substr($current_filename,$p+1);
          } 

        $current_directory = remove_directory_from_filename($current_filename);
        }
      }

    if ($in_revision_chunk === 1)
      {
      // $in_revision_chunk_line_number + 1
      $npos = strpos($vv, "revision ");
      if ($npos !== FALSE && $npos === 0 && $line_number === $in_revision_chunk_line_number + 1)
        {
        $npos = strlen("revision ");
        $npos2 = strlen($vv);
        $current_revision = substr($vv, $npos, $npos2 - $npos);
        }

      // $in_revision_chunk_line_number + 2
      $npos = strpos($vv, "date: ");
      if ($npos !== FALSE && $npos === 0 && $line_number === $in_revision_chunk_line_number + 2)
        {
        $npos2 = strpos($vv, "; ", $npos);
        $current_time = gmdate(FMT_DATETIME,strtotime(substr($vv, $npos + 6, $npos2 - ($npos + 6)))); // 6 == strlen("date: ")

        // Lines that begin with "date: " also contain "author: "
        //
        $npos = strpos($vv, "author: ");
        if ($npos !== FALSE)
        {
          $npos2 = strpos($vv, "; ", $npos);
          $current_author = substr($vv, $npos + 8, $npos2 - ($npos + 8)); // 8 == strlen("author: ")
        }
      }

      // still $in_revision_chunk?
      $npos = strpos($vv, "====================");
      if ($npos !== FALSE && $npos === 0)
      {
        $commit = array();
        $commit['directory'] = $current_directory;
        $commit['filename'] = $current_filename;
        $commit['revision'] = $current_revision;
        $commit['priorrevision'] = "";
        $commit['time'] = $current_time;
        $commit['author'] = $current_author;
        $commit['comment'] = $current_comment;
        $commits[$current_directory . "/" . $current_filename . ";" . $current_revision] = $commit;

        // Switching out of revision chunk. Clear current_comment:
        //
        $current_comment = "";
        $in_revision_chunk = 0;
      }

      if ($in_revision_chunk === 1 && $line_number > $in_revision_chunk_line_number + 2)
      {
        if ($current_comment === "")
        {
          $current_comment = $vv;
        }
        else
        {
          $current_comment = $current_comment . "\n" . $vv;
        }
      }
    }
  }

 return $commits;
}


function get_svn_repository_commits($svnroot, $dates)
{
  $commits = array();

  // To pick up all possible changes, the svn log query has to go back
  // *2* days -- svn log (for date queries) spits out all changes since
  // the beginning of the date, there is no syntax for passing time
  // stamps. Then, we have to filter the results to include only those
  // changes that fall in the $fromtime, $totime range...
  //
  // So call get_dates twice to get yesterday ($fromdate) and again to
  // get the low end of the svn log date range ($daybefore)...
  //
  $svnrevision = "{" . gmdate(FMT_DATE, $dates['nightly-2']) . "}:{" .
    gmdate(FMT_DATE, $dates['nightly+1']) . "}";

  $fromtime = gmdate(FMT_DATETIMESTD, $dates['nightly-1']+1) . " GMT";
  $totime = gmdate(FMT_DATETIMESTD, $dates['nightly-0']) . " GMT";

  $raw_output = `svn log $svnroot -r $svnrevision -v 2>&1`;
  //$raw_output = `svn help log`;

  $lines = explode("\n", $raw_output);


  $gathered_file_lines = array();
  $current_author = "";
  $current_comment = "";
  $current_directory = "";
  $current_filename = "";
  $current_revision = "";
  $current_time = 0;

  $line_number = 0;
  $last_chunk_line_number = 0;
  $in_list_of_filenames = 0;
  foreach($lines as $vv)
    {
    $line_number = $line_number + 1;

    $npos = strpos($vv, "--------------------");
    if ($npos !== FALSE && $npos === 0)
      {
      if ($line_number > 1)
        {
        if ($current_time > $fromtime && $current_time <= $totime)
          {
          foreach($gathered_file_lines as $ff)
            {
            $previous_revision = "";

            // Look if we have a A or a M
            if(strpos(substr($ff,0, 5),'A')!== FALSE)
              {
              $previous_revision = "-1"; // newly added file so we marked it as no prior revision
               }
              
            // Skip the '   M ' at the beginning of the filename output lines:
            //
            $current_filename = substr($ff, 5);

            // If there is " (from /blah/blah.h:42)" notation at end of filename,
            // strip it off:
            //
            $npos = strpos($current_filename, " (from ");
            if ($npos !== FALSE && $npos !== 0)
              {
              $current_filename = substr($current_filename, 0, $npos);
              }

            // Remove the first directory
            $npos = strpos($current_filename,"/",2);
            if ($npos !== FALSE && $npos !== 0)
              {
              $current_filename = substr($current_filename, $npos+1);
              }
              
            $current_directory = remove_directory_from_filename($current_filename);

            $commit = array();
            $commit['directory'] = $current_directory;
            $commit['filename'] = $current_filename;
            $commit['revision'] = $current_revision;
            $commit['priorrevision'] = $previous_revision;
            $commit['time'] = $current_time;
            $commit['author'] = $current_author;
            $commit['comment'] = $current_comment;
            $commits[$current_directory . "/" . $current_filename . ";" . $current_revision] = $commit;
            }
          }
        else
          {
          //echo "excluding: '" . $current_time . "' (" . gmdate(FMT_DATETIMEMS, $current_time) . ")<br/>";
          }
        $gathered_file_lines = array();
        }
      $current_comment = "";
      $last_chunk_line_number = $line_number;
      //echo "<br/>";
      }

    if ($line_number === $last_chunk_line_number + 1)
      {
      $npos = strpos($vv, " | ");
      if ($npos !== FALSE)
        {
        $current_revision = substr($vv, 1, $npos-1); // 1 == skip the 'r' at the beginning...
        //echo "current_revision: '" . $current_revision . "'<br/>";

        $npos2 = strpos($vv, " | ", $npos+3);
        if ($npos2 !== FALSE)
          {
          $current_author = substr($vv, $npos+3, $npos2 - ($npos+3));
          //echo "current_author: '" . $current_author . "'<br/>";
          $npos = $npos2;

          $npos2 = strpos($vv, " (", $npos+3);
          if ($npos2 !== FALSE)
            {
            $current_date = substr($vv, $npos+3, $npos2 - ($npos+3));
            //echo "current_date: '" . $current_date . "'<br/>";

            $current_time = gmdate(FMT_DATETIME,strtotime($current_date));
            //echo "date: '" . $current_time . "' (" . date(FMT_DATETIMEMS, $current_time) . ")<br/>";
            //echo "gmdate: '" . $current_time . "' (" . gmdate(FMT_DATETIMEMS, $current_time) . ")<br/>";

            $npos2 = strpos($vv, " | ", $npos+3);
            $npos = $npos2;
            if ($npos2 !== FALSE)
              {
              $current_line_count = substr($vv, $npos+3);
              $npos2 = strpos($current_line_count, " line");
              $current_line_count = substr($current_line_count, 0, $npos2);
              //echo "current_line_count: '" . $current_line_count . "'<br/>";
              }
            }
          }
        }
      }

    if ($in_list_of_filenames === 0 && $line_number > $last_chunk_line_number + 2)
      {
      $in_comment = 1;

      //echo "gather comment line: '" . $vv . "'<br/>";
      if ($current_comment === "")
        {
        $current_comment = $vv;
        }
      else
        {
        $current_comment = $current_comment . "\n" . $vv;
        }
      }

    if ($in_list_of_filenames === 1)
      {
      if (strlen($vv) === 0)
        {
        // Empty line signals the end of the list of filenames:
        //
        $in_list_of_filenames = 0;
        }
      else
        {
        $gathered_file_lines[] = $vv;
        }
      }

    if ($line_number === $last_chunk_line_number + 2)
      {
      $in_list_of_filenames = 1;
      }
    }
  return $commits;
}


function get_bzr_repository_commits($bzrroot, $dates)
{
  $commits = array();

  $fromtime = gmdate(FMT_DATETIMESTD, $dates['nightly-1']+1) . " GMT";
  $totime = gmdate(FMT_DATETIMESTD, $dates['nightly-0']) . " GMT";

  $raw_output = `bzr log -v --xml -r date:"$fromtime"..date:"$totime" $bzrroot 2>&1`;  

  $doc = new DomDocument;
  $doc->loadXML($raw_output);
  $logs = $doc->getElementsByTagName("log");

  foreach ($logs as $log) {
     $current_author = $log->getElementsByTagName("committer")->item(0)->nodeValue;
     // remove email from author and strip result
     $current_author = trim(substr($current_author, 0, strpos($current_author, "<")));     
     
     $current_comment = $log->getElementsByTagName("message")->item(0)->nodeValue;
     $current_time = gmdate(FMT_DATETIMEMS,strtotime($log->getElementsByTagName("timestamp")->item(0)->nodeValue));
     $current_revision = $log->getElementsByTagName("revno")->item(0)->nodeValue;
     
     $files = $log->getElementsByTagName("file");
     foreach ($files as $file) {
        $current_filename = $file->nodeValue;
        $current_directory = remove_directory_from_filename($current_filename);
        $commit = array();
        $commit['directory'] = $current_directory;
        $commit['filename'] = $current_filename;
        $commit['revision'] = $current_revision;
        $commit['priorrevision'] = "";
        $commit['time'] = $current_time;
        $commit['author'] = $current_author;
        $commit['comment'] = $current_comment;
        $commits[$current_directory . "/" . $current_filename . ";" . $current_revision] = $commit;        
     }
  }

  return $commits;
}

// Return an array of arrays. Each entry in the returned array will
// have the following named elements:
//   directory, filename, revision, time, author, comment
//
function get_repository_commits($projectid, $dates)
{
  global $xml;

  $roots = array();
 
  // Find the repository 
  $repositories = pdo_query("SELECT repositories.url FROM repositories,project2repositories 
                        WHERE repositories.id=project2repositories.repositoryid
                        AND project2repositories.projectid='$projectid'");

  while($repositories_array = pdo_fetch_array($repositories))
    {
    $roots[] = $repositories_array["url"];
    } 

  $cvsviewers = pdo_query("SELECT cvsviewertype FROM project 
                        WHERE id='$projectid'");

  $cvsviewers_array = pdo_fetch_array($cvsviewers);
  $cvsviewer = $cvsviewers_array[0];
  
  // Start with an empty array:
  $commits = array();

  foreach($roots as $root)
    {
    if (is_cvs_root($root))
      {
      $new_commits = get_cvs_repository_commits($root, $dates);
      }
    else
      {
      if ($cvsviewer == "loggerhead")
        {
        $new_commits = get_bzr_repository_commits($root, $dates);
        }
      else
        {
        $new_commits = get_svn_repository_commits($root, $dates);
        }       
      }

    if (count($new_commits)>0)
      {
      $commits = array_merge(array_values($commits), array_values($new_commits));
      }
    }

  return $commits;
}

/** Send email if expected build from last day have not been submitting */
function sendEmailExpectedBuilds($projectid,$currentstarttime)
{
  include("cdash/config.php");
  include_once("cdash/common.php");
  $db = pdo_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN", "$CDASH_DB_PASS");
  pdo_select_db("$CDASH_DB_NAME", $db);

  $currentEndUTCTime =  gmdate(FMT_DATETIME,$currentstarttime);
  $currentBeginUTCTime =  gmdate(FMT_DATETIME,$currentstarttime-3600*24);
  $sql = "SELECT buildtype,buildname,siteid,groupid,site.name FROM (SELECT g.siteid,g.buildtype,g.buildname,g.groupid FROM build2grouprule as g  LEFT JOIN build as b ON( 
          g.expected='1' AND (b.type=g.buildtype AND b.name=g.buildname AND b.siteid=g.siteid)
          AND b.projectid='$projectid' AND b.starttime>'$currentBeginUTCTime' AND b.starttime<'$currentEndUTCTime')
          WHERE (b.type is null AND b.name is null AND b.siteid is null) 
          AND g.expected='1'
          AND g.starttime<'$currentBeginUTCTime' AND (g.endtime>'$currentEndUTCTime' OR g.endtime='1980-01-01 00:00:00')) as t1, buildgroup as bg, site
          WHERE t1.groupid=bg.id AND bg.projectid='$projectid' AND bg.starttime<'$currentBeginUTCTime' AND (bg.endtime>'$currentEndUTCTime' OR bg.endtime='1980-01-01 00:00:00')
          AND site.id=t1.siteid
          ";
  $build2grouprule = pdo_query($sql);
  $authors = array();
  $projectname = get_project_name($projectid);    
  $summary = "The following expected builds for the project *".$projectname."* didn't submit yesterday:\n";
  $missingbuilds = 0;
  
  $currentURI = get_server_URI();
  
  while($build2grouprule_array = pdo_fetch_array($build2grouprule))
    {
    $builtype = $build2grouprule_array["buildtype"];
    $buildname = $build2grouprule_array["buildname"];
    $sitename = $build2grouprule_array["name"];
    $siteid = $build2grouprule_array["siteid"];
    $summary .= "* ".$sitename." - ".$buildname." (".$builtype.")\n";
   
    // Find the site maintainers
    $email = "";
    $emails = pdo_query("SELECT email FROM ".qid("user").",site2user WHERE ".qid("user").".id=site2user.userid AND site2user.siteid='$siteid'");
    while($emails_array = pdo_fetch_array($emails))
      {
      if($email != "")
        {
        $email .= ", ";
        }
      $email .= $emails_array["email"];
      }

    if($email!="")
      {
      $missingTitle = "CDash [".$projectname."] - Missing Build for ".$sitename; 
      $missingSummary = "The following expected build for the project ".$projectname." didn't submit yesterday:\n";
      $missingSummary .= "* ".$sitename." - ".$buildname." (".$builtype.")\n";
      $missingSummary .= "\n-CDash on ".$serverName."\n";

      if(mail("$email", $missingTitle, $missingSummary,
       "From: CDash <".$CDASH_EMAIL_FROM.">\nReply-To: ".$CDASH_EMAIL_REPLY."\nX-Mailer: PHP/" . phpversion()."\nMIME-Version: 1.0" ))
        {
        add_log("email sent to: ".$email,"sendEmailExpectedBuilds");
        return;
        }
      else
        {
        add_log("cannot send email to: ".$email,"sendEmailExpectedBuilds");
        }
      }
    $missingbuilds = 1;
    }
  
  // Send a summary email to the project administrator
  if($missingbuilds == 1)
    {
    $summary .= "\n-CDash on ".$serverName."\n";
    
    $title = "CDash [".$projectname."] - Missing Builds"; 
    
    // Find the site administrators
    $email = "";
    $emails = pdo_query("SELECT email FROM ".qid("user").",user2project WHERE ".qid("user").".id=user2project.userid AND user2project.role='2' AND user2project.projectid='$projectid'");
    while($emails_array = pdo_fetch_array($emails))
      {
      if($email != "")
        {
        $email .= ", ";
        }
      $email .= $emails_array["email"];
      }
      
    // Send the email
    if($email != "")
      {
      if(mail("$email", $title, $summary,
         "From: CDash <".$CDASH_EMAIL_FROM.">\nReply-To: ".$CDASH_EMAIL_REPLY."\nX-Mailer: PHP/" . phpversion()."\nMIME-Version: 1.0" ))
        {
        add_log("email sent to: ".$email,"sendEmailExpectedBuilds");
        return;
        }
      else
        {
        add_log("cannot send email to: ".$email,"sendEmailExpectedBuilds");
        }
      }
    }
}

/** Remove the buildemail that have been there from more than 48h */
function cleanBuildEmail($projectid)
{
  include("cdash/config.php");
  include_once("cdash/common.php");
  $now = date(FMT_DATETIME,time()-3600*48);
  pdo_query("DELETE from buildemail WHERE time<'$now'");
}

/** Add daily changes if necessary */
function addDailyChanges($projectid)
{
  include("cdash/config.php");
  include_once("cdash/common.php");
  include_once("cdash/sendemail.php");
  
  $db = pdo_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN", "$CDASH_DB_PASS");
  pdo_select_db("$CDASH_DB_NAME", $db);

  $project_array = pdo_fetch_array(pdo_query("SELECT nightlytime,name,autoremovetimeframe,autoremovemaxbuilds FROM project WHERE id='$projectid'"));
  $date = ""; // now
  list ($previousdate, $currentstarttime, $nextdate) = get_dates($date,$project_array["nightlytime"]);
  $date = gmdate(FMT_DATE, $currentstarttime);
  
  // Check if we already have it somwhere
  $query = pdo_query("SELECT id FROM dailyupdate WHERE projectid='$projectid' AND date='$date'");
  if(pdo_num_rows($query)==0)
    {
    pdo_query("INSERT INTO dailyupdate (projectid,date,command,type,status) 
               VALUES ($projectid,'$date','NA','NA','0')");
    
    $updateid = pdo_insert_id("dailyupdate");    
    
    $dates = get_related_dates($project_array["nightlytime"],$date);
    $commits = get_repository_commits($projectid, $dates);
    // Insert the commits
    foreach($commits as $commit)
      {
      $filename = $commit['directory']."/".$commit['filename'];
      $checkindate = $commit['time'];
      $author = addslashes($commit['author']);
      $log= addslashes($commit['comment']);
      $revision= $commit['revision'];
      $priorrevision = $commit['priorrevision'];
      
      pdo_query("INSERT INTO dailyupdatefile (dailyupdateid,filename,checkindate,author,log,revision,priorrevision)
                   VALUES ($updateid,'$filename','$checkindate','$author','$log','$revision','$priorrevision')");
      } // end foreach commit
    
    // Send an email if some expected builds have not been submitting
    sendEmailExpectedBuilds($projectid,$currentstarttime);    
    
    // cleanBuildEmail
    cleanBuildEmail($projectid);

    // If the status of daily update is set to 2 that means we should send an email
    $query = pdo_query("SELECT status FROM dailyupdate WHERE projectid='$projectid' AND date='$date'");
    $dailyupdate_array = pdo_fetch_array($query);
    $dailyupdate_status = $dailyupdate_array["status"];
    if($dailyupdate_status == 2)
      {
      // Find the groupid
      $group_query = pdo_query("SELECT buildid,groupid FROM summaryemail WHERE date='$date'");
      while($group_array = pdo_fetch_array($group_query))
        {
        $groupid = $group_array["groupid"];
        $buildid = $group_array["buildid"];
        
        // Find if the build has any errors
        $builderror = pdo_query("SELECT count(buildid) FROM builderror WHERE buildid='$buildid' AND type='0'");
        $builderror_array = pdo_fetch_array($builderror);
        $nbuilderrors = $builderror_array[0];
           
        // Find if the build has any warnings
        $buildwarning = pdo_query("SELECT count(buildid) FROM builderror WHERE buildid='$buildid' AND type='1'");
        $buildwarning_array = pdo_fetch_array($buildwarning);
        $nbuildwarnings = $buildwarning_array[0];
      
        // Find if the build has any test failings
        if($project_emailtesttimingchanged)
          {
          $sql = "SELECT count(testid) FROM build2test WHERE buildid='$buildid' AND (status='failed' OR timestatus>".qnum($project_testtimemaxstatus).")";
          }
        else
          {
          $sql = "SELECT count(testid) FROM build2test WHERE buildid='$buildid' AND status='failed'";
          }  
          
        $nfail_array = pdo_fetch_array(pdo_query($sql));
        $nfailingtests = $nfail_array[0];
  
        sendsummaryemail($projectid,$project_array["name"],$date,$groupid,$nbuildwarnings,$nbuilderrors,$nfailingtests);
        }
      }
    
    pdo_query("UPDATE dailyupdate SET status='1' WHERE projectid='$projectid' AND date='$date'");
    
    // Remove the first builds of the project
    include_once("cdash/autoremove.php");
    removeFirstBuilds($projectid,$project_array["autoremovetimeframe"],$project_array["autoremovemaxbuilds"]);
    }
}
?>

