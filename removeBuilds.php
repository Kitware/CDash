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

// Delete the builds
if(isset($submit))
  {
  $begin = $yearFrom."-".$monthFrom."-".$dayFrom." 00:00:00";
  $end = $yearTo."-".$monthTo."-".$dayTo." 00:00:00";
  $sql = "SELECT id FROM build WHERE projectid=".qnum($projectid)." AND starttime<='$end' AND starttime>='$begin' ORDER BY starttime ASC";
  
  echo $sql."<br>";
  
  $build = pdo_query($sql);
  
  ob_end_flush(); // This should be called at start

  // Do some preliminary calculations, such as:
  $totalloops = pdo_num_rows($build);
  $percent_per_loop = 100 / $totalloops;
  $prev_percent = 0;
  $percent_last = 0;
  $i=1;

  echo "0..................................................................................................100%<br>";
  while($build_array = pdo_fetch_array($build))
    {
    //sleep(1);
    //echo $build_array['id']."<br>";
    remove_build($build_array['id']);
    
    // Print progress
    $percent_now = round($i * $percent_per_loop);
    if($percent_now != $percent_last) 
      {
      $difference = $percent_now - $percent_last;
      for($j=1;$j<=$difference;$j++) 
        {
        echo '.';
        }
      $percent_last = $percent_now;
      $i++;
      flush(); // Push the new data to the browser;
      }
    }

  echo "<br> Removed ".$i." builds.<br>";
  }
  
generate_XSLT($xml,"removeBuilds");
?>
