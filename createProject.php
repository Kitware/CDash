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
include('login.php');
include_once('common.php');

if ($session_OK) 
  {
  @$db = mysql_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN","$CDASH_DB_PASS");
  mysql_select_db("$CDASH_DB_NAME",$db);

  $userid = $_SESSION['cdash']['loginid'];
  @$projectid = $_GET["projectid"];
   
  @$edit = $_GET["edit"];
   
  // If the projectid is not set and there is only one project we go directly to the page
  if(isset($edit) && !isset($projectid))
  {
   $project = mysql_query("SELECT id FROM project");
   if(mysql_num_rows($project)==1)
    {
    $project_array = mysql_fetch_array($project);
    $projectid = $project_array["id"];
    }
  }
  
 
  $role = 0;
 
  $user_array = mysql_fetch_array(mysql_query("SELECT admin FROM user WHERE id='$userid'"));
  if($projectid)
    {
    $user2project = mysql_query("SELECT role FROM user2project WHERE userid='$userid' AND projectid='$projectid'");
    if(mysql_num_rows($user2project)>0)
      {
      $user2project_array = mysql_fetch_array($user2project);
      $role = $user2project_array["role"];
      }  
    }
    
  if($user_array["admin"]!=1 && $role<=1)
    {
    echo "You don't have the permissions to access this page";
    return;
    }

$xml = "<cdash>";
$xml .= "<cssfile>".$CDASH_CSS_FILE."</cssfile>";
$xml .= "<backurl>user.php</backurl>";

if($edit || isset($projectid))
  {
  $xml .= "<title>CDash - Edit Project</title>";
  $xml .= "<menutitle>CDash</menutitle>";
  $xml .= "<menusubtitle>Edit Project</menusubtitle>";
  $xml .= add_XML_value("edit","1");
  }
else
  {
  $xml .= "<title>CDash - New Project</title>";
  $xml .= "<menutitle>CDash</menutitle>";
  $xml .= "<menusubtitle>New Project</menusubtitle>";
  $xml .= add_XML_value("edit","0");
  }


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
  
  // Check that the name are different
  $project = mysql_query("SELECT id FROM project WHERE name='$Name'");
  
  if(mysql_num_rows($project)==0)
    {
    $Description = addslashes($_POST["description"]);
    $HomeURL = stripHTTP($_POST["homeURL"]);
    $CVSURL = stripHTTP($_POST["cvsURL"]);
    $BugURL = stripHTTP($_POST["bugURL"]);
		$DocURL = stripHTTP($_POST["docURL"]);
    @$Public = $_POST["public"];
    if(!isset($Public))
      {
      $Public = 0;
      }
    
    $CoverageThreshold = $_POST["coverageThreshold"];
    $NightlyTime = $_POST["nightlyTime"];
    $GoogleTracker = $_POST["googleTracker"]; 
    @$EmailBrokenSubmission = $_POST["emailBrokenSubmission"];
    @$EmailBuildMissing = $_POST["emailBuildMissing"]; 
    @$EmailLowCoverage = $_POST["emailLowCoverage"]; 
    @$EmailTestTimingChanged = $_POST["emailTestTimingChanged"];        
          
    $handle = fopen($_FILES['logo']['tmp_name'],"r");
    $contents = 0;
    if($handle)
      {
      $contents = addslashes(fread($handle,$_FILES['logo']['size']));
      $filetype = $_FILES['logo']['type'];
      fclose($handle);
      }
    
    $projectid = -1;
    $imgid = 0;
    
    /** Add the logo if any */
    if($contents)
      {
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
      } // end if contents
      
    //We should probably check the type of the image here to make sure the user
    //isn't trying anything fruity
    $sql = "INSERT INTO project(name,description,homeurl,cvsurl,bugtrackerurl,documentationurl,public,imageid,coveragethreshold,nightlytime,
                                googletracker,emailbrokensubmission,emailbuildmissing,emaillowcoverage,emailtesttimingchanged)
            VALUES ('$Name','$Description','$HomeURL','$CVSURL','$BugURL','$DocURL','$Public','$imgid','$CoverageThreshold','$NightlyTime',
                    '$GoogleTracker','$EmailBrokenSubmission','$EmailBuildMissing','$EmailLowCoverage','$EmailTestTimingChanged')"; 
    if(mysql_query("$sql"))
      {
      $projectid = mysql_insert_id();
      $xml .= "<project_name>$Name</project_name>";
			$xml .= "<project_id>$projectid</project_id>";
      $xml .= "<project_created>1</project_created>";
      }
    else
      {
      echo mysql_error();
      return;
      }
    
    // Add the default groups
    mysql_query("INSERT INTO buildgroup(name,projectid) VALUES ('Nightly','$projectid')");
    $id = mysql_insert_id();
    mysql_query("INSERT INTO buildgroupposition(buildgroupid,position) VALUES ('$id','1')");
    mysql_query("INSERT INTO buildgroup(name,projectid) VALUES ('Continuous','$projectid')");
    $id = mysql_insert_id();
    mysql_query("INSERT INTO buildgroupposition(buildgroupid,position) VALUES ('$id','2')");
    mysql_query("INSERT INTO buildgroup(name,projectid) VALUES ('Experimental','$projectid')");
    $id = mysql_insert_id();
    mysql_query("INSERT INTO buildgroupposition(buildgroupid,position) VALUES ('$id','3')");
    }
  else
    {
    $xml .= "<alert>Project's name already exists.</alert>";
    }
  
  } // end submit


// If we should delete the project
@$Delete = $_POST["Delete"];
if($Delete)
  {
  remove_project_builds($projectid);
	// Remove the project groups and rules
	$buildgroup = mysql_query("SELECT * FROM buildgroup WHERE projectid='$projectid'");
	while($buildgroup_array = mysql_fetch_array($buildgroup))
	  {
		$groupid = $buildgroup_array["id"];
		mysql_query("DELETE FROM buildgroupposition WHERE buildgroupid='$groupid'");
		mysql_query("DELETE FROM build2grouprule WHERE groupid='$groupid'");
		mysql_query("DELETE FROM build2group WHERE groupid='$groupid'");
	  }
  mysql_query("DELETE FROM buildgroup WHERE projectid='$projectid'");
  mysql_query("DELETE FROM project WHERE id='$projectid'");
  }

if($projectid>0)
  {
  $project = mysql_query("SELECT * FROM project WHERE id='$projectid'");
  $project_array = mysql_fetch_array($project);
  }


// If we should update the project
@$Update = $_POST["Update"];
if($Update)
  {
  $Description = addslashes($_POST["description"]);
  $HomeURL = stripHTTP($_POST["homeURL"]);
  $CVSURL = stripHTTP($_POST["cvsURL"]);
  $BugURL = stripHTTP($_POST["bugURL"]);
	$DocURL = stripHTTP($_POST["docURL"]);
  @$Public = $_POST["public"];
  $CoverageThreshold = $_POST["coverageThreshold"];
  $NightlyTime = $_POST["nightlyTime"];
  $GoogleTracker = $_POST["googleTracker"]; 
 @$EmailBrokenSubmission = $_POST["emailBrokenSubmission"];
 @$EmailBuildMissing = $_POST["emailBuildMissing"]; 
 @$EmailLowCoverage = $_POST["emailLowCoverage"]; 
 @$EmailTestTimingChanged = $_POST["emailTestTimingChanged"];

  $imgid = $project_array["imageid"];
  
  $handle = fopen($_FILES['logo']['tmp_name'],"r");
  $contents = 0;
  if($handle)
    {
    $contents = addslashes(fread($handle,$_FILES['logo']['size']));
    $filetype = $_FILES['logo']['type'];
    fclose($handle);
    }
  
  /** Add the logo if any */
  if($contents)
    {
    $checksum = crc32($contents);
    //check if we already have a copy of this file in the database
    $sql = "SELECT id FROM image WHERE checksum = '$checksum'";
    $result = mysql_query("$sql");
    if($row = mysql_fetch_array($result))
      {
      $imgid = $row["id"];
      }
    else if($imgid==0)
      {
      $sql = "INSERT INTO image(img, extension, checksum) VALUES ('$contents', '$filetype', '$checksum')";
      if(mysql_query("$sql"))
        {
        $imgid = mysql_insert_id();
        }
       }
     else // update the current image
       { 
       mysql_query("UPDATE image SET img='$contents',extension='$filetype',checksum='$checksum' WHERE id='$imgid'");
       }
    } // end if contents
    
  //We should probably check the type of the image here to make sure the user
  //isn't trying anything fruity
  mysql_query("UPDATE project SET description='$Description',homeurl='$HomeURL',cvsurl='$CVSURL',
                                  bugtrackerurl='$BugURL',documentationurl='$DocURL',public='$Public',imageid='$imgid',
                                  coveragethreshold='$CoverageThreshold',nightlytime='$NightlyTime',
                                  googletracker='$GoogleTracker',emailbrokensubmission='$EmailBrokenSubmission',
                                  emailbuildmissing='$EmailBuildMissing',emaillowcoverage='$EmailLowCoverage',
                                  emailtesttimingchanged='$EmailTestTimingChanged'
                                  WHERE id='$projectid'");
  echo mysql_error();

  $project = mysql_query("SELECT * FROM project WHERE id='$projectid'");
  $project_array = mysql_fetch_array($project);

  }
  
  
// List the available projects
// We should check if we are admin or not...
$sql = "SELECT id,name FROM project";
if($user_array["admin"] != 1)
  {
  $sql .= " WHERE id IN (SELECT projectid AS id FROM user2project WHERE userid='$userid')"; 
  }

$projects = mysql_query($sql);
while($projects_array = mysql_fetch_array($projects))
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
   
if($projectid>0)
  {
  $xml .= "<project>";
  $xml .= add_XML_value("id",$project_array['id']);
  $xml .= add_XML_value("name",$project_array['name']);
  $xml .= add_XML_value("description",$project_array['description']);
  $xml .= add_XML_value("homeurl",$project_array['homeurl']);  
  $xml .= add_XML_value("cvsurl",$project_array['cvsurl']);
  $xml .= add_XML_value("bugurl",$project_array['bugtrackerurl']);
  $xml .= add_XML_value("docurl",$project_array['documentationurl']);	
  $xml .= add_XML_value("public",$project_array['public']);
  $xml .= add_XML_value("imageid",$project_array['imageid']);
  $xml .= add_XML_value("coveragethreshold",$project_array['coveragethreshold']);  
  $xml .= add_XML_value("nightlytime",$project_array['nightlytime']);
  $xml .= add_XML_value("googletracker",$project_array['googletracker']);
  $xml .= add_XML_value("emailbrokensubmission",$project_array['emailbrokensubmission']);
  $xml .= add_XML_value("emailbuildmissing",$project_array['emailbuildmissing']);
  $xml .= add_XML_value("emaillowcoverage",$project_array['emaillowcoverage']);
  $xml .= add_XML_value("emailtesttimingchanged",$project_array['emailtesttimingchanged']);
  $xml .= "</project>";
  }
$xml .= "</cdash>";

// Now doing the xslt transition
generate_XSLT($xml,"createProject");

} // end session
?>
