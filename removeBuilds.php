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
include("config.php");
require_once("pdo.php");
include('login.php');
include("version.php");
require_once("common.php");

set_time_limit(0);

$db = pdo_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN","$CDASH_DB_PASS");
if(!$db)
  {
  echo pdo_error();
  }
if(pdo_select_db("$CDASH_DB_NAME",$db) === FALSE)
  {
  echo pdo_error();
  return;
  }

checkUserPolicy(@$_SESSION['cdash']['loginid'],0); // only admin

@$projectid = $_GET["projectid"]; 
    
$xml = "<cdash>";
$xml .= "<cssfile>".$CDASH_CSS_FILE."</cssfile>";
$xml .= "<version>".$CDASH_VERSION."</version>";

//get date info here
@$dayTo = $_POST["dayFrom"];
if(!isset($dayTo))
  {
  $time = strtotime("2000-01-01 00:00:00");
  
  if(isset($projectid)) // find the first and last builds
    {
    $sql = "SELECT starttime FROM build WHERE projectid=".qnum($projectid)." ORDER BY starttime ASC LIMIT 1";
    $startttime = pdo_query($sql);
    if($startttime_array = pdo_fetch_array($startttime))
       {
       $time = strtotime($startttime_array['starttime']);
       }
    }
  $dayFrom = date('d',$time);
  $monthFrom = date('m',$time);
  $yearFrom = date('Y',$time);     
  $dayTo = date('d');
  $yearTo = date('Y');
  $monthTo = date('m');     
  }
else
  {
  $dayFrom = $_POST["dayFrom"];
  $monthFrom = $_POST["monthFrom"];
  $yearFrom = $_POST["yearFrom"];
  $dayTo = $_POST["dayTo"];
  $monthTo = $_POST["monthTo"];
  $yearTo = $_POST["yearTo"];
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
while($projects_array = pdo_fetch_array($projects))
   {
   $xml .= "<availableproject>";
   $xml .= add_XML_value("id",$projects_array['id']);
   $xml .= add_XML_value("name",$projects_array['name']);
   if($projects_array['id']==$projectid)
      {
      $xml .= add_XML_value("selected","1");
      }
   $xml .= "</availableproject>";
   }
   
$xml .= "<dayFrom>".$dayFrom."</dayFrom>";
$xml .= "<monthFrom>".$monthFrom."</monthFrom>";
$xml .= "<yearFrom>".$yearFrom."</yearFrom>";
$xml .= "<dayTo>".$dayTo."</dayTo>";
$xml .= "<monthTo>".$monthTo."</monthTo>";
$xml .= "<yearTo>".$yearTo."</yearTo>";

$xml .= "</cdash>";
@$submit = $_POST["Submit"];

/** THIS SHOULD GO IN  common.php */
/* Remove an array of builds 
 * This should be much faster than deleting builds one by one */
function remove_builds($builds)
{
  if(empty($builds))
    {
    return;
    }
    
  $buildsql="";
  $buildidsql="";

  foreach($builds as $buildid)
    {
    if(!is_numeric($buildid))
      {
      return;
      }
    
    if($buildsql != "")
      {
      $buildsql .= " OR ";
      $buildidsql .= " OR ";
      }
    $buildsql .= 'buildid='.qnum($buildid);
    $buildidsql .= 'id='.qnum($buildid);  
    }

  pdo_query("DELETE FROM build2group WHERE ".$buildsql);
  pdo_query("DELETE FROM builderror WHERE ".$buildsql);
  pdo_query("DELETE FROM buildinformation WHERE ".$buildsql);
  pdo_query("DELETE FROM buildnote WHERE ".$buildsql);
  pdo_query("DELETE FROM builderrordiff WHERE ".$buildsql);
  pdo_query("DELETE FROM buildupdate WHERE ".$buildsql);
  pdo_query("DELETE FROM configure WHERE ".$buildsql);
  pdo_query("DELETE FROM configureerror WHERE ".$buildsql);
  pdo_query("DELETE FROM configureerrordiff WHERE ".$buildsql);
  pdo_query("DELETE FROM coveragesummarydiff WHERE ".$buildsql);
  pdo_query("DELETE FROM testdiff WHERE ".$buildsql);
  pdo_query("DELETE FROM coverage WHERE ".$buildsql);
  pdo_query("DELETE FROM coveragefilelog WHERE ".$buildsql);
  pdo_query("DELETE FROM coveragesummary WHERE ".$buildsql);
  pdo_query("DELETE FROM dynamicanalysis WHERE ".$buildsql);
  pdo_query("DELETE FROM updatefile WHERE ".$buildsql);   
  pdo_query("DELETE FROM build2note WHERE ".$buildsql); 
  pdo_query("DELETE FROM build2test WHERE ".$buildsql); 
  
  // coverage file are kept unless they are shared
  pdo_query("DELETE FROM coveragefile WHERE id NOT IN (SELECT fileid as id FROM coverage)");

  // dynamicanalysisdefect
  pdo_query("DELETE FROM dynamicanalysisdefect WHERE dynamicanalysisid NOT IN (SELECT id as dynamicanalysisid FROM dynamicanalysis)");  
  
  // Delete the note if not shared
  pdo_query("DELETE FROM note WHERE id NOT IN (SELECT noteid as id FROM build2note)");
  
  // Delete the test if not shared
  pdo_query("DELETE FROM test WHERE id NOT IN (SELECT testid as id FROM build2test)");
  pdo_query("DELETE FROM testmeasurement WHERE testid NOT IN (SELECT id as testid FROM test)");
  pdo_query("DELETE FROM test2image WHERE testid NOT IN (SELECT id as testid FROM test)");

  // Delete the testimages if not shared
  pdo_query("DELETE FROM image WHERE id NOT IN (SELECT imgid as id FROM test2image) AND id NOT IN (SELECT imageid FROM project)");
  
  // Delete build
  pdo_query("DELETE FROM build WHERE ".$buildidsql);
}


// Delete the builds
if(isset($submit))
  {
  $begin = $yearFrom."-".$monthFrom."-".$dayFrom." 00:00:00";
  $end = $yearTo."-".$monthTo."-".$dayTo." 00:00:00";
  $sql = "SELECT id FROM build WHERE projectid=".qnum($projectid)." AND starttime<='$end' AND starttime>='$begin' ORDER BY starttime ASC";
    
  $build = pdo_query($sql);
  
  $builds = array();
  while($build_array = pdo_fetch_array($build))
    {
    $builds[] = $build_array['id'];
    }
 
  remove_builds($builds);
  echo "<br> Removed ".count($builds)." builds.<br>";
  }
  
generate_XSLT($xml,"removeBuilds");
?>
