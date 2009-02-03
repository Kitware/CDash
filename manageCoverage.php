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
include("cdash/config.php");
require_once("cdash/pdo.php");
include_once("cdash/common.php");
include('login.php');
include('cdash/version.php');
include_once("models/project.php");
include_once("models/coverage.php");
include_once("models/build.php");
include_once("models/user.php");

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
  
$xml = "<cdash>";
$xml .= "<cssfile>".$CDASH_CSS_FILE."</cssfile>";
$xml .= "<version>".$CDASH_VERSION."</version>";
$xml .= "<backurl>user.php</backurl>";
$xml .= "<title>CDash - Manage Coverage</title>";
$xml .= "<menutitle>CDash</menutitle>";
$xml .= "<menusubtitle>Coverage</menusubtitle>";
  
@$projectid = $_GET["projectid"];
$Project = new Project;
     
// If the projectid is not set and there is only one project we go directly to the page
if(isset($edit) && !isset($projectid))
  {
  $projectids = $Project->GetIds();
  if(count($projectids)==1)
    {
    $projectid = $projectids[0];
    }
  }

$User = new User;
$User->Id = $userid;
$Project->Id = $projectid;
  
$role = $Project->GetUserRole($userid);
     
if(!(isset($_SESSION['cdash']['user_can_create_project']) && 
   $_SESSION['cdash']['user_can_create_project'] == 1)
   && ($User->IsAdmin()===FALSE && $role<=1))
  {
  echo "You don't have the permissions to access this page";
  return;
  }
  
$sql = "SELECT id,name FROM project";
if($User->IsAdmin() == false)
  {
  $sql .= " WHERE id IN (SELECT projectid AS id FROM user2project WHERE userid='$userid' AND role>0)"; 
  }
$projects = pdo_query($sql);
while($project_array = pdo_fetch_array($projects))
   {
   $xml .= "<availableproject>";
   $xml .= add_XML_value("id",$project_array['id']);
   $xml .= add_XML_value("name",$project_array['name']);
   if($project_array['id']==$projectid)
      {
      $xml .= add_XML_value("selected","1");
      }
   $xml .= "</availableproject>";
   }

// Display the current builds who have coverage for the past 7 days
$currentUTCTime =  gmdate(FMT_DATETIME);
$beginUTCTime = gmdate(FMT_DATETIME,time()-3600*30*24); // 7 days

$CoverageFile2User = new CoverageFile2User();

// Add an author manually
if(isset($_POST["addAuthor"]))
  {
  $CoverageFile2User->UserId = $_POST["userSelection"];
  $CoverageFile2User->FileId = $_POST["fileId"];
  $CoverageFile2User->Insert();
  } // end addAuthor

// Add an author manually
if(isset($_GET["removefileid"]))
  {
  $CoverageFile2User->UserId = $_GET["removeuserid"];
  $CoverageFile2User->FileId = $_GET["removefileid"];
  $CoverageFile2User->Remove();
  } // end addAuthor

// Assign last author
if(isset($_POST["assignLastAuthor"]))
  {
  $CoverageFile2User->AssignLastAuthor($projectid,$beginUTCTime,$currentUTCTime);
  } // end last author
  
// Assign all authors
if(isset($_POST["assignAllAuthors"]))
  {
  $CoverageFile2User->AssignAllAuthors($projectid,$beginUTCTime,$currentUTCTime);
  } // end Assign all authors

// Upload file
if(isset($_POST["uploadAuthorsFile"]))
  {
  $contents = file_get_contents($_FILES['authorsFile']['tmp_name']);
  if(strlen($contents)>0)
    {  
    $pos = 0;
    $pos2 = strpos($contents,"\n");
    while($pos !== false)
      {
      $line = substr($contents,$pos,$pos2-$pos);
      
      $file = "";
      $authors = array();
      
      // first is the svnuser
      $posfile = strpos($line,":");
      if($posfile !== false)
        {
        $file = trim(substr($line,0,$posfile));
        $begauthor = $posfile+1;
        $endauthor = strpos($line,",",$begauthor);
        while($endauthor !== false)
          {
          $authors[] = trim(substr($line,$begauthor,$endauthor-$begauthor));
          $begauthor = $endauthor+1;
          $endauthor = strpos($line,",",$begauthor);
          }
        
        $authors[] = trim(substr($line,$begauthor));
        
        // Insert the user
        // Last build
        $CoverageSummary = new CoverageSummary();
        $buildids = $CoverageSummary->GetBuilds($Project->Id,$beginUTCTime,$currentUTCTime);
 
        $CoverageFile = new CoverageFile;
        $CoverageFile2User->FileId = $CoverageFile->GetIdFromName($file,$buildids[0]);
        
        if($CoverageFile2User->FileId === false)
          {
          echo "File not found for: ".$file."<br>";
          }
        else
          {      
          foreach($authors as $author)
            {
            $User = new User;
            $CoverageFile2User->UserId = $User->GetIdFromName($author);
            if($CoverageFile2User->UserId === false)
              {
              echo "User not found for: ".$author."<br>";
              }
            else
              {
              $CoverageFile2User->Insert();
              }
            }
          }
        }
        
      $pos = $pos2;
      $pos2 = strpos($contents,"\n",$pos2+1);
      } // end looping through lines
    } // end if strlen>0    
  }  // end upload authors file
  
// Send an email
if(isset($_POST["sendEmail"]))
  {
  $coverageThreshold = $Project->GetCoverageThreshold();
    
  $userids = $CoverageFile2User->GetUsersFromProject($projectid);
  foreach($userids as $userid)
    {
    $CoverageFile2User->UserId = $userid;
    $fileids = $CoverageFile2User->GetFiles();

    $files = array();
    
    // For each file check the coverage metric
    foreach($fileids as $fileid)
      {
      $coveragefile = new CoverageFile;
      $coveragefile->Id = $fileid;
      $metric = $coveragefile->GetMetric();
      if($metric < ($coverageThreshold/100.0))
        {
        $file['percent'] = $coveragefile->GetLastPercentCoverage();
        $file['path'] = $coveragefile->GetPath();
        $file['id'] = $fileid;
        $files[] = $file; 
        }
      }
    
    // Send an email if the number of uncovered file is greater than one
    if(count($files)>0)
      {
      // Writing the message
      $messagePlainText = "The following files for the project ".$Project->GetName();
      $messagePlainText .= " have a low coverage and "; 
      $messagePlainText .= "you have been identified as one of the authors of these files.\n";
      
      foreach($files as $file)
        {
        $messagePlainText .= $file['path']." (".$file['percent']."%)\n";
        }  
        
      $messagePlainText .= "Details on the submission can be found at ";
    
      $currentURI =  "http://".$_SERVER['SERVER_NAME'] .$_SERVER['REQUEST_URI']; 
      $currentURI = substr($currentURI,0,strrpos($currentURI,"/"));
      $messagePlainText .= $currentURI;
      $messagePlainText .= "\n\n";  
      $messagePlainText .= "\n-CDash on ".$_SERVER['SERVER_NAME']."\n";
        
      // Send the email
      $title = "CDash [".$Project->GetName()."] - Low Coverage";
      
      echo $title."<br>";
      echo $messagePlainText;   
      //mail("$email", $title, $messagePlainText,
      //    "From: CDash <".$CDASH_EMAIL_FROM.">\nReply-To: ".$CDASH_EMAIL_REPLY."\nX-Mailer: PHP/" . phpversion()."\nMIME-Version: 1.0" );
      }
    }
  
  } // end sendEmail
  
/** We start generating the XML here */

// Find the recent builds for this project
if($projectid>0)
  {
  $xml .= "<project>";
  $xml .= add_XML_value("id",$Project->Id);
  $xml .= add_XML_value("name",$Project->GetName());
  
  $CoverageSummary = new CoverageSummary();
  
  $buildids = $CoverageSummary->GetBuilds($Project->Id,$beginUTCTime,$currentUTCTime);
  foreach($buildids as $buildid)
    {
    $Build = new Build();
    $Build->Id = $buildid;
    $xml .= "<build>";
    $xml .= add_XML_value("id",$buildid);
    $xml .= add_XML_value("name",$Build->GetName());
    $xml .= "</build>";
    }
  
  // For now take the first one
  if(count($buildids)>0)
    {
    $buildid = $buildids[0];
    
    // Find the files associated with the build
    $Coverage = new Coverage();
    $Coverage->BuildId = $buildid;
    $fileIds = $Coverage->GetFiles();
    $row = "0";
    foreach($fileIds as $fileid)
      {
      $CoverageFile = new CoverageFile();
      $CoverageFile->Id = $fileid;
      $xml .= "<file>";
      $xml .= add_XML_value("id",$fileid);
      $xml .= add_XML_value("name",$CoverageFile->GetPath());
      
      if($row == 0)
        {
        $row = 1;
        }
      else
        {
        $row = 0;
        }   
      $xml .= add_XML_value("row",$row);
      
      // Get the authors
      $CoverageFile2User->FileId = $fileid;
      $authorids = $CoverageFile2User->GetAuthors();
      foreach($authorids as $authorid)
        {
        $xml .= "<author>";
        $User = new User();
        $User->Id = $authorid;
        $xml .= add_XML_value("id",$authorid);
        $xml .= add_XML_value("name",$User->GetName());
        $xml .= "</author>";
        }
        
      $xml .= "</file>";
      }
    } // end count(buildids)
 
  // List all the users of the project
  $UserProject = new UserProject();
  $UserProject->ProjectId = $Project->Id;
  $userIds = $UserProject->GetUsers();
  foreach($userIds as $userid)
    {
    $User = new User;
    $User->Id = $userid;
    $xml .= "<user>";
    $xml .= add_XML_value("id",$userid);
    $xml .= add_XML_value("name",$User->GetName());
    $xml .= "</user>";
    }
 
  $xml .= "</project>";
  }
$xml .= "</cdash>";

// Now doing the xslt transition
generate_XSLT($xml,"manageCoverage");

} // end session OK
?>

