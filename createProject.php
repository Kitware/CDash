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
include('login.php');
include_once('cdash/common.php');
include("cdash/version.php");
include("models/buildgroup.php");
include("models/project.php");
include("models/user.php");

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
  if(!$Project->ExistsByName($Name))
    {    
    $Project->Name = $Name;
    $Project->Description = $_POST["description"];
    $Project->HomeUrl = stripHTTP($_POST["homeURL"]);
    $Project->CvsUrl = stripHTTP($_POST["cvsURL"]);
    $Project->BugTrackerUrl = stripHTTP($_POST["bugURL"]);
    $Project->DocumentationUrl = stripHTTP($_POST["docURL"]);
    @$Public = $_POST["public"];
    if(!isset($Public))
      {
      $Public = 0;
      }
    
    $Project->CoverageThreshold = $_POST["coverageThreshold"];
    $Project->NightlyTime = $_POST["nightlyTime"];
    $Project->GoogleTracker = $_POST["googleTracker"]; 
    @$Project->EmailBrokenSubmission = $_POST["emailBrokenSubmission"];
    @$Project->EmailRedundantFailures = $_POST["emailRedundantFailures"];
    @$Project->EmailBuildMissing = $_POST["emailBuildMissing"];
    @$Project->EmailLowCoverage = $_POST["emailLowCoverage"];
    @$Project->EmailTestTimingChanged = $_POST["emailTestTimingChanged"];
    @$Project->CvsViewerType = $_POST["cvsviewertype"];
    @$CVSRepositories = $_POST["cvsRepository"];
    @$Project->TestTimeStd = $_POST["testTimeStd"];
    @$Project->TestTimeStdThreshold = $_POST["testTimeStdThreshold"];
    @$Project->TestTimeMaxStatus = $_POST["testTimeMaxStatus"];
    @$Project->ShowTestTime = $_POST["showTestTime"];
    @$Project->EmailMaxItems = $_POST["emailMaxItems"];
    @$Project->EmailMaxChars = $_POST["emailMaxChars"];
    @$Project->EmailAdministrator = $_POST["emailAdministrator"];
    @$Project->ShowIPAddresses = $_POST["showIPAddresses"];
    @$Project->DisplayLabels = $_POST["displayLabels"];
    @$Project->AutoremoveTimeframe = $_POST["autoremovetimeframe"];
    @$Project->AutoremoveMaxBuilds = $_POST["autoremovemaxbuilds"];
    $Project->Public = $Public;
    
    $projectid = -1;
    $Project->Id = '';
   
    // Save the project
    if($Project->Save())
      {
      $projectid = $Project->Id;
      }
                         
    if($projectid>0)
      {
      $xml .= "<project_name>$Name</project_name>";
      $xml .= "<project_id>$projectid</project_id>";
      $xml .= "<project_created>1</project_created>";
      }
    else
      {
      return;
      }
      
    // Add the default groups
    $BuildGroup = new BuildGroup;
    $BuildGroup->Id = ''; 
    $BuildGroup->Name = 'Nightly';
    $BuildGroup->Description = 'Nightly builds';
    $Project->AddBuildGroup($BuildGroup);
    $BuildGroup->Id = ''; 
    $BuildGroup->Name = 'Continuous';
    $BuildGroup->Description = 'Continuous builds';
    $Project->AddBuildGroup($BuildGroup);
    $BuildGroup->Id = ''; 
    $BuildGroup->Name = 'Experimental';
    $BuildGroup->Description = 'Experimental builds';
    $Project->AddBuildGroup($BuildGroup);

    // Add administrator to the project
    $UserProject = new UserProject;
    $UserProject->Role = 2;
    $UserProject->EmailType=3;// receive all emails
    $UserProject->ProjectId = $projectid;
    $User->Id = 1; // administrator
    $User->AddProject($UserProject);
    
    // Add current user to the project
    $User->Id = $userid;
    if($userid != 1)
      {
      $User->Id = $userid;
      $User->AddProject($UserProject);
      }
    
    // Add the repositories
    $Project->AddRepositories($CVSRepositories);
    
    /** Add the logo if any */
    if(isset($_FILES['logo']) && strlen($_FILES['logo']['tmp_name'])>0)
      {
      $handle = fopen($_FILES['logo']['tmp_name'],"r");
      $contents = 0;
      if($handle)
        {
        $contents = addslashes(fread($handle,$_FILES['logo']['size']));
        $filetype = $_FILES['logo']['type'];
        fclose($handle);
        }

      if($contents)
        {
        $imageId = $Project->AddLogo($contents,$filetype);
        } // end if contents
      } // end if logo name
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
  $Project->Delete();
  echo "<script language=\"javascript\">window.location='user.php'</script>";
  } // end Delete project

// If we should update the project
@$Update = $_POST["Update"];
@$AddRepository = $_POST["AddRepository"];
if($Update || $AddRepository)
  {
  $Project->Description = $_POST["description"];
  $Project->HomeUrl = stripHTTP($_POST["homeURL"]);
  $Project->CvsUrl = stripHTTP($_POST["cvsURL"]);
  $Project->BugTrackerUrl = stripHTTP($_POST["bugURL"]);
  $Project->DocumentationUrl = stripHTTP($_POST["docURL"]);
  @$Project->Public = $_POST["public"];
  $Project->CoverageThreshold = $_POST["coverageThreshold"];
  $Project->NightlyTime = $_POST["nightlyTime"];
  $Project->GoogleTracker = $_POST["googleTracker"]; 
  @$Project->EmailBrokenSubmission = $_POST["emailBrokenSubmission"];
  @$Project->EmailRedundantFailures = $_POST["emailRedundantFailures"];
  @$Project->EmailBuildMissing = $_POST["emailBuildMissing"];
  @$Project->EmailLowCoverage = $_POST["emailLowCoverage"];
  @$Project->EmailTestTimingChanged = $_POST["emailTestTimingChanged"];
  @$Project->CvsViewerType = $_POST["cvsviewertype"]; 
  @$Project->TestTimeStd = $_POST["testTimeStd"];
  @$Project->TestTimeStdThreshold = $_POST["testTimeStdThreshold"];
  @$Project->TestTimeMaxStatus = $_POST["testTimeMaxStatus"];  
  @$Project->TestTimeStdThreshold = $_POST["testTimeStdThreshold"];
  @$Project->ShowTestTime = $_POST["showTestTime"];
  @$Project->EmailMaxItems = $_POST["emailMaxItems"];
  @$Project->EmailMaxChars = $_POST["emailMaxChars"];
  @$Project->EmailAdministrator = $_POST["emailAdministrator"];
  @$Project->ShowIPAddresses = $_POST["showIPAddresses"];
  @$Project->DisplayLabels = $_POST["displayLabels"];
  @$Project->AutoremoveTimeframe = $_POST["autoremoveTimeframe"];
  @$Project->AutoremoveMaxBuilds = $_POST["autoremoveMaxBuilds"];
  $Project->Save();
  
  // Add the logo
  if(strlen($_FILES['logo']['tmp_name'])>0)
    {
    $handle = fopen($_FILES['logo']['tmp_name'],"r");
    $contents = 0;
    if($handle)
      {
      $contents = addslashes(fread($handle,$_FILES['logo']['size']));
      $filetype = $_FILES['logo']['type'];
      fclose($handle);
      }  
    $Project->AddLogo($contents,$filetype);
    }
  
  // Add repositories
  $Project->AddRepositories($_POST["cvsRepository"]);
  }
  
// List the available projects
$sql = "SELECT id,name FROM project";
if(!$User->IsAdmin())
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
  $Project->Fill();
  
  $xml .= "<project>";
  $xml .= add_XML_value("id",$Project->Id);
  $xml .= add_XML_value("name",$Project->Name);
  $xml .= add_XML_value("description",$Project->Description);
  $xml .= add_XML_value("homeurl",$Project->HomeUrl);  
  $xml .= add_XML_value("cvsurl",$Project->CvsUrl);
  $xml .= add_XML_value("bugurl",$Project->BugTrackerUrl);
  $xml .= add_XML_value("docurl",$Project->DocumentationUrl); 
  $xml .= add_XML_value("public",$Project->Public);
  $xml .= add_XML_value("imageid",$Project->ImageId);
  $xml .= add_XML_value("coveragethreshold",$Project->CoverageThreshold);  
  $xml .= add_XML_value("nightlytime",$Project->NightlyTime);
  $xml .= add_XML_value("googletracker",$Project->GoogleTracker);
  $xml .= add_XML_value("emailbrokensubmission",$Project->EmailBrokenSubmission);
  $xml .= add_XML_value("emailredundantfailures",$Project->EmailRedundantFailures);
  $xml .= add_XML_value("emailbuildmissing",$Project->EmailBuildMissing);
  $xml .= add_XML_value("emaillowcoverage",$Project->EmailLowCoverage);
  $xml .= add_XML_value("emailtesttimingchanged",$Project->EmailTestTimingChanged);
  $xml .= add_XML_value("cvsviewertype",$Project->CvsViewerType);
  $xml .= add_XML_value("testtimestd",$Project->TestTimeStd);
  $xml .= add_XML_value("testtimestdthreshold",$Project->TestTimeStdThreshold);
  $xml .= add_XML_value("testtimemaxstatus",$Project->TestTimeMaxStatus);  
  $xml .= add_XML_value("showtesttime",$Project->ShowTestTime);
  $xml .= add_XML_value("emailmaxitems",$Project->EmailMaxItems);
  $xml .= add_XML_value("emailmaxchars",$Project->EmailMaxChars);
  $xml .= add_XML_value("emailadministrator",$Project->EmailAdministrator);
  $xml .= add_XML_value("showipaddresses",$Project->ShowIPAddresses);
  $xml .= add_XML_value("displaylabels",$Project->DisplayLabels);
  $xml .= add_XML_value("autoremovetimeframe",$Project->AutoremoveTimeframe);
  $xml .= add_XML_value("autoremovemaxbuilds",$Project->AutoremoveMaxBuilds);
  $xml .= "</project>";
  
  $repositories = $Project->GetRepositories();
  $nRegisteredRepositories = 0;
  $nRepositories = 0;
  foreach($repositories as $repository)
    {
    $xml .= "<cvsrepository>";
    $xml .= add_XML_value("id",$nRepositories);
    $xml .= add_XML_value("url",$repository['url']);
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
if(strlen($Project->CvsViewerType)==0)
  {
  $Project->CvsViewerType = "viewcvs";
  }
  
$xml .= AddCVSViewer("viewcvs","ViewCVS",$Project->CvsViewerType); // first should be lower case
$xml .= AddCVSViewer("trac","Trac",$Project->CvsViewerType);
$xml .= AddCVSViewer("fisheye","Fisheye",$Project->CvsViewerType);
$xml .= AddCVSViewer("cvstrac","CVSTrac",$Project->CvsViewerType);
$xml .= AddCVSViewer("viewvc","ViewVC",$Project->CvsViewerType);
$xml .= AddCVSViewer("viewvc1.1","ViewVC1.1",$Project->CvsViewerType);
$xml .= AddCVSViewer("websvn","WebSVN",$Project->CvsViewerType);
$xml .= AddCVSViewer("loggerhead","Loggerhead",$Project->CvsViewerType);
 
$xml .= add_XML_value("nrepositories",$nRepositories); // should be at the end
  
$xml .= "</cdash>";

// Now doing the xslt transition
generate_XSLT($xml,"createProject");

} // end session
?>
