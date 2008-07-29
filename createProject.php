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
include_once('common.php');
include("version.php");

if ($session_OK) 
  {
  @$db = pdo_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN","$CDASH_DB_PASS");
  pdo_select_db("$CDASH_DB_NAME",$db);

  $userid = $_SESSION['cdash']['loginid'];
  // Checks
  if(!isset($userid) || !is_numeric($userid))
    {
    echo "Not a valid userid!";
    return;
    }
    
  @$projectid = $_GET["projectid"];
   
  @$edit = $_GET["edit"];
   
  // If the projectid is not set and there is only one project we go directly to the page
  if(isset($edit) && !isset($projectid))
    {
    $project = pdo_query("SELECT id FROM project");
    if(pdo_num_rows($project)==1)
      {
      $project_array = pdo_fetch_array($project);
      $projectid = $project_array["id"];
      }
    }
  
  $role = 0;
   
  $user_array = pdo_fetch_array(pdo_query("SELECT admin FROM ".qid("user")." WHERE id='$userid'"));
  if($projectid && is_numeric($projectid))
    {
    $user2project = pdo_query("SELECT role FROM user2project WHERE userid='$userid' AND projectid='$projectid'");
    if(pdo_num_rows($user2project)>0)
      {
      $user2project_array = pdo_fetch_array($user2project);
      $role = $user2project_array["role"];
      }  
    }
    
  if(!(isset($_SESSION['cdash']['user_can_create_project']) && 
     $_SESSION['cdash']['user_can_create_project'] == 1)
     && ($user_array["admin"]!=1 && $role<=1))
    {
    echo "You don't have the permissions to access this page";
    return;
    }

$nRepositories = 0;
  
$xml = "<cdash>";
$xml .= "<cssfile>".$CDASH_CSS_FILE."</cssfile>";
$xml .= "<version>".$CDASH_VERSION."</version>";
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
  $project = pdo_query("SELECT id FROM project WHERE name='$Name'");
  
  if(pdo_num_rows($project)==0)
    {
    $Description = addslashes($_POST["description"]);
    $HomeURL = stripHTTP($_POST["homeURL"]);
    $CVSURL = stripHTTP($_POST["cvsURL"]);
    $BugURL = stripHTTP($_POST["bugURL"]);
    $DocURL = stripHTTP($_POST["docURL"]);
    @$Public = qnum($_POST["public"]);
    if(!isset($Public))
      {
      $Public = 0;
      }
    
    $CoverageThreshold = qnum($_POST["coverageThreshold"]);
    $NightlyTime = $_POST["nightlyTime"];
    $GoogleTracker = $_POST["googleTracker"]; 
    @$EmailBrokenSubmission = qnum($_POST["emailBrokenSubmission"]);
    @$EmailBuildMissing = qnum($_POST["emailBuildMissing"]);
    @$EmailLowCoverage = qnum($_POST["emailLowCoverage"]);
    @$EmailTestTimingChanged = qnum($_POST["emailTestTimingChanged"]);
    @$CVSViewerType = $_POST["cvsviewertype"];
    @$CVSRepositories = $_POST["cvsRepository"];
    @$TestTimeStd = qnum($_POST["testTimeStd"]);
    @$TestTimeStdThreshold = qnum($_POST["testTimeStdThreshold"]);
    @$TestTimeMaxStatus = qnum($_POST["testTimeMaxStatus"]);
    @$ShowTestTime = qnum($_POST["showTestTime"]);
     @$EmailMaxItems = qnum($_POST["emailMaxItems"]);
    @$EmailMaxChars = qnum($_POST["emailMaxChars"]);
         
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
      $result = pdo_query("$sql");
      if($row = pdo_fetch_array($result))
        {
        $imgid = qnum($row["id"]);
        }
      else
        {
        $sql = "INSERT INTO image(img, extension, checksum)
         VALUES ('$contents', '$filetype', '$checksum')";
        if(pdo_query("$sql"))
          {
          $imgid = pdo_insert_id("image");
          }
         }
      } // end if contents
      
    // Avoid errors with MySQL
    if(!isset($EmailBrokenSubmission))
      {
      $EmailBrokenSubmission = 0;
      }      
    if(!isset($EmailBuildMissing))
      {
      $EmailBuildMissing = 0;
      }  
    if(!isset($EmailLowCoverage))
      {
      $EmailLowCoverage = 0;
      }    
    if(!isset($EmailTestTimingChanged))
      {
      $EmailTestTimingChanged = 0;
      }
      
    // We should probably check the type of the image here to make sure the user
    // isn't trying anything fruity
    $sql = "INSERT INTO project(name,description,homeurl,cvsurl,bugtrackerurl,documentationurl,public,imageid,coveragethreshold,nightlytime,
                                googletracker,emailbrokensubmission,emailbuildmissing,emaillowcoverage,emailtesttimingchanged,cvsviewertype,
                                testtimestd,testtimestdthreshold,testtimemaxstatus,emailmaxitems,emailmaxchars,showtesttime)
            VALUES ('$Name','$Description','$HomeURL','$CVSURL','$BugURL','$DocURL',$Public,$imgid,$CoverageThreshold,'$NightlyTime',
                    '$GoogleTracker',$EmailBrokenSubmission,$EmailBuildMissing,$EmailLowCoverage,$EmailTestTimingChanged,'$CVSViewerType',
                    $TestTimeStd,$TestTimeStdThreshold,$TestTimeMaxStatus,$EmailMaxItems,$EmailMaxChars,$ShowTestTime)";                     
                    
    if(pdo_query("$sql"))
      {
      $projectid = pdo_insert_id("project");
      $xml .= "<project_name>$Name</project_name>";
      $xml .= "<project_id>$projectid</project_id>";
      $xml .= "<project_created>1</project_created>";
      }
    else
      {
      echo pdo_error();
      return;
      }
      
    // Add the default groups
    pdo_query("INSERT INTO buildgroup(name,projectid,starttime,endtime,description)
               VALUES ('Nightly',$projectid,'1980-01-01 00:00:00','1980-01-01 00:00:00','Nightly builds')");
    $id = pdo_insert_id("buildgroup");
    pdo_query("INSERT INTO buildgroupposition(buildgroupid,position,starttime,endtime) 
               VALUES ($id,1,'1980-01-01 00:00:00','1980-01-01 00:00:00')");
    pdo_query("INSERT INTO buildgroup(name,projectid,starttime,endtime,description) 
               VALUES ('Continuous',$projectid,'1980-01-01 00:00:00','1980-01-01 00:00:00','Continuous builds')");
    $id = pdo_insert_id("buildgroup");
    pdo_query("INSERT INTO buildgroupposition(buildgroupid,position,starttime,endtime) 
               VALUES ($id,2,'1980-01-01 00:00:00','1980-01-01 00:00:00')");
    pdo_query("INSERT INTO buildgroup(name,projectid,starttime,endtime,description)
               VALUES ('Experimental',$projectid,'1980-01-01 00:00:00','1980-01-01 00:00:00','Experimental builds')");
    $id = pdo_insert_id("buildgroup");
    pdo_query("INSERT INTO buildgroupposition(buildgroupid,position,starttime,endtime) 
               VALUES ($id,3,'1980-01-01 00:00:00','1980-01-01 00:00:00')");
    
    // Add administrator to the project
    pdo_query("INSERT INTO user2project(userid,projectid,role) VALUES (1,$projectid,2)");
    // Add current user to the project
    if($userid != 1)
      {
      pdo_query("INSERT INTO user2project(userid,projectid,role) VALUES ($userid,$projectid,2)");
     }
    
    // Add the repository 
    $url = $CVSRepositories[0];
    if(strlen($url) > 0)
      {
      // Insert into repositories if not any
      $repositories = pdo_query("SELECT id FROM repositories WHERE url='$url'");
      if(pdo_num_rows($repositories) == 0)
        {
        pdo_query("INSERT INTO repositories (url) VALUES ('$url')");
        $repositoryid = pdo_insert_id("repositories");
        }
      else
        {
        $repositories_array = pdo_fetch_array($repositories);
        $repositoryid = $repositories_array["id"];
        } 
      pdo_query("INSERT INTO project2repositories (projectid,repositoryid) VALUES ($projectid,$repositoryid)");
      echo pdo_error();   
      } // end url for repository is > 0
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
  $buildgroup = pdo_query("SELECT * FROM buildgroup WHERE projectid=$projectid");
  while($buildgroup_array = pdo_fetch_array($buildgroup))
    {
    $groupid = $buildgroup_array["id"];
    pdo_query("DELETE FROM buildgroupposition WHERE buildgroupid=$groupid");
    pdo_query("DELETE FROM build2grouprule WHERE groupid=$groupid");
    pdo_query("DELETE FROM build2group WHERE groupid=$groupid");
    }
   
  pdo_query("DELETE FROM buildgroup WHERE projectid=$projectid");
  pdo_query("DELETE FROM project WHERE id=$projectid");
  pdo_query("DELETE FROM user2project WHERE projectid=$projectid");
  
  echo "<script language=\"javascript\">window.location='user.php'</script>";
  } // end Delete project

if($projectid>0)
  {
  $project = pdo_query("SELECT * FROM project WHERE id=$projectid");
  $project_array = pdo_fetch_array($project);
  }


// If we should update the project
@$Update = $_POST["Update"];
@$AddRepository = $_POST["AddRepository"];
if($Update || $AddRepository)
  {
  $Description = addslashes($_POST["description"]);
  $HomeURL = stripHTTP($_POST["homeURL"]);
  $CVSURL = stripHTTP($_POST["cvsURL"]);
  $BugURL = stripHTTP($_POST["bugURL"]);
  $DocURL = stripHTTP($_POST["docURL"]);
  @$Public = qnum($_POST["public"]);
  $CoverageThreshold = qnum($_POST["coverageThreshold"]);
  $NightlyTime = $_POST["nightlyTime"];
  $GoogleTracker = $_POST["googleTracker"]; 
  @$EmailBrokenSubmission = qnum($_POST["emailBrokenSubmission"]);
  @$EmailBuildMissing = qnum($_POST["emailBuildMissing"]);
  @$EmailLowCoverage = qnum($_POST["emailLowCoverage"]);
  @$EmailTestTimingChanged = qnum($_POST["emailTestTimingChanged"]);
  @$CVSViewerType = $_POST["cvsviewertype"]; 
  @$TestTimeStd = qnum($_POST["testTimeStd"]);
  @$TestTimeStdThreshold = qnum($_POST["testTimeStdThreshold"]);
  @$TestTimeMaxStatus = qnum($_POST["testTimeMaxStatus"]);  
  @$TestTimeStdThreshold = qnum($_POST["testTimeStdThreshold"]);
  @$ShowTestTime = qnum($_POST["showTestTime"]);
  @$EmailMaxItems = qnum($_POST["emailMaxItems"]);
  @$EmailMaxChars = qnum($_POST["emailMaxChars"]);
  @$CVSRepositories = $_POST["cvsRepository"];

  $imgid = qnum($project_array["imageid"]);
  
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
    $result = pdo_query("$sql");
    if($row = pdo_fetch_array($result))
      {
      $imgid = qnum($row["id"]);
      }
    else if($imgid==0)
      {
      $sql = "INSERT INTO image(img, extension, checksum) VALUES ('$contents', '$filetype', '$checksum')";
      if(pdo_query("$sql"))
        {
        $imgid = qnum(pdo_insert_id("image"));
        }
       }
     else // update the current image
       { 
       pdo_query("UPDATE image SET img='$contents',extension='$filetype',checksum='$checksum' WHERE id=$imgid");
       }
    } // end if contents
    
  //We should probably check the type of the image here to make sure the user
  //isn't trying anything fruity
  pdo_query("UPDATE project SET description='$Description',homeurl='$HomeURL',cvsurl='$CVSURL',
                                  bugtrackerurl='$BugURL',documentationurl='$DocURL',public=$Public,imageid=$imgid,
                                  coveragethreshold=$CoverageThreshold,nightlytime='$NightlyTime',
                                  googletracker='$GoogleTracker',emailbrokensubmission=$EmailBrokenSubmission,
                                  emailbuildmissing=$EmailBuildMissing,emaillowcoverage=$EmailLowCoverage,
                                  emailtesttimingchanged=$EmailTestTimingChanged,
                                  cvsviewertype='$CVSViewerType',
                                  testtimestd=$TestTimeStd,
                                  testtimestdthreshold=$TestTimeStdThreshold,
                                  testtimemaxstatus=$TestTimeMaxStatus,
                                  emailmaxitems=$EmailMaxItems,
                                  emailmaxchars=$EmailMaxChars,
                                  showtesttime=$ShowTestTime
                                  WHERE id=$projectid");
  echo pdo_error();

  // First we update/delete any registered repositories
  $currentRepository = 0;
  $repositories = pdo_query("SELECT repositoryid from project2repositories WHERE projectid='$projectid' ORDER BY repositoryid");
  while($repository_array = pdo_fetch_array($repositories))
    {
    $repositoryid = $repository_array["repositoryid"];
    if(!isset($CVSRepositories[$currentRepository]) || strlen($CVSRepositories[$currentRepository])==0)
      {
      $query = pdo_query("SELECT * FROM project2repositories WHERE repositoryid='$repositoryid'");
      if(pdo_num_rows($query)==1)
        {
        pdo_query("DELETE FROM repositories WHERE id='$repositoryid'");
        }
      pdo_query("DELETE FROM project2repositories WHERE projectid='$projectid' AND repositoryid='$repositoryid'");  
      }
    else
      {
      pdo_query("UPDATE repositories SET url='$CVSRepositories[$currentRepository]' WHERE id='$repositoryid'");
      }  
    $currentRepository++;
    }
  
  //  Then we add new repositories
  for($i=$currentRepository;$i<count($CVSRepositories);$i++)
    {
    $url = $CVSRepositories[$i];
    if(strlen($url) == 0)
      {
      continue;
      }
    
    // Insert into repositories if not any
    $repositories = pdo_query("SELECT id FROM repositories WHERE url='$url'");
    if(pdo_num_rows($repositories) == 0)
      {
      pdo_query("INSERT INTO repositories (url) VALUES ('$url')");
      $repositoryid = pdo_insert_id("repositories");
      }
    else
      {
      $repositories_array = pdo_fetch_array($repositories);
      $repositoryid = $repositories_array["id"];
      } 
    pdo_query("INSERT INTO project2repositories (projectid,repositoryid) VALUES ('$projectid','$repositoryid')");
    echo pdo_error();   
    } 
  
  $project = pdo_query("SELECT * FROM project WHERE id='$projectid'");
  $project_array = pdo_fetch_array($project);
  }
  
  
// List the available projects
$sql = "SELECT id,name FROM project";
if($user_array["admin"] != 1)
  {
  $sql .= " WHERE id IN (SELECT projectid AS id FROM user2project WHERE userid='$userid' AND role>0)"; 
  }
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
  $xml .= add_XML_value("cvsviewertype",$project_array['cvsviewertype']);
  $xml .= add_XML_value("testtimestd",$project_array['testtimestd']);
  $xml .= add_XML_value("testtimestdthreshold",$project_array['testtimestdthreshold']);
  $xml .= add_XML_value("testtimemaxstatus",$project_array['testtimemaxstatus']);  
  $xml .= add_XML_value("showtesttime",$project_array['showtesttime']);
  $xml .= add_XML_value("emailmaxitems",$project_array['emailmaxitems']);
  $xml .= add_XML_value("emailmaxchars",$project_array['emailmaxchars']);
  $xml .= "</project>";
  
  $repository = pdo_query("SELECT url from repositories,project2repositories
                             WHERE repositories.id=project2repositories.repositoryid
                             AND   project2repositories.projectid='$projectid'");
  $nRegisteredRepositories = 0;
  $nRepositories = 0;
  while($repository_array = pdo_fetch_array($repository))
    {
    $xml .= "<cvsrepository>";
    $xml .= add_XML_value("id",$nRepositories);
    $xml .= add_XML_value("url",$repository_array['url']);
    $xml .= "</cvsrepository>";
    $nRegisteredRepositories++;
    $nRepositories++;
    }
    
  // If we should add another repository
  if($AddRepository)
    {
    $nTotalRepositories = $_POST["nRepositories"];
    for($i=$nRegisteredRepositories;$i<=$nTotalRepositories;$i++)
      {
      $xml .= "<cvsrepository>";
      $xml .= add_XML_value("id",$nRepositories);
      $xml .= add_XML_value("url","");
      $xml .= "</cvsrepository>";
      $nRepositories++;
      }
    } // end AddRepository
  } // end projectid=0

// Make sure we have at least one repository
if($nRepositories == 0)
  {
  $xml .= "<cvsrepository>";
  $xml .= add_XML_value("id",$nRepositories);
  $xml .= add_XML_value("url","");
  $xml .= "</cvsrepository>";
  $nRepositories++;
  }
    
// 
function AddCVSViewer($name,$description,$currentViewer)
  {
  $xml = "<cvsviewer>";
  if($currentViewer == $name)
    {
    $xml .= add_XML_value("selected","1");
    }
  $xml .= add_XML_value("value",$name);
  $xml .= add_XML_value("description",$description);
  $xml .= "</cvsviewer>";
  return $xml;
  }

// Add the type of CVS/SVN viewers
if(!isset($project_array))
  {
  $project_array['cvsviewertype'] = "viewcvs";
  }
$xml .= AddCVSViewer("viewcvs","ViewCVS",$project_array['cvsviewertype']); // first should be lower case
$xml .= AddCVSViewer("trac","Trac",$project_array['cvsviewertype']);
$xml .= AddCVSViewer("fisheye","Fisheye",$project_array['cvsviewertype']);
$xml .= AddCVSViewer("cvstrac","CVSTrac",$project_array['cvsviewertype']);
$xml .= AddCVSViewer("viewvc","ViewVC",$project_array['cvsviewertype']);
$xml .= AddCVSViewer("websvn","WebSVN",$project_array['cvsviewertype']);
 
$xml .= add_XML_value("nrepositories",$nRepositories); // should be at the end
  
$xml .= "</cdash>";

// Now doing the xslt transition
generate_XSLT($xml,"createProject");

} // end session
?>
