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
$noforcelogin = 1;
include("cdash/config.php");
require_once("cdash/pdo.php");
include('login.php');
include_once("cdash/common.php");
include("cdash/version.php");
include("models/coveragefile2user.php");
include("models/user.php");

set_time_limit(0);

@$buildid = $_GET["buildid"];
@$date = $_GET["date"];
@$sortby = $_GET["sortby"];

// Checks
if(!isset($buildid) || !is_numeric($buildid))
  {
  echo "Not a valid buildid!";
  return;
  }

@$userid = $_SESSION['cdash']['loginid'];
if(!isset($userid))
  {
  $userid = 0;
  }    
  
if(!$sortby)
  {
  $sortby = "status";
  }

$db = pdo_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN","$CDASH_DB_PASS");
pdo_select_db("$CDASH_DB_NAME",$db);
  
$build_array = pdo_fetch_array(pdo_query("SELECT starttime,projectid,siteid,type,name FROM build WHERE id='$buildid'"));  
$projectid = $build_array["projectid"];
 
if(!isset($projectid) || $projectid==0)
  {
  echo "This build doesn't exist. Maybe it has been deleted.";
  exit();
  }

checkUserPolicy(@$_SESSION['cdash']['loginid'],$projectid);
  
$project = pdo_query("SELECT name,coveragethreshold,nightlytime FROM project WHERE id='$projectid'");
if(pdo_num_rows($project)>0)
  {
  $project_array = pdo_fetch_array($project);
  $projectname = $project_array["name"];  
  }

$xml = '<?xml version="1.0"?><cdash>';
$xml .= "<title>CDash : ".$projectname."</title>";
$xml .= "<cssfile>".$CDASH_CSS_FILE."</cssfile>";
$xml .= "<version>".$CDASH_VERSION."</version>";
$xml .= get_cdash_dashboard_xml_by_name($projectname,$date);
$xml .= "<buildid>".$buildid."</buildid>";

$siteid = $build_array["siteid"];
$buildtype = $build_array["type"];
$buildname = $build_array["name"];
$starttime = $build_array["starttime"];

$xml .= "<menu>";
$xml .= add_XML_value("back","index.php?project=".urlencode($projectname)."&date=".get_dashboard_date_from_build_starttime($build_array["starttime"],$project_array["nightlytime"]));
$previousbuildid = get_previous_buildid($projectid,$siteid,$buildtype,$buildname,$starttime);
if($previousbuildid>0)
  {
  $xml .= add_XML_value("previous","viewCoverage.php?buildid=".$previousbuildid);
  }
else
  {
  $xml .= add_XML_value("noprevious","1");
  }  
$xml .= add_XML_value("current","viewCoverage.php?buildid=".get_last_buildid($projectid,$siteid,$buildtype,$buildname,$starttime));  
$nextbuildid = get_next_buildid($projectid,$siteid,$buildtype,$buildname,$starttime);
if($nextbuildid>0)
  {
  $xml .= add_XML_value("next","viewCoverage.php?buildid=".$nextbuildid);
  }  
else
  {
  $xml .= add_XML_value("nonext","1");
  }
$xml .= "</menu>";
  
  // coverage
  $xml .= "<coverage>";
  $coverage = pdo_query("SELECT * FROM coveragesummary WHERE buildid='$buildid'");
  $coverage_array = pdo_fetch_array($coverage);
  $xml .= add_XML_value("starttime",date("l, F d Y",strtotime($build_array["starttime"])));
  $xml .= add_XML_value("loctested",$coverage_array["loctested"]);
  $xml .= add_XML_value("locuntested",$coverage_array["locuntested"]);
  
  $loc = $coverage_array["loctested"]+$coverage_array["locuntested"];
  if($loc>0)
    {
    $percentcoverage = round($coverage_array["loctested"]/$loc*100,2);
    }
  else
    {
    $percentcoverage = 0;
    }   
  $xml .= add_XML_value("loc",$loc);
  $xml .= add_XML_value("percentcoverage",$percentcoverage);
  $xml .= add_XML_value("percentagegreen",$project_array["coveragethreshold"]);
  // Above this number of the coverage is green
  $metricpass = $project_array["coveragethreshold"]/100;
  $xml .= add_XML_value("metricpass",$metricpass);
  // Below this number of the coverage is red
  $metricerror = 0.7*($project_array["coveragethreshold"]/100);
  $xml .= add_XML_value("metricerror",$metricerror);


  $coveredfiles = pdo_query("SELECT count(covered) FROM coverage WHERE buildid='$buildid' AND covered='1'");
  $coveredfiles_array = pdo_fetch_array($coveredfiles);
  $ncoveredfiles = $coveredfiles_array[0];
  
  $files = pdo_query("SELECT count(covered) FROM coverage WHERE buildid='$buildid'");
  $files_array = pdo_fetch_array($files);
  $nfiles = $files_array[0];
  
  $xml .= add_XML_value("totalcovered",$ncoveredfiles);
  $xml .= add_XML_value("totalfiles",$nfiles);
  $xml .= add_XML_value("buildid",$buildid);
  $xml .= add_XML_value("sortby",$sortby);
  $xml .= add_XML_value("userid",$userid);
  
  $nsatisfactorycoveredfiles = 0;
    
  // Coverage files
  $coveragefile = pdo_query("SELECT cf.fullpath,c.fileid,c.locuntested,c.loctested,c.branchstested,c.branchsuntested,c.functionstested,c.functionsuntested
                               FROM coverage AS c,coveragefile AS cf WHERE c.buildid='$buildid' AND cf.id=c.fileid AND c.covered=1");
  
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
    $covfile["priority"] = $CoverageFile2User->GetPriority();

    // If the user is logged in we set the users
    if($userid>0)
      {
      $covfile["user"] = $CoverageFile2User->GetAuthors();
      }

    $covfile_array[] = $covfile;
    }
    
   // Add the coverage type
  $xml .= add_XML_value("coveragetype",$coveragetype);

  $xml .= add_XML_value("totalsatisfactorilycovered",$nsatisfactorycoveredfiles);
  $xml .= add_XML_value("totalunsatisfactorilycovered",$nfiles-$nsatisfactorycoveredfiles);

  $xml .= "</coverage>";  
  
  // Do the sorting
  function sort_array($a,$b)
    { 
    global $sortby; 
    if($sortby == "filename")
      {
      return $a["fullpath"]>$b["fullpath"] ? 1:0;
      }
    else if($sortby == "status")
      {
      return $a["coveragemetric"]>$b["coveragemetric"] ? 1:0;
      }
    else if($sortby == "percentage")
      {
      return $a["percentcoverage"]>$b["percentcoverage"] ? 1:0;
      }
    else if($sortby == "lines")
      {
      return $a["locuntested"]<$b["locuntested"] ? 1:0;
      }
    else if($sortby == "branches")
      {
      return $a["branchesuntested"]<$b["branchesuntested"] ? 1:0;
      } 
    else if($sortby == "functions")
      {
      return $a["functionsuntested"]<$b["functionsuntested"] ? 1:0;
      } 
    else if($sortby == "priority")
      {
      return $a["priority"]<$b["priority"] ? 1:0;
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
  
  // Add the untested files
  $coveragefile = pdo_query("SELECT cf.fullpath FROM coverage AS c,coveragefile AS cf WHERE c.buildid='$buildid' AND cf.id=c.fileid AND c.covered=0");
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
      
    // Add the priority
    $CoverageFile2User = new CoverageFile2User();
    $CoverageFile2User->ProjectId = $projectid;
    $CoverageFile2User->FullPath = $covfile["fullpath"];
    $covfile["priority"] = $CoverageFile2User->GetPriority();

    // If the user is logged in we set the users
    if($userid>0)
      {
      $covfile["user"] = $CoverageFile2User->GetAuthors();
      }
      
    $covfile_array[] = $covfile;
    }

  $ncoveragefiles = array();
  $ncoveragefiles[0] = 0;
  $ncoveragefiles[1] = 0;
  $ncoveragefiles[2] = 0;

  foreach($covfile_array as $covfile)
    {
    // Show only the low coverage  
    if($covfile["covered"]==0 || $covfile["coveragemetric"] < $metricerror)
      {
      $ncoveragefiles[0]++;  
      $coveragestatus = 0; // low 
      }
    else if($covfile["covered"]==1 && $covfile["coveragemetric"] >= $metricpass)
      {
      $ncoveragefiles[2]++;   
      $coveragestatus = 2; // satisfactory 
      }
    else
      {
      $ncoveragefiles[1]++; // medium
      $coveragestatus = 1;
      }

    if(isset($_GET['status']) && $_GET['status'] != $coveragestatus)
      {
      continue;  
      }
    else if(!isset($_GET['status']) && $coveragestatus!=0) // low by default
      {
      continue;  
      }
      
    $xml .= "<coveragefile>";   
    $xml .= add_XML_value("filename",$covfile["filename"]);
    $xml .= add_XML_value("fullpath",$covfile["fullpath"]);
    $xml .= add_XML_value("locuntested",$covfile["locuntested"]);
    $xml .= add_XML_value("totalloc",$covfile["loctested"]+$covfile["locuntested"]);
    $xml .= add_XML_value("covered",$covfile["covered"]);
    $xml .= add_XML_value("fileid",$covfile["fileid"]);
    $xml .= add_XML_value("percentcoverage",$covfile["percentcoverage"]);
    // For display purposes
    $roundedpercentage = round($covfile["percentcoverage"]);
    if($roundedpercentage>98) {$roundedpercentage = 98;};
    $xml .= add_XML_value("percentcoveragerounded",$roundedpercentage); // used for progress bar
    $xml .= add_XML_value("coveragemetric",$covfile["coveragemetric"]);
    $xml .= add_XML_value("functionsuntested",@$covfile["functionsuntested"]);
    $xml .= add_XML_value("totalfunctions",@$covfile["functionstested"]+@$covfile["functionsuntested"]);
    $xml .= add_XML_value("branchesuntested",@$covfile["branchesuntested"]);
    $xml .= add_XML_value("totalbranches",@$covfile["branchestested"]+@$covfile["branchesuntested"]);
    
    // Get the priority
    $priority = "NA";
    switch($covfile["priority"])
      {
      case 0: $priority = "None"; break;
      case 1: $priority = "Low"; break;
      case 2: $priority = "Medium"; break;
      case 3: $priority = "High"; break;
      case 4: $priority = "Urgent"; break;
       }
    $xml .= add_XML_value("priority",$priority);
   
    // Set the authors of the file
    if($userid>0)
      {
      foreach(@$covfile["user"] as $authorid)
        {
        $xml .= "<author>";
        $xml .= add_XML_value("id",$authorid);
        $User = new User();
        $User->Id = $authorid;
        $xml .= add_XML_value("name",$User->GetName());
        $xml .= "</author>";
        }
      }
     
    // Set the labels
    $fileid = $covfile['fileid'];
    $xml .= get_labels_xml_from_query_results(
      "SELECT text FROM label, label2coveragefile WHERE ".
      "label.id=label2coveragefile.labelid AND ".
      "label2coveragefile.coveragefileid='$fileid' AND ".
      "label2coveragefile.buildid='$buildid' ".
      "ORDER BY text ASC");
    $xml .= "</coveragefile>";
    }

    
  // Show the number of files covered by status  
  $xml .= "<coveragefilestatus>";
  $xml .= add_XML_value("low",$ncoveragefiles[0]);
  $xml .= add_XML_value("medium",$ncoveragefiles[1]);
  $xml .= add_XML_value("satisfactory",$ncoveragefiles[2]);
  $xml .= "</coveragefilestatus>";  
  
  $xml .= "</cdash>";
  
// Now doing the xslt transition
generate_XSLT($xml,"viewCoverage");
?>
