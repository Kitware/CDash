<?php
/*=========================================================================

  Program:   CDash - Cross-Platform Dashboard System
  Module:    $RCSfile: viewChanges.php,v $
  Language:  PHP
  Date:      $Date: 2007-10-29 15:37:28 -0400 (Mon, 29 Oct 2007) $
  Version:   $Revision: 67 $

  Copyright (c) 2002 Kitware, Inc.  All rights reserved.
  See Copyright.txt or http://www.cmake.org/HTML/Copyright.html for details.

     This software is distributed WITHOUT ANY WARRANTY; without even 
     the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR 
     PURPOSE.  See the above copyright notices for more information.

=========================================================================*/
include("config.php");
include("common.php");

// get_related_dates takes a projectname and basedate as input
// and produces an array of related dates and times based on:
// the input, the project's nightly start time, now
//
function get_related_dates($projectname, $basedate)
{
  include("config.php");

  $dates = array();

  $db = mysql_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN", "$CDASH_DB_PASS");
  mysql_select_db("$CDASH_DB_NAME", $db);

  $dbQuery = mysql_query("SELECT nightlytime FROM project WHERE name='$projectname'");
  if(mysql_num_rows($dbQuery)>0)
    {
    $project = mysql_fetch_array($dbQuery);
    $nightlytime = $project['nightlytime'];
    //echo "query result nightlytime: " . $nightlytime . "<br/>";
    }
  else
    {
    $nightlytime = "00:00:00";
    //echo "default nightlytime: " . $nightlytime . "<br/>";
    }

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

function echo_array($a, $aname)
{
  echo '<br/>';
  echo count($a) . ' ' . $aname . ':<br/>';
  foreach($a as $item)
  {
    echo '<br/>';
    echo '&nbsp;&nbsp;' . $item . '<br/>';
  }
}

function echo_dates($projectname, $dates)
{
  echo "projectname: " . $projectname . "<br/>";

  $k = "now";
  $d = $dates[$k];
  echo $k . ": " . gmdate("Y-m-d H:i:s", $d) . " GMT<br/>";

  $k = "most-recent-nightly";
  $d = $dates[$k];
  echo $k . ": " . gmdate("Y-m-d H:i:s", $d) . " GMT<br/>";

  $k = "today_utc";
  $d = $dates[$k];
  echo $k . ": " . $d . " GMT<br/>";

  $k = "basedate";
  $d = $dates[$k];
  echo $k . ": " . $d . " GMT<br/>";

  $k = "nightly+2";
  $d = $dates[$k];
  echo $k . ": " . gmdate("Y-m-d H:i:s", $d) . " GMT<br/>";

  $k = "nightly+1";
  $d = $dates[$k];
  echo $k . ": " . gmdate("Y-m-d H:i:s", $d) . " GMT<br/>";

  $k = "nightly-0";
  $d = $dates[$k];
  echo $k . ": " . gmdate("Y-m-d H:i:s", $d) . " GMT<br/>";

  $k = "nightly-1";
  $d = $dates[$k];
  echo $k . ": " . gmdate("Y-m-d H:i:s", $d) . " GMT<br/>";

  $k = "nightly-2";
  $d = $dates[$k];
  echo $k . ": " . gmdate("Y-m-d H:i:s", $d) . " GMT<br/>";

  echo "<br/>";
  echo "<br/>";
  var_dump($dates);
  echo "<br/>";
  echo "<br/>";

  return;
}


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


// Take an eight character "YYYYmmdd" style date string and convert
// it to the one cvs understands best: "YYYY-mm-dd" with hyphens...
//
//function cvs_date($date)
//{
//  return date("Y-m-d", mktime("0", "0", "0",
//    substr($date,4,2), substr($date,6,2), substr($date,0,4)));
//}


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


// Return the email of a given author within a given project.
// DB lookup?
//
function get_author_email($projectname, $author)
{
  return $author . "_@_" . $projectname . ".org";
}


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
  $rcsfilebase = substr($cvsroot, $npos, $npos2 - $npos) . '/'; // . $module . '/';

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
      $npos = strpos($vv, "RCS file: " . $rcsfilebase);
      if ($npos !== FALSE && $npos === 0)
      {
        $npos = strlen("RCS file: " . $rcsfilebase);
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


  // Set this to 1 to echo the raw data before returning:
  //   (can help to debug issues...)
  $echo_raw_data = 0;

  if (count($commits) === 0 && $raw_output !== "")
  {
    //$echo_raw_data = 1;
  }


  // Echo query:
  //
  if ($echo_raw_data)
  {
    echo '<br/>';
    echo '<H2>Query</H2>';
    echo '&nbsp;&nbsp;cvs -d ' . $cvsroot . ' rlog -S -N -d "' . $fromtime . '&lt;' . $totime . '" ' . $module . ' 2>&amp;1';
    echo '<br/>';
  }


  // Echo summary data:
  //
  if ($echo_raw_data)
  {
    echo '<br/>';
    echo '<H2>Summary</H2>';

    echo_array($commits, "commits");

    echo '<br/>';
    echo 'rcsfilebase: ' . $rcsfilebase . '<br/>';
    echo 'total revisions: ' . $total_revisions . '<br/>';
    echo '<br/>';
  }


  // Echo line numbered raw data:
  //
  if ($echo_raw_data)
  {
    echo '<br/>';
    echo '<H2>RawData</H2>';
    echo '<pre>';
    $line_number = 0;
    foreach($lines as $vv)
    {
      $line_number = $line_number + 1;
      echo $line_number . ': ' . $vv . '
';
    }
    echo '</pre>';
    echo '<br/>';
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


  // Set this to 1 to echo the raw data before returning:
  //   (can help to debug issues...)
  $echo_raw_data = 0;

  if (count($commits) === 0 && $raw_output !== "")
  {
    //$echo_raw_data = 1;
  }


  // Echo query:
  //
  if ($echo_raw_data)
  {
    echo '<br/>';
    echo '<H2>Query</H2>';
    echo '&nbsp;&nbsp;svn log ' . $svnroot . ' -r ' . $svnrevision . ' -v 2>&amp;1';
    echo '<br/>';
  }


  // Echo summary data:
  //
  if ($echo_raw_data)
  {
    echo '<br/>';
    echo '<H2>Summary</H2>';

    echo_array($commits, "commits");

    echo '<br/>';
  }


  // Echo line numbered raw data:
  //
  if ($echo_raw_data)
  {
    echo '<br/>';
    echo '<H2>RawData</H2>';
    echo '<pre>';
    $line_number = 0;
    foreach($lines as $vv)
    {
      $line_number = $line_number + 1;
      echo $line_number . ': ' . htmlspecialchars($vv, ENT_QUOTES) . '
';
    }
    echo '</pre>';
    echo '<br/>';
  }


  return $commits;
}


function sort_by_directory_file_time($e1, $e2)
{
  // Sort directory names lexicographically in ascending order:
  // (A, B, C, ... Z)
  //
  $d1 = $e1['directory'];
  $d2 = $e2['directory'];
  if ($d1<$d2)
  {
    return -1;
  }
  if ($d1>$d2)
  {
    return 1;
  }

  // Sort file names lexicographically in ascending order
  // (A, B, C, ... Z)
  //
  $f1 = $e1['filename'];
  $f2 = $e2['filename'];
  if ($f1<$f2)
  {
    return -1;
  }
  if ($f1>$f2)
  {
    return 1;
  }

  // Sort time stamps numerically in descending order
  // (newer changes before older changes)
  //
  $t1 = $e1['time'];
  $t2 = $e2['time'];
  if ($t1<$t2)
  {
    return 1;
  }
  if ($t1>$t2)
  {
    return -1;
  }

  // Identical entries:
  //
  return 0;
}


// Return an array of arrays. Each entry in the returned array will
// have the following named elements:
//   directory, filename, revision, time, author, comment
//
function get_repository_commits($projectname, $dates)
{
  // Compute cvsroot(s) for project (DB lookup?)
  //
  if ($projectname === "CDash")
  {
    $roots[] = "https://www.kitware.com:8443/svn/CDash";
  }
  else if ($projectname === "CMake")
  {
    $roots[] = ":pserver:anonymous:cmake@www.cmake.org/cvsroot/CMake";
  }
  else if ($projectname === "Insight")
  {
    $roots[] = ":pserver:anonymous:insight@www.itk.org/cvsroot/Insight";
  }
  else if ($projectname === "KWWidgets")
  {
    $roots[] = ":pserver:anoncvs:@www.kwwidgets.org/cvsroot/KWWidgets";
  }
  else if ($projectname === "ParaView3")
  {
    $roots[] = ":pserver:anoncvs:@www.paraview.org/cvsroot/ParaView3";
  }
  else if ($projectname === "VTK")
  {
    $roots[] = ":pserver:anonymous:vtk@public.kitware.com/cvsroot/VTK";
    $roots[] = ":pserver:anonymous:vtk@public.kitware.com/cvsroot/VTKData";
  }
  else if ($projectname === "TortoiseSVN")
  {
    $roots[] = "http://tortoisesvn.tigris.org/svn/tortoisesvn";
  }
  else
  {
    $roots = array();
    echo "unrecognized project name: " . $projectname . " (could not lookup any cvsroot values)<br/>";
  }

  // Start with an empty array:
  //
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


function get_updates_xml_from_commits($projectname, $dates, $commits)
{
  $xml = "<updates>\n";
  $xml .= "<timestamp>" . date("Y-m-d H:i:s T", $dates['nightly-0']." GMT") .
    "</timestamp>";
  //$xml .= "<timestamp> " . $dates['basedate'] . "</timestamp>\n";
  $xml .= "<javascript>\n";

  // Args to dbAdd : "true" means directory, "false" means file
  //
  $xml .= "dbAdd(true, \"Updated files  (".count($commits).")\", \"\", 0, \"\", \"1\", \"\", \"\", \"\")\n";

  $previousdir = "";

  usort($commits, "sort_by_directory_file_time");

  $projecturl = get_project_property($projectname, "cvsurl");

  foreach($commits as $commit)
  {
    $directory = $commit['directory'];

    if($directory != $previousdir)
    {
      $xml .= "dbAdd(true, \"&lt;b&gt;".$directory."&lt;/b&gt;\", \"\", 1, \"\", \"1\", \"\", \"\", \"\")\n";
      $previousdir = $directory;
    }

    $filename = $commit['filename'];
    $revision = $commit['revision'];
    $time = gmdate("Y-m-d H:i:s", $commit['time']);
    $author = $commit['author'];
    $email = get_author_email($projectname, $author);

    $comment = $commit['comment'];
    $comment = str_replace("\n", " ", $comment); 
    // Do this twice so that <something> ends up as
    // &amp;lt;something&amp;gt; because it gets sent to a 
    // java script function not just displayed as html
    $comment = XMLStrFormat($comment);
    $comment = XMLStrFormat($comment);

    $diff_url = get_diff_url($projecturl, $directory, $filename, $revision);
    $diff_url = XMLStrFormat($diff_url);

    $xml .= "dbAdd(false, \"".$filename."  Revision: ".$revision."\",\"".$diff_url."\",2,\"\",\"1\",\"".$author."\",\"".$email."\",\"".$comment."\")\n";
  }

  $xml .= "</javascript>\n";
  $xml .= "</updates>";

  return $xml;
}


// Repository nightly queries are for the 24 hours leading up to the
// nightly start time for "$projectname" on "$date"

@$projectname = $_GET["project"];
@$date = $_GET["date"];

@$query = $_GET["query"];
if (!isset($query))
{
  $query = 1;
}


$dates = get_related_dates($projectname, $date);


if ($query === 1)
{
  $commits = get_repository_commits($projectname, $dates);
}
else
{
  $commits = array();
}


$xml = '<?xml version="1.0"?><cdash>';
$xml .= "<title>CDash : ".$projectname."</title>";
$xml .= "<cssfile>".$CDASH_CSS_FILE."</cssfile>";
$xml .= get_cdash_dashboard_xml_by_name($projectname, $date);
$xml .= get_updates_xml_from_commits($projectname, $dates, $commits);

  //echo "<pre>";
  //echo htmlspecialchars($xml, ENT_QUOTES);
  //echo "</pre>";

$xml .= "</cdash>";


generate_XSLT($xml, "viewChanges");


if ($query === 0)
{
  // redirect to this script to execute the query
  echo "<script language=\"javascript\">window.location='?project=".$projectname."&date=".$dates['basedate']."&query=1'</script>";
  return;
}


?>
