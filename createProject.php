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

  @$edit = $_GET["edit"];

  @$projectid = $_GET["projectid"];
  if ($projectid != NULL)
    {
    $projectid = pdo_real_escape_numeric($projectid);
    }

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

  // If the projectid is set, make sure that it's valid
  $Project->Id = $projectid;
  if(isset($projectid) && $projectid>0 && !$Project->Exists())
    {
    echo "This project doesn't exists.";
    return;
    }

  $User = new User;
  $User->Id = $userid;
  $role = $Project->GetUserRole($userid);

  // If we are editing a project make sure we have the right to do so
  if(!isset($projectid)
     && !(isset($_SESSION['cdash']['user_can_create_project']) &&
     $_SESSION['cdash']['user_can_create_project'] == 1)
     && !$User->IsAdmin()
     )
    {
    echo "You don't have the permissions to access this page";
    return;
    }
  else if(isset($projectid) && (!$User->IsAdmin() && $role<=1))
    {
    echo "You don't have the permissions to access this page";
    return;
    }

$nRepositories = 0;

$xml = begin_XML_for_XSLT();
$xml .= "<backurl>user.php</backurl>";
$xml .= "<user><id>".$userid."</id></user>";
$xml .= add_XML_value("manageclient",$CDASH_MANAGE_CLIENTS);

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
  // Remove any slashes, etc...
  $Name = stripslashes_if_gpc_magic_quotes($_POST["name"]);
  $Name = preg_replace("/[^a-zA-Z0-9\s+-._]/", "",$Name);

  // Check that the name are different
  if(!$Project->ExistsByName($Name))
    {
    $Project->Name = $Name;
    $Project->Description = stripslashes_if_gpc_magic_quotes($_POST["description"]);
    $Project->HomeUrl = stripHTTP(stripslashes_if_gpc_magic_quotes($_POST["homeURL"]));
    $Project->CvsUrl = stripHTTP(stripslashes_if_gpc_magic_quotes($_POST["cvsURL"]));
    $Project->BugTrackerUrl = stripslashes_if_gpc_magic_quotes($_POST["bugURL"]);
    $Project->BugTrackerFileUrl = stripslashes_if_gpc_magic_quotes($_POST["bugFileURL"]);
    $Project->DocumentationUrl = stripHTTP(stripslashes_if_gpc_magic_quotes($_POST["docURL"]));
    $Project->TestingDataUrl = stripHTTP(stripslashes_if_gpc_magic_quotes($_POST["testingDataUrl"]));
    @$Public = $_POST["public"];
    if(!isset($Public))
      {
      $Public = 0;
      }

    $Project->CoverageThreshold = stripslashes_if_gpc_magic_quotes($_POST["coverageThreshold"]);
    $Project->NightlyTime = stripslashes_if_gpc_magic_quotes($_POST["nightlyTime"]);
    $Project->GoogleTracker = stripslashes_if_gpc_magic_quotes($_POST["googleTracker"]);
    @$Project->EmailBrokenSubmission = stripslashes_if_gpc_magic_quotes($_POST["emailBrokenSubmission"]);
    @$Project->EmailRedundantFailures = stripslashes_if_gpc_magic_quotes($_POST["emailRedundantFailures"]);
    @$Project->EmailLowCoverage = stripslashes_if_gpc_magic_quotes($_POST["emailLowCoverage"]);
    @$Project->EmailTestTimingChanged = stripslashes_if_gpc_magic_quotes($_POST["emailTestTimingChanged"]);
    @$Project->CvsViewerType = stripslashes_if_gpc_magic_quotes($_POST["cvsviewertype"]);
    @$Project->RobotName = stripslashes_if_gpc_magic_quotes($_POST["robotname"]);
    @$Project->RobotRegex = stripslashes_if_gpc_magic_quotes($_POST["robotregex"]);
    @$CVSRepositories = stripslashes_if_gpc_magic_quotes($_POST["cvsRepository"]);
    @$CVSUsernames = stripslashes_if_gpc_magic_quotes($_POST["cvsUsername"]);
    @$CVSPasswords = stripslashes_if_gpc_magic_quotes($_POST["cvsPassword"]);
    @$CVSBranches = stripslashes_if_gpc_magic_quotes($_POST["cvsBranch"]);

    @$Project->TestTimeStd = stripslashes_if_gpc_magic_quotes($_POST["testTimeStd"]);
    @$Project->TestTimeStdThreshold = stripslashes_if_gpc_magic_quotes($_POST["testTimeStdThreshold"]);
    @$Project->TestTimeMaxStatus = stripslashes_if_gpc_magic_quotes($_POST["testTimeMaxStatus"]);
    @$Project->ShowTestTime = stripslashes_if_gpc_magic_quotes($_POST["showTestTime"]);
    @$Project->EmailMaxItems = stripslashes_if_gpc_magic_quotes($_POST["emailMaxItems"]);
    @$Project->EmailMaxChars = stripslashes_if_gpc_magic_quotes($_POST["emailMaxChars"]);
    @$Project->EmailAdministrator = stripslashes_if_gpc_magic_quotes($_POST["emailAdministrator"]);
    @$Project->ShowIPAddresses = stripslashes_if_gpc_magic_quotes($_POST["showIPAddresses"]);
    @$Project->DisplayLabels = stripslashes_if_gpc_magic_quotes($_POST["displayLabels"]);
    @$Project->ShowCoverageCode = stripslashes_if_gpc_magic_quotes($_POST["showCoverageCode"]);
    @$Project->AutoremoveTimeframe = stripslashes_if_gpc_magic_quotes($_POST["autoremoveTimeframe"]);
    @$Project->AutoremoveMaxBuilds = stripslashes_if_gpc_magic_quotes($_POST["autoremoveMaxBuilds"]);
    $Project->Public = $Public;

    /** Calculate the upload quota */
    if(isset($_POST['uploadQuota']))
      {
      $uploadQuota = $_POST['uploadQuota'];
      }
    else
      {
      $uploadQuota = $CDASH_MAX_UPLOAD_QUOTA;
      }

    if(is_numeric($uploadQuota) && $uploadQuota > 0)
      {
      $Project->UploadQuota = floor(min($uploadQuota, $CDASH_MAX_UPLOAD_QUOTA) * 1024 * 1024 * 1024);
      }

    /** If we are managing clients */
    if($CDASH_MANAGE_CLIENTS)
      {
      @$Project->CTestTemplateScript = stripslashes_if_gpc_magic_quotes($_POST["ctestTemplateScript"]);
      }

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
      $xml .= "<project_name_encoded>".urlencode($Name)."</project_name_encoded>";
      $xml .= "<project_id>$projectid</project_id>";
      $xml .= "<project_created>1</project_created>";
      }
    else
      {
      return;
      }

    // Add the default groups
    $BuildGroup = new BuildGroup();
    $BuildGroup->SetName('Nightly');
    $BuildGroup->SetDescription('Nightly builds');
    $BuildGroup->SetSummaryEmail(0);
    $Project->AddBuildGroup($BuildGroup);

    $BuildGroup = new BuildGroup();
    $BuildGroup->SetName('Continuous');
    $BuildGroup->SetDescription('Continuous builds');
    $BuildGroup->SetSummaryEmail(0);
    $Project->AddBuildGroup($BuildGroup);

    $BuildGroup = new BuildGroup();
    $BuildGroup->SetName('Experimental');
    $BuildGroup->SetDescription('Experimental builds');
    // default to "No Email" for the Experimental group
    $BuildGroup->SetSummaryEmail(2);
    $Project->AddBuildGroup($BuildGroup);

    // Set up overview page to initially contain just the "Nightly" group
    $groups = $Project->GetBuildGroups();
    foreach ($groups as $group)
      {
      if ($group->GetName() == "Nightly")
        {
        $buildgroupid = $group->GetId();
        $query =
          "INSERT INTO overview_components (projectid, buildgroupid, position, type)
           VALUES ('$projectid', '$buildgroupid', '1', 'build')";
        pdo_query($query);
        add_last_sql_error("CreateProject :: DefaultOverview", $projectid);
        break;
        }
      }

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
    $Project->AddRepositories($CVSRepositories, $CVSUsernames, $CVSPasswords,$CVSBranches);

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
        unset($handle);
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

// If we should add a spam filter
@$SpamFilter = stripslashes_if_gpc_magic_quotes($_POST["SpamFilter"]);
if($SpamFilter)
  {
  @$spambuildname = pdo_real_escape_string(stripslashes_if_gpc_magic_quotes($_POST["spambuildname"]));
  @$spamsitename = pdo_real_escape_string(stripslashes_if_gpc_magic_quotes($_POST["spamsitename"]));
  @$spamip = pdo_real_escape_string(stripslashes_if_gpc_magic_quotes($_POST["spamip"]));

  if(!empty($spambuildname) || !empty($spamsitename) || !empty($spamip))
    {
    pdo_query("INSERT INTO blockbuild (projectid,buildname,sitename,ipaddress) VALUES (".qnum($projectid).
              ",'".$spambuildname."','".$spamsitename."','".$spamip."')");
    }
  } // end spam filter


// If we should remove a spam filter
@$RemoveSpamFilter = $_POST["RemoveSpamFilter"];
if($RemoveSpamFilter)
  {
  @$removespam = $_POST["removespam"];
  foreach($removespam as $key => $value)
    {
    if($value == 1)
      {
      pdo_query("DELETE FROM blockbuild WHERE id=".qnum($key));
      }
    }
  } // end spam filter

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
  $Project->Description = stripslashes_if_gpc_magic_quotes($_POST["description"]);
  $Project->HomeUrl = stripHTTP(stripslashes_if_gpc_magic_quotes($_POST["homeURL"]));
  $Project->CvsUrl = stripHTTP(stripslashes_if_gpc_magic_quotes($_POST["cvsURL"]));
  $Project->BugTrackerUrl = stripslashes_if_gpc_magic_quotes($_POST["bugURL"]);
  $Project->BugTrackerFileUrl = stripslashes_if_gpc_magic_quotes($_POST["bugFileURL"]);
  $Project->DocumentationUrl = stripHTTP(stripslashes_if_gpc_magic_quotes($_POST["docURL"]));
  $Project->TestingDataUrl = stripHTTP(stripslashes_if_gpc_magic_quotes($_POST["testingDataUrl"]));
  @$Project->Public = $_POST["public"];
  if(!isset($Project->Public))
    {
    $Project->Public = 0;
    }

  @$Project->CoverageThreshold = stripslashes_if_gpc_magic_quotes($_POST["coverageThreshold"]);
  @$Project->NightlyTime = stripslashes_if_gpc_magic_quotes($_POST["nightlyTime"]);
  @$Project->GoogleTracker = stripslashes_if_gpc_magic_quotes($_POST["googleTracker"]);
  @$Project->EmailBrokenSubmission = stripslashes_if_gpc_magic_quotes($_POST["emailBrokenSubmission"]);
  @$Project->EmailRedundantFailures = stripslashes_if_gpc_magic_quotes($_POST["emailRedundantFailures"]);
  @$Project->EmailLowCoverage = stripslashes_if_gpc_magic_quotes($_POST["emailLowCoverage"]);
  @$Project->EmailTestTimingChanged = stripslashes_if_gpc_magic_quotes($_POST["emailTestTimingChanged"]);
  @$Project->CvsViewerType = stripslashes_if_gpc_magic_quotes($_POST["cvsviewertype"]);
  @$Project->RobotName = stripslashes_if_gpc_magic_quotes($_POST["robotname"]);
  @$Project->RobotRegex = stripslashes_if_gpc_magic_quotes($_POST["robotregex"]);
  @$Project->TestTimeStd = stripslashes_if_gpc_magic_quotes($_POST["testTimeStd"]);
  @$Project->TestTimeStdThreshold = stripslashes_if_gpc_magic_quotes($_POST["testTimeStdThreshold"]);
  @$Project->TestTimeMaxStatus = stripslashes_if_gpc_magic_quotes($_POST["testTimeMaxStatus"]);
  @$Project->TestTimeStdThreshold = stripslashes_if_gpc_magic_quotes($_POST["testTimeStdThreshold"]);
  @$Project->ShowTestTime = stripslashes_if_gpc_magic_quotes($_POST["showTestTime"]);
  @$Project->EmailMaxItems = stripslashes_if_gpc_magic_quotes($_POST["emailMaxItems"]);
  @$Project->EmailMaxChars = stripslashes_if_gpc_magic_quotes($_POST["emailMaxChars"]);
  @$Project->EmailAdministrator = stripslashes_if_gpc_magic_quotes($_POST["emailAdministrator"]);
  @$Project->ShowIPAddresses = stripslashes_if_gpc_magic_quotes($_POST["showIPAddresses"]);
  @$Project->DisplayLabels = stripslashes_if_gpc_magic_quotes($_POST["displayLabels"]);
  @$Project->ShowCoverageCode = stripslashes_if_gpc_magic_quotes($_POST["showCoverageCode"]);
  @$Project->AutoremoveTimeframe = stripslashes_if_gpc_magic_quotes($_POST["autoremoveTimeframe"]);
  @$Project->AutoremoveMaxBuilds = stripslashes_if_gpc_magic_quotes($_POST["autoremoveMaxBuilds"]);

  /** Calculate the upload quota */
  if(isset($_POST['uploadQuota']))
    {
    $uploadQuota = $_POST['uploadQuota'];
    }
  else
    {
    $uploadQuota = $CDASH_MAX_UPLOAD_QUOTA;
    }
  if(is_numeric($uploadQuota) && $uploadQuota > 0)
    {
    $Project->UploadQuota = floor(min($uploadQuota, $CDASH_MAX_UPLOAD_QUOTA) * 1024 * 1024 * 1024);
    }

  /** If we are managing clients */
  if($CDASH_MANAGE_CLIENTS)
    {
    $Project->CTestTemplateScript = stripslashes_if_gpc_magic_quotes($_POST["ctestTemplateScript"]);
    }

  $Project->Save();

  // Add the logo
  if(array_key_exists('logo', $_FILES) && strlen($_FILES['logo']['tmp_name'])>0)
    {
    $handle = fopen($_FILES['logo']['tmp_name'],"r");
    $contents = 0;
    if($handle)
      {
      $contents = addslashes(fread($handle,$_FILES['logo']['size']));
      $filetype = $_FILES['logo']['type'];
      fclose($handle);
      unset($handle);
      }
    $Project->AddLogo($contents,$filetype);
    }

  // Add repositories
  $Project->AddRepositories($_POST["cvsRepository"],
                            $_POST["cvsUsername"],
                            $_POST["cvsPassword"],
                            $_POST["cvsBranch"]);
  }

// List the available projects
$sql = "SELECT id,name FROM project";
if(!$User->IsAdmin())
  {
  $sql .= " WHERE id IN (SELECT projectid AS id FROM user2project WHERE userid='$userid' AND role>0)";
  }
$sql .= " ORDER by name ASC";
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
  $xml .= add_XML_value("name_encoded",urlencode($Project->Name));
  $xml .= add_XML_value("description",$Project->Description);
  $xml .= add_XML_value("homeurl",$Project->HomeUrl);
  $xml .= add_XML_value("cvsurl",$Project->CvsUrl);
  $xml .= add_XML_value("bugurl",$Project->BugTrackerUrl);
  $xml .= add_XML_value("bugfileurl",$Project->BugTrackerFileUrl);
  $xml .= add_XML_value("docurl",$Project->DocumentationUrl);
  $xml .= add_XML_value("public",$Project->Public);
  $xml .= add_XML_value("imageid",$Project->ImageId);
  $xml .= add_XML_value("coveragethreshold",$Project->CoverageThreshold);
  $xml .= add_XML_value("nightlytime",$Project->NightlyTime);
  $xml .= add_XML_value("testingdataurl",$Project->TestingDataUrl);
  $xml .= add_XML_value("googletracker",$Project->GoogleTracker);
  $xml .= add_XML_value("emailbrokensubmission",$Project->EmailBrokenSubmission);
  $xml .= add_XML_value("emailredundantfailures",$Project->EmailRedundantFailures);
  $xml .= add_XML_value("emaillowcoverage",$Project->EmailLowCoverage);
  $xml .= add_XML_value("emailtesttimingchanged",$Project->EmailTestTimingChanged);
  $xml .= add_XML_value("cvsviewertype",$Project->CvsViewerType);
  $xml .= add_XML_value("robotname",$Project->RobotName);
  $xml .= add_XML_value("robotregex",$Project->RobotRegex);
  $xml .= add_XML_value("testtimestd",$Project->TestTimeStd);
  $xml .= add_XML_value("testtimestdthreshold",$Project->TestTimeStdThreshold);
  $xml .= add_XML_value("testtimemaxstatus",$Project->TestTimeMaxStatus);
  $xml .= add_XML_value("showtesttime",$Project->ShowTestTime);
  $xml .= add_XML_value("emailmaxitems",$Project->EmailMaxItems);
  $xml .= add_XML_value("emailmaxchars",$Project->EmailMaxChars);
  $xml .= add_XML_value("emailadministrator",$Project->EmailAdministrator);
  $xml .= add_XML_value("showipaddresses",$Project->ShowIPAddresses);
  $xml .= add_XML_value("displaylabels",$Project->DisplayLabels);
  $xml .= add_XML_value("showcoveragecode",$Project->ShowCoverageCode);
  $xml .= add_XML_value("autoremovetimeframe",$Project->AutoremoveTimeframe);
  $xml .= add_XML_value("autoremovemaxbuilds",$Project->AutoremoveMaxBuilds);
  $xml .= add_XML_value("webapikey",$Project->WebApiKey);
  if(strlen($Project->CTestTemplateScript)>0)
    {
    $xml .= add_XML_value("ctesttemplatescript",$Project->CTestTemplateScript);
    }
  else
    {
    $xml .= add_XML_value("ctesttemplatescript",$Project->getDefaultJobTemplateScript());
    }
  $uploadQuotaGB = $Project->UploadQuota == 0 ? 0 : $Project->UploadQuota / (1024*1024*1024);
  $xml .= add_XML_value('uploadquota', min($uploadQuotaGB, $CDASH_MAX_UPLOAD_QUOTA));
  $xml .= add_XML_value('maxuploadquota', $CDASH_MAX_UPLOAD_QUOTA);
  $xml .= "</project>";

  // Get the spam list
  $spambuilds = $Project->GetBlockedBuilds();
  foreach($spambuilds as $spambuild)
    {
    $xml .= "<blockedbuild>";
    $xml .= add_XML_value("name",$spambuild['buildname']);
    $xml .= add_XML_value("site",$spambuild['sitename']);
    $xml .= add_XML_value("ip",$spambuild['ipaddress']);
    $xml .= add_XML_value("id",$spambuild['id']);
    $xml .= "</blockedbuild>";
    }

  $repositories = $Project->GetRepositories();
  $nRegisteredRepositories = 0;
  $nRepositories = 0;
  foreach($repositories as $repository)
    {
    $xml .= "<cvsrepository>";
    $xml .= add_XML_value("id",$nRepositories);
    $xml .= add_XML_value("url",$repository['url']);
    $xml .= add_XML_value("username",$repository['username']);
    $xml .= add_XML_value("password",$repository['password']);
    $xml .= add_XML_value("branch",$repository['branch']);
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
  } // end projectid>0
else
  {
  $xml .= "<project>";
  // Initialize the variables for creation
  $xml .= add_XML_value("emailbrokensubmission","1");
  $xml .= add_XML_value("showcoveragecode","1");
  $xml .= add_XML_value("autoremovetimeframe","60");
  $xml .= add_XML_value("autoremovemaxbuilds","500");
  $xml .= add_XML_value("uploadquota","1");
  $xml .= "</project>";
  }

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

// Put the repository viewers in alphabetical orders
// first should be lower case
$xml .= AddCVSViewer("cgit","CGit",$Project->CvsViewerType);
$xml .= AddCVSViewer("cvstrac","CVSTrac",$Project->CvsViewerType);
$xml .= AddCVSViewer("fisheye","Fisheye",$Project->CvsViewerType);
$xml .= AddCVSViewer("github","GitHub",$Project->CvsViewerType);
$xml .= AddCVSViewer("gitlab","GitLab",$Project->CvsViewerType);
$xml .= AddCVSViewer("gitorious","Gitorious",$Project->CvsViewerType);
$xml .= AddCVSViewer("gitweb","GitWeb",$Project->CvsViewerType);
$xml .= AddCVSViewer("gitweb2","GitWeb2",$Project->CvsViewerType);
$xml .= AddCVSViewer("hgweb","Hgweb",$Project->CvsViewerType);
$xml .= AddCVSViewer("stash","Atlassian Stash",$Project->CvsViewerType);
$xml .= AddCVSViewer("loggerhead","Loggerhead",$Project->CvsViewerType);
$xml .= AddCVSViewer("p4web","P4Web",$Project->CvsViewerType);
$xml .= AddCVSViewer("redmine","Redmine",$Project->CvsViewerType);
$xml .= AddCVSViewer("allura","SourceForge Allura",$Project->CvsViewerType);
$xml .= AddCVSViewer("trac","Trac",$Project->CvsViewerType);
$xml .= AddCVSViewer("viewcvs","ViewCVS",$Project->CvsViewerType);
$xml .= AddCVSViewer("viewvc","ViewVC",$Project->CvsViewerType);
$xml .= AddCVSViewer("viewvc_1_1","ViewVC1.1",$Project->CvsViewerType);
$xml .= AddCVSViewer("websvn","WebSVN",$Project->CvsViewerType);

$xml .= add_XML_value("nrepositories",$nRepositories); // should be at the end

$xml .= "</cdash>";

// Now doing the xslt transition
generate_XSLT($xml,"createProject");

} // end session
?>
