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
include("config.php");
include("common.php"); 

@$db = mysql_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN","$CDASH_DB_PASS");
mysql_select_db("$CDASH_DB_NAME",$db);
$xml = "<cdash>";
$xml .= "<cssfile>".$CDASH_CSS_FILE."</cssfile>";

/** Strip the HTTP */
function stripHTTP($url)
  {
  $pos = strpos($url,"http://");
  if($pos !== FALSE)
    {
    return substr($url,7);
    }
  return $url;
  }

// If we should create the tables
@$Submit = $_POST["Submit"];
if($Submit)
  {
  $Name = $_POST["name"];
  $Description = addslashes($_POST["description"]);
  $HomeURL = stripHTTP($_POST["homeURL"]);
  $CVSURL = stripHTTP($_POST["cvsURL"]);
  $BugURL = stripHTTP($_POST["bugURL"]);
  $Public = $_POST["public"];
  $CoverageThreshold = $_POST["coverageThreshold"];
  $NightlyHour = $_POST["nightlyHour"];
  $NightlyMinute = $_POST["nightlyMinute"];
  $NightlySecond = $_POST["nightlySecond"];
    
  $handle = fopen($_FILES['logo']['tmp_name'],"r");
  $contents = 0;
  if($handle)
    {
    $contents = addslashes(fread($handle,$_FILES['logo']['size']));
    $filetype = $_FILES['logo']['type'];
    fclose($handle);
    }
  
  $projectid = -1;
  
  $NightlyTime = $NightlyHour.":".$NightlyMinute.":".$NightlySecond;
  
  //We should probably check the type of the image here to make sure the user
  //isn't trying anything fruity
  $sql = "INSERT INTO project(name,description,homeurl,cvsurl,bugtrackerurl,logo,public,coveragethreshold,nightlytime) 
   VALUES ('$Name','$Description','$HomeURL','$CVSURL','$BugURL','$contents','$Public','$CoverageThreshold','$NightlyTime')"; 
  if(mysql_query("$sql"))
    {
    $projectid = mysql_insert_id();
    $xml .= "<project_name>$Name</project_name>";
    $xml .= "<project_created>1</project_created>";
    }
  else
    {
    echo mysql_error();
    return;
    }
  
  // Add the default groups
  mysql_query("INSERT INTO buildgroup(name,projectid) ('Nightly','$projectid')");
  $id = mysql_insert_id();
  mysql_query("INSERT INTO buildgroupposition(buildgroupid,position) VALUES ('$id','1')");
  mysql_query("INSERT INTO buildgroup(name,projectid) VALUES ('Continuous','$projectid')");
  $id = mysql_insert_id();
  mysql_query("INSERT INTO buildgroupposition(buildgroupid,position) VALUES ('$id','2')");
  mysql_query("INSERT INTO buildgroup(name,projectid) VALUES ('Experimental','$projectid')");
  $id = mysql_insert_id();
  mysql_query("INSERT INTO buildgroupposition(buildgroupid,position) VALUES ('$id','3')");
  
  /** Add the logo if any */
  if($contents)
    {  
    $imgid = 0;
    $checksum = crc32($contents);
    //check if we already have a copy of this file in the database
    $sql = "SELECT id FROM image WHERE checksum = '$checksum'";
    $result = mysql_query("$sql");
    if($row = mysql_fetch_array($result))
      {
      $imgid = $row["id"];
      }
    else
      {
      $sql = "INSERT INTO image(img, extension, checksum)
       VALUES ('$contents', '$filetype', '$checksum')";
      if(mysql_query("$sql"))
        {
        $imgid = mysql_insert_id();
               }
             }
           if($imgid)
             {
             $sql = "INSERT INTO image2project(imgid, projectid)
              VALUES ('$imgid', '$projectid')";
             if(!mysql_query("$sql"))
        {
        echo mysql_error();
        return;
        }
      }
    } // end if contents
  } // end submit

$xml .= "</cdash>";

// Now doing the xslt transition
generate_XSLT($xml,"createProject");
?>
