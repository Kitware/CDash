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
include("config.php");
include('login.php');
include("common.php");
include("version.php");

@$buildid = $_GET["buildid"];
@$date = $_GET["date"];
@$sortby = $_GET["sortby"];

// Checks
if(!isset($buildid) || !is_numeric($buildid))
  {
  echo "Not a valid buildid!";
  return;
  }
    
if(!$sortby)
  {
  $sortby = "filename";
  }

include("config.php");
$db = mysql_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN","$CDASH_DB_PASS");
mysql_select_db("$CDASH_DB_NAME",$db);
  
$build_array = mysql_fetch_array(mysql_query("SELECT starttime,projectid FROM build WHERE id='$buildid'"));  
$projectid = $build_array["projectid"];
 
checkUserPolicy(@$_SESSION['cdash']['loginid'],$projectid);
  
$project = mysql_query("SELECT * FROM project WHERE id='$projectid'");
if(mysql_num_rows($project)>0)
  {
  $project_array = mysql_fetch_array($project);
  $projectname = $project_array["name"];  
  }

$xml = '<?xml version="1.0"?><cdash>';
$xml .= "<title>CDash : ".$projectname."</title>";
$xml .= "<cssfile>".$CDASH_CSS_FILE."</cssfile>";
$xml .= "<version>".$CDASH_VERSION."</version>";
$xml .= get_cdash_dashboard_xml_by_name($projectname,$date);
  
  // coverage
  $xml .= "<coverage>";
  $coverage = mysql_query("SELECT * FROM coveragesummary WHERE buildid='$buildid'");
  $coverage_array = mysql_fetch_array($coverage);
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
  
  $coveredfiles = mysql_query("SELECT count(covered) FROM coverage WHERE buildid='$buildid' AND covered='1'");
  $coveredfiles_array = mysql_fetch_array($coveredfiles);
  $ncoveredfiles = $coveredfiles_array[0];
  
  $files = mysql_query("SELECT count(covered) FROM coverage WHERE buildid='$buildid'");
  $files_array = mysql_fetch_array($files);
  $nfiles = $files_array[0];
  
  $xml .= add_XML_value("totalcovered",$ncoveredfiles);
  $xml .= add_XML_value("totalfiles",$nfiles);
  $xml .= add_XML_value("totalsatisfactorilycovered",$ncoveredfiles);
  $xml .= add_XML_value("totalunsatisfactorilycovered",$nfiles-$ncoveredfiles);
  $xml .= add_XML_value("buildid",$buildid);
  $xml .= add_XML_value("sortby",$sortby);
  
    
  // Coverage files
  $coveragefile = mysql_query("SELECT cf.fullpath,c.fileid,c.locuntested,c.loctested,c.branchstested,c.branchsuntested,c.functionstested,c.functionsuntested
                               FROM coverage AS c,coveragefile AS cf WHERE c.buildid='$buildid' AND cf.id=c.fileid AND c.covered=1");
  
  $covfile_array = array();
  while($coveragefile_array = mysql_fetch_array($coveragefile))
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
      $covfile["functionsuntested"] = $coveragefile_array["functionsuntested"];
        
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
      
    $covfile_array[] = $covfile;
    }
    
   // Add the coverage type
  $xml .= add_XML_value("coveragetype",$coveragetype);
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
         
    }
    
  usort($covfile_array,"sort_array");
  
  // Add the untested files
  $coveragefile = mysql_query("SELECT cf.fullpath FROM coverage AS c,coveragefile AS cf WHERE c.buildid='$buildid' AND cf.id=c.fileid AND c.covered=0");
  while($coveragefile_array = mysql_fetch_array($coveragefile))
    {
    $covfile["filename"] = substr($coveragefile_array["fullpath"],strrpos($coveragefile_array["fullpath"],"/")+1);
    $covfile["fullpath"] = $coveragefile_array["fullpath"];
    $covfile["fileid"] = 0;
    $covfile["covered"] = 0;       
    $covfile_array[] = $covfile;
    }
  
  $i=0;
  foreach($covfile_array as $covfile)
    {   
    $xml .= "<coveragefile>";   
    // Backgroung color of the lines
    if($i%2==0)
      {
      $xml .= add_XML_value("bgcolor","#b0c4de");
      } 
    $xml .= add_XML_value("filename",$covfile["filename"]);
    $xml .= add_XML_value("fullpath",$covfile["fullpath"]);
    $xml .= add_XML_value("locuntested",$covfile["locuntested"]);
    $xml .= add_XML_value("covered",$covfile["covered"]);
    $xml .= add_XML_value("fileid",$covfile["fileid"]);
    $xml .= add_XML_value("percentcoverage",$covfile["percentcoverage"]);
    $xml .= add_XML_value("coveragemetric",$covfile["coveragemetric"]);
    $xml .= add_XML_value("functionsuntested",@$covfile["functionsuntested"]);
    $xml .= add_XML_value("branchesuntested",@$covfile["branchesuntested"]);    
    $xml .= "</coveragefile>";
    $i++;
    }
    
  $xml .= "</cdash>";

// Now doing the xslt transition
generate_XSLT($xml,"viewCoverage");
?>
