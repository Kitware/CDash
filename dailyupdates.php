<?php
/*=========================================================================

  Program:   CDash - Cross-Platform Dashboard System
  Module:    $Id: viewChanges.php,v $
  Language:  PHP
  Date:      $Date: 2007-10-29 15:37:28 -0400 (Mon, 29 Oct 2007) $
  Version:   $Revision: 67 $

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

set_time_limit(0);

function get_related_dates($projectnightlytime, $basedate)
{
  $dates = array();
  $nightlytime = $projectnightlytime;

  if(!isset($basedate) || strlen($basedate)==0)
    {
    $basedate = gmdate("Ymd");
    }

  // Convert the nightly time into GMT
  $nightlytime = gmdate("H:i:s",strtotime($nightlytime)); 

  $nightlyhour = substr($nightlytime,0,2);
  $nightlyminute = substr($nightlytime,3,2);
  $nightlysecond = substr($nightlytime,6,2);
  $basemonth = substr($basedate,4,2);
  $baseday = substr($basedate,6,2);
  $baseyear = substr($basedate,0,4);

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
  $currentgmdate = gmdate("Ymd", $currentgmtime);

  // Find the most recently past nightly time:
  //
  $todaymonth = substr($currentgmdate,4,2);
  $todayday = substr($currentgmdate,6,2);
  $todayyear = substr($currentgmdate,0,4);
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
  $dates['basedate'] = gmdate("Ymd", $dates['nightly-0']);

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
  $fromtime = gmdate("Y-m-d H:i:s", $dates['nightly-1']+1) . " GMT";
  $totime = gmdate("Y-m-d H:i:s", $dates['nightly-0']) . " GMT";

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
        $current_time = strtotime(substr($vv, $npos + 6, $npos2 - ($npos + 6))); // 6 == strlen("date: ")

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
  $svnrevision = "{" . gmdate("Ymd", $dates['nightly-2']) . "}:{" .
    gmdate("Ymd", $dates['nightly+1']) . "}";

  $fromtime = $dates['nightly-1'];
  $totime = $dates['nightly-0'];


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
            $commit['time'] = $current_time;
            $commit['author'] = $current_author;
            $commit['comment'] = $current_comment;
            $commits[$current_directory . "/" . $current_filename . ";" . $current_revision] = $commit;
            }
          }
        else
          {
          //echo "excluding: '" . $current_time . "' (" . gmdate("Y-m-d H:i:s.u", $current_time) . ")<br/>";
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

            $current_time = strtotime($current_date);
            //echo "date: '" . $current_time . "' (" . date("Y-m-d H:i:s.u", $current_time) . ")<br/>";
            //echo "gmdate: '" . $current_time . "' (" . gmdate("Y-m-d H:i:s.u", $current_time) . ")<br/>";

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


// Return an array of arrays. Each entry in the returned array will
// have the following named elements:
//   directory, filename, revision, time, author, comment
//
function get_repository_commits($projectid, $dates)
{
  global $xml;

  $roots = array();
 
  // Find the repository 
  $repositories = mysql_query("SELECT repositories.url FROM repositories,project2repositories 
                        WHERE repositories.id=project2repositories.repositoryid
                        AND project2repositories.projectid='$projectid'");

  foreach($repositories_array = mysql_fetch_array($repositories))
    {
    $roots[] = $repositories_array["url"];
    } 

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
      $new_commits = get_svn_repository_commits($root, $dates);
      }

    if (count($new_commits)>0)
      {
      $commits = array_merge(array_values($commits), array_values($new_commits));
      }
    }

  return $commits;
}

/** Add daily changes if necessary */
function addDailyChanges($projectid)
{
  include("config.php");
  include_once("common.php");
  $db = mysql_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN", "$CDASH_DB_PASS");
  mysql_select_db("$CDASH_DB_NAME", $db);

  $project_array = mysql_fetch_array(mysql_query("SELECT nightlytime,name FROM project WHERE id='$projectid'"));
  $date = ""; // now
  list ($previousdate, $currentstarttime, $nextdate) = get_dates($date,$project_array["nightlytime"]);
  $date = gmdate("Ymd", $currentstarttime);
  
  // Check if we already have it somwhere
  $query = mysql_query("SELECT id FROM dailyupdate WHERE projectid='$projectid' AND date='$date'");
  if(mysql_num_rows($query)==0)
    {
    mysql_query("INSERT INTO dailyupdate (projectid,date,command,type,status) 
                 VALUES ($projectid,$date,'NA','NA','0')");
    
    $updateid = mysql_insert_id();    
    
    $dates = get_related_dates($project_array["nightlytime"], $date);
    $commits = get_repository_commits($projectid, $dates);
    // Insert the commits
    foreach($commits as $commit)
      {
      $filename = $commit['directory']."/".$commit['filename'];
      $checkindate = $commit['time'];
      $author = addslashes($commit['author']);
      $log= addslashes($commit['comment']);
      $revision= $commit['revision'];  
      
      mysql_query("INSERT INTO dailyupdatefile (dailyupdateid,filename,checkindate,author,log,revision,priorrevision)
                   VALUES ($updateid,'$filename','$checkindate','$author','$log','$revision','$priorrevision')");
      } // end foreach commit
    
    }
}
?>

