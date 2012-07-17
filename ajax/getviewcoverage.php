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

// To be able to access files in this CDash installation regardless
// of getcwd() value:
//
$cdashpath = str_replace('\\', '/', dirname(dirname(__FILE__)));
set_include_path($cdashpath . PATH_SEPARATOR . get_include_path());

require_once("cdash/config.php");
require_once("cdash/pdo.php");
include_once("cdash/common.php");
include("cdash/version.php");
include("models/coveragefile2user.php");
include("models/user.php");
require_once("filterdataFunctions.php");

set_time_limit(0);

$noforcelogin = 1;
require('login.php');

$buildid = $_GET["buildid"];
if(!isset($buildid) || !is_numeric($buildid))
  {
  echo "Not a valid buildid!";
  return;
  }

$userid = 0;
if(isset($_GET['userid']) && is_numeric($_GET['userid']))
  {
  $userid = $_GET['userid'];
  }

// Find the project variables
$build = pdo_query("SELECT name,type,siteid,projectid,starttime FROM build WHERE id='$buildid'");
$build_array = pdo_fetch_array($build);
$projectid = $build_array['projectid'];

if(!isset($projectid) || $projectid==0 || !is_numeric($projectid))
  {
  echo "This project doesn't exist. Maybe it has been deleted.";
  exit();
  }

checkUserPolicy(@$_SESSION['cdash']['loginid'],$projectid);

$project = pdo_query("SELECT name,coveragethreshold,nightlytime,showcoveragecode FROM project WHERE id='$projectid'");
if(pdo_num_rows($project) == 0)
  {
  echo "This project doesn't exist.";
  exit();
  }

$role=0;
$user2project = pdo_query("SELECT role FROM user2project WHERE userid='$userid' AND projectid='$projectid'");
if(pdo_num_rows($user2project)>0)
  {
  $user2project_array = pdo_fetch_array($user2project);
  $role = $user2project_array["role"];
  }

$project_array = pdo_fetch_array($project);
$projectname = $project_array["name"];

$projectshowcoveragecode = 1;
if(!$project_array["showcoveragecode"] && $role<2)
  {
  $projectshowcoveragecode = 0;
  }

$start = 0;
$end = 10000000;

/** Paging */
if ( isset( $_GET['iDisplayStart'] ) && $_GET['iDisplayLength'] != '-1' )
  {
  $start = $_GET['iDisplayStart'];
  $end = $_GET['iDisplayStart']+$_GET['iDisplayLength'];
  }

/** Sorting */
$sortby = "";
if(isset($_GET['iSortCol_0']))
  {
  switch($_GET['iSortCol_0'])
    {
    case 0: $sortby="filename";break;
    case 1: $sortby="status";break;
    case 2: $sortby="percentage";break;
    case 3: $sortby="lines";break;
    case 5: $sortby="priority";break;
    }
  }

$sortdir = "asc";
if(isset($_GET['sSortDir_0']))
  {
  $sortdir = $_GET['sSortDir_0'];
  }

$SQLsearchTerm = "";
if ( isset($_GET['sSearch']) && $_GET['sSearch'] != "" )
  {
  $SQLsearchTerm = " AND cf.fullpath LIKE '%".pdo_real_escape_string($_GET['sSearch'])."%'";
  }

$SQLDisplayAuthors = '';
$SQLDisplayAuthor = '';
if($userid)
  {
  $SQLDisplayAuthor = ',cfu.userid ';
  $SQLDisplayAuthors = ' LEFT JOIN coveragefile2user AS cfu ON (cfu.fileid=cf.id) ';
  }

  // Filters:
  //
  $filterdata = get_filterdata_from_request();
  $filter_sql = $filterdata['sql'];
  $limit_sql = '';
  if ($filterdata['limit']>0)
  {
    $limit_sql = ' LIMIT '.$filterdata['limit'];
  }

  // Coverage files
  $sql = "SELECT cf.fullpath,c.fileid,".
         "c.locuntested,c.loctested,".
         "c.branchstested,c.branchsuntested,".
         "c.functionstested,c.functionsuntested,".
         "cfp.priority ".$SQLDisplayAuthor." ".
         "FROM coverage AS c,coveragefile AS cf ".
         $SQLDisplayAuthors." ".
         "LEFT JOIN coveragefilepriority AS cfp ON ".
         "(cfp.fullpath=cf.fullpath AND projectid=".qnum($projectid).") ".
         "WHERE c.buildid='$buildid' AND cf.id=c.fileid AND c.covered=1 ".
         $filter_sql." ".$SQLsearchTerm.$limit_sql;
  $coveragefile = pdo_query($sql);
  if (FALSE === $coveragefile)
    {
    add_log('error: pdo_query failed: ' . pdo_error(),
      __FILE__, LOG_ERR);
    }

  $covfile_array = array();
  while($coveragefile_array = pdo_fetch_array($coveragefile))
    {
    $covfile["filename"] = substr($coveragefile_array["fullpath"],strrpos($coveragefile_array["fullpath"],"/")+1);
    $covfile["fullpath"] = $coveragefile_array["fullpath"];
    $covfile["fileid"] = $coveragefile_array["fileid"];
    $covfile["locuntested"] = $coveragefile_array["locuntested"];
    $covfile["loctested"] = $coveragefile_array["loctested"];
    $covfile["covered"] = 1;
    // Compute the coverage metric for bullseye
    if($coveragefile_array["branchstested"]>0 || $coveragefile_array["branchsuntested"]>0 || $coveragefile_array["functionstested"]>0 || $coveragefile_array["functionsuntested"]>0)
      {
      // Metric coverage
      $metric = 0;
      if($coveragefile_array["functionstested"]+$coveragefile_array["functionsuntested"]>0)
        {
        $metric += $coveragefile_array["functionstested"]/($coveragefile_array["functionstested"]+$coveragefile_array["functionsuntested"]);
        }
      if($coveragefile_array["branchstested"]+$coveragefile_array["branchsuntested"]>0)
        {
        $metric += $coveragefile_array["branchstested"]/($coveragefile_array["branchstested"]+$coveragefile_array["branchsuntested"]);
        $metric /= 2.0;
        }
      $covfile["branchesuntested"] = $coveragefile_array["branchsuntested"];
      $covfile["branchestested"] = $coveragefile_array["branchstested"];
      $covfile["functionsuntested"] = $coveragefile_array["functionsuntested"];
      $covfile["functionstested"] = $coveragefile_array["functionstested"];


      $covfile["percentcoverage"] = sprintf("%3.2f",$metric*100);
      $covfile["coveragemetric"] = $metric;
      $coveragetype = "bullseye";
      }
    else // coverage metric for gcov
      {
      $covfile["percentcoverage"] = sprintf("%3.2f",$covfile["loctested"]/($covfile["loctested"]+$covfile["locuntested"])*100);
      $covfile["coveragemetric"] = ($covfile["loctested"]+10)/($covfile["loctested"]+$covfile["locuntested"]+10);
      $coveragetype = "gcov";
      }

    // Add the number of satisfactory covered files
    if($covfile["coveragemetric"]>=0.7)
      {
      $nsatisfactorycoveredfiles++;
      }

    // Add the priority
    $CoverageFile2User = new CoverageFile2User();
    $CoverageFile2User->ProjectId = $projectid;
    $CoverageFile2User->FullPath = $covfile["fullpath"];
    //$covfile["priority"] = $CoverageFile2User->GetPriority();


    $covfile["priority"] = $coveragefile_array["priority"];

    // If the user is logged in we set the users
    if(isset($coveragefile_array["userid"]))
      {
      $covfile["user"] = $coveragefile_array["userid"];
      }
    $covfile_array[] = $covfile;
    }


  // Add the coverage type
  $status = 0;
  if(isset($_GET['status']))
    {
    $status = $_GET['status'];
    }

  // Do the sorting
  function sort_array($a,$b)
    {
    global $sortby;
    global $sortdir;
    if($sortby == "filename")
      {
      if($a["fullpath"]==$b["fullpath"]) return 0;
      if($sortdir == 'desc') return $a["fullpath"]>$b["fullpath"] ? -1:1;
      return $a["fullpath"]>$b["fullpath"] ? 1:-1;
      }
    else if($sortby == "status")
      {
      if($a["fullpath"]==$b["fullpath"]) return 0;
      if($sortdir == 'desc') return $a["coveragemetric"]>$b["coveragemetric"] ? -1:1;
      return $a["coveragemetric"]>$b["coveragemetric"] ? 1:-1;
      }
    else if($sortby == "percentage")
      {
      if($a["percentcoverage"]==$b["percentcoverage"]) return 0;
      if($sortdir == 'desc') return $a["percentcoverage"]>$b["percentcoverage"]  ? -1:1;
      return $a["percentcoverage"]>$b["percentcoverage"] ? 1:-1;
      }
    else if($sortby == "lines")
      {
      if($a["locuntested"]==$b["locuntested"]) return 0;
      if($sortdir == 'desc') return $a["locuntested"]>$b["locuntested"] ? -1:1;
      return $a["locuntested"]>$b["locuntested"] ? 1:-1;
      }
    else if($sortby == "branches")
      {
      if($a["branchesuntested"]==$b["branchesuntested"]) return 0;
      if($sortdir == 'desc') return $a["branchesuntested"]>$b["branchesuntested"] ? -1:1;
      return $a["branchesuntested"]>$b["branchesuntested"] ? 1:-1;
      }
    else if($sortby == "functions")
      {
      if($a["functionsuntested"]==$b["functionsuntested"]) return 0;
      if($sortdir == 'desc') return $a["functionsuntested"]>$b["functionsuntested"] ? -1:1;
      return $a["functionsuntested"]>$b["functionsuntested"] ? 1:-1;
      }
    else if($sortby == "priority")
      {
      if($a["priority"]==$b["priority"]) return 0;
      if($sortdir == 'desc') return $a["priority"]>$b["priority"] ? -1:1;
      return $a["priority"]>$b["priority"] ? 1:-1;
      }
    else if($sortby == "user")
      {
      if(isset($a["user"][0]) && !isset($b["user"][0]))
        {
        return 0;
        }
      if(!isset($a["user"][0]) && isset($b["user"][0]))
        {
        return 1;
        }
      if(!isset($a["user"][0]) && !isset($b["user"][0]))
        {
        return 0;
        }
      return $a["user"][0]<$b["user"][0] ? 1:0;
      }
    }
  usort($covfile_array,"sort_array");

  // Add the untested files if the coverage is low
  if($coveragestatus == 0)
    {
    $sql = "SELECT cf.fullpath,cfp.priority".$SQLDisplayAuthor." FROM coverage AS c,coveragefile AS cf ".$SQLDisplayAuthors."
              LEFT JOIN coveragefilepriority AS cfp ON (cfp.fullpath=cf.fullpath AND projectid=".qnum($projectid).")
              WHERE c.buildid='$buildid' AND cf.id=c.fileid AND c.covered=0 ".
              $SQLsearchTerm;
    $coveragefile = pdo_query($sql);
    if (FALSE === $coveragefile)
      {
      add_log('error: pdo_query 2 failed: ' . pdo_error(),
        __FILE__, LOG_ERR);
      }
    while($coveragefile_array = pdo_fetch_array($coveragefile))
      {
      $covfile["filename"] = substr($coveragefile_array["fullpath"],strrpos($coveragefile_array["fullpath"],"/")+1);
      $covfile["fullpath"] = $coveragefile_array["fullpath"];
      $covfile["fileid"] = 0;
      $covfile["covered"] = 0;
      $covfile["locuntested"] = 0;
      $covfile["loctested"] = 0;
      $covfile["branchesuntested"] = 0;
      $covfile["branchestested"] = 0;
      $covfile["functionsuntested"] = 0;
      $covfile["functionstested"] = 0;
      $covfile["percentcoverage"] = 0;
      $covfile["coveragemetric"] = 0;

      $covfile["priority"] = $coveragefile_array["priority"];
      if(isset($coveragefile_array["userid"]))
        {
        $covfile["user"] = $coveragefile_array["userid"];
        }
      $covfile_array[] = $covfile;
      }
    }  // end adding untested files

  // Array to return to the datatable
  $output = array(
    "sEcho" => intval($_GET['sEcho']),
    "aaData" => array()
  );

  $ncoveragefiles = 0;
  $filestatus = -1;

  foreach($covfile_array as $covfile)
    {
    // Show only the low coverage
    if($covfile["covered"]==0)
      {
      $filestatus = 0; //no
      }
    else if($covfile["covered"]==1 && $covfile["percentcoverage"] == 0.0)
      {
      $filestatus = 1; //zero
      }
    else if(($covfile["covered"]==1 && $covfile["coveragemetric"] < $_GET['metricerror']))
      {
      $filestatus = 2; //low
      }
    else if($covfile["covered"]==1 && $covfile["coveragemetric"] == 1.0)
      {
      $filestatus = 5; //complete
      }
    else if($covfile["covered"]==1 && $covfile["coveragemetric"] >= $_GET['metricpass'])
      {
      $filestatus = 4; // satisfactory
      }
    else
      {
      $filestatus = 3; // medium
      }

    if($status != $filestatus)
      {
      continue;
      }
    $ncoveragefiles++;
    if($ncoveragefiles<$start)
      {
      continue;
      }
    else if($ncoveragefiles>$end)
      {
      break;
      }

    // For display purposes
    $roundedpercentage = round($covfile["percentcoverage"]);
    if($roundedpercentage>98) {$roundedpercentage = 98;};

    $row = array();

    // First column (Filename)
    if(!$covfile["covered"] || !$projectshowcoveragecode)
      {
      $row[] = $covfile["fullpath"];
      }
    else
      {
      $row[] = '<a href="viewCoverageFile.php?buildid='.$buildid.'&#38;fileid='.$covfile["fileid"].'">'.$covfile["fullpath"].'</a>';
      }

    // Second column (Status)
    switch($status)
      {
      case 0: $row[] = "No"; break;
      case 1: $row[] = "Zero"; break;
      case 2: $row[] = "Low"; break;
      case 3: $row[] = "Medium"; break;
      case 4: $row[] = "Satifactory"; break;
      case 5: $row[] = "Complete"; break;
      }

    // Third column (Percentage)
    $thirdcolumn = '<div style="position:relative; width: 190px;">
       <div style="position:relative; float:left;
       width: 123px; height: 12px; background: #bdbdbd url(\'images/progressbar.gif\') top left no-repeat;">
       <div style="height: 12px;margin-left:1px;';
    switch($status)
      {
      case 0: $thirdcolumn .= " background: #bdbdbd url('images/progressbg_red.gif') top left no-repeat;"; break;
      case 1: $thirdcolumn .= " background: #bdbdbd url('images/progressbg_red.gif') top left no-repeat;"; break;
      case 2: $thirdcolumn .= " background: #bdbdbd url('images/progressbg_red.gif') top left no-repeat;"; break;
      case 3: $thirdcolumn .= " background: #bdbdbd url('images/progressbg_orange.gif') top left no-repeat;"; break;
      case 4: $thirdcolumn .= " background: #bdbdbd url('images/progressbg_green.gif') top left no-repeat;"; break;
      case 5: $thirdcolumn .= " background: #bdbdbd url('images/progressbg_green.gif') top left no-repeat;"; break;
      }
    $thirdcolumn .= 'width:'.$roundedpercentage.'%;">';
    $thirdcolumn .='</div></div><div class="percentvalue" style="position:relative; float:left; margin-left:10px">'.$covfile["percentcoverage"].'%</div></div>';
    $row[] = $thirdcolumn;

    // Fourth column (Line not covered)
    $fourthcolumn = '';
    if($coveragetype == 'gcov')
      {
      $fourthcolumn = '<span';
      if($covfile["covered"]==0)
        {
        $fourthcolumn .= ' class="error">'.$covfile["locuntested"].'</span>';
        }
      else // covered > 0
        {
        switch($status)
          {
          case 0: $fourthcolumn .= ' class="error">'; break;
          case 1: $fourthcolumn .= ' class="error">'; break;
          case 2: $fourthcolumn .= ' class="error">'; break;
          case 3: $fourthcolumn .= ' class="warning">'; break;
          case 4: $fourthcolumn .= ' class="normal">'; break;
          case 5: $fourthcolumn .= ' class="normal">'; break;
          }
        $totalloc = $covfile["loctested"]+$covfile["locuntested"];
        $fourthcolumn .= $covfile["locuntested"].'/'.$totalloc.'</span>';
        }
      $row[] = $fourthcolumn;
      } // end gcov
    else if($coveragetype == 'bullseye')
      {
      // branches
      if($covfile["covered"]==0)
        {
        $fourthcolumn .= ' class="error">'.$covfile["branchesuntested"].'</span>';
        }
      else // covered > 0
        {
        switch($status)
          {
          case 0: $fourthcolumn .= ' class="error">'; break;
          case 1: $fourthcolumn .= ' class="error">'; break;
          case 2: $fourthcolumn .= ' class="error">'; break;
          case 3: $fourthcolumn .= ' class="warning">'; break;
          case 4: $fourthcolumn .= ' class="normal">'; break;
          case 5: $fourthcolumn .= ' class="normal">'; break;
          }
        $totalloc = @$covfile["branchestested"]+@$covfile["branchesuntested"];
        $fourthcolumn .= $covfile["branchesuntested"].'/'.$totalloc.'</span>';
        }
       $row[] = $fourthcolumn;

       $fourthcolumn2 = '';
       //functions
       if($covfile["covered"]==0)
        {
        $fourthcolumn2 .= ' class="error">0</span>';
        }
      else // covered > 0
        {
        switch($status)
          {
          case 0: $fourthcolumn2 .= ' class="error">'; break;
          case 1: $fourthcolumn2 .= ' class="error">'; break;
          case 2: $fourthcolumn2 .= ' class="error">'; break;
          case 3: $fourthcolumn2 .= ' class="warning">'; break;
          case 4: $fourthcolumn2 .= ' class="normal">'; break;
          case 5: $fourthcolumn2 .= ' class="normal">'; break;
          }
        $totalfunctions = @$covfile["functionstested"]+@$covfile["functionsuntested"];
        $fourthcolumn2 .= $covfile["functionsuntested"].'/'.$totalfunctions.'</span>';
        }
       $row[] = $fourthcolumn2;
       } // end bullseye

    // Fifth column (Priority)
    // Get the priority
    $priority = "NA";
    switch($covfile["priority"])
      {
      case 0: $priority = '<div>None</div>'; break;
      case 1: $priority = '<div>Low</div>'; break;
      case 2: $priority = '<div class="warning">Medium</div>'; break;
      case 3: $priority = '<div class="error">High</div>'; break;
      case 4: $priority = '<div class="error">Urgent</div>'; break;
       }
    $row[] = $priority;

    // Sixth colum (Authors)
    if($userid>0)
      {
      $author = '';
      if(isset($covfile["user"]))
        {
        $User = new User();
        $User->Id = $covfile["user"];
        $author = $User->GetName();
        }
      $row[] = $author;
      }

    // Seventh colum (Label)
    if(isset($_GET['displaylabels']) && $_GET['displaylabels']==1)
      {
      $fileid = $covfile['fileid'];
      $labels = '';
      $coveragelabels = pdo_query("SELECT text FROM label, label2coveragefile WHERE ".
                                  "label.id=label2coveragefile.labelid AND ".
                                  "label2coveragefile.coveragefileid='$fileid' AND ".
                                  "label2coveragefile.buildid='$buildid' ".
                                  "ORDER BY text ASC");
      while($coveragelabels_array = pdo_fetch_array($coveragelabels))
        {
        if($labels!='')
          {
          $labels .= ', ';
          }
        $labels .= $coveragelabels_array['text'];
        }

      $row[] = $labels;
      }

    $output['aaData'][] = $row;
    } // end loop through all the rows

  switch($status)
    {
    case 0: $output['iTotalRecords'] = $output['iTotalDisplayRecords'] = $_GET["nno"]; break;
    case 1: $output['iTotalRecords'] = $output['iTotalDisplayRecords'] = $_GET["nzero"]; break;
    case 2: $output['iTotalRecords'] = $output['iTotalDisplayRecords'] = $_GET["nlow"]; break;
    case 3: $output['iTotalRecords'] = $output['iTotalDisplayRecords'] = $_GET["nmedium"]; break;
    case 4: $output['iTotalRecords'] = $output['iTotalDisplayRecords'] = $_GET["nsatisfactory"]; break;
    case 5: $output['iTotalRecords'] = $output['iTotalDisplayRecords'] = $_GET["ncomplete"]; break;
    }

  echo json_encode( $output );
?>
