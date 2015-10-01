<?php
include_once("api_setpath.php");
include("cdash/config.php");
require_once("cdash/pdo.php");
include("cdash/common.php");
require_once("models/project.php");

set_time_limit(0);

@$projectname = $_GET["project"];
$projectname = htmlspecialchars(pdo_real_escape_string($projectname));
$projectid = get_project_id($projectname);
$Project = new Project();
$Project->Id = $projectid;
$Project->Fill();

@$date = $_GET["date"];
if ($date != NULL)
  {
  $date = htmlspecialchars(pdo_real_escape_string($date));
  }

echo_subprojects_dashboard_JSON($Project, $date);


// Gather up the data for a SubProjects dashboard.
function echo_subprojects_dashboard_JSON($project_instance, $date)
{
  $start = microtime_float();
  $noforcelogin = 1;
  include_once("cdash/config.php");
  require_once("cdash/pdo.php");
  include('login.php');
  include_once("models/banner.php");
  include_once("models/subproject.php");

  $response = array();

  $db = pdo_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN","$CDASH_DB_PASS");
  if(!$db)
    {
    $response['error'] = "Error connecting to CDash database server";
    echo json_encode($response);
    return;
    }
  if(!pdo_select_db("$CDASH_DB_NAME",$db))
    {
    $response['error'] = "Error selecting CDash database";
    echo json_encode($response);
    return;
    }

  $Project = $project_instance;
  $projectid = $project_instance->Id;

  $homeurl = make_cdash_url(htmlentities($Project->HomeUrl));

  if (!checkUserPolicy(@$_SESSION['cdash']['loginid'], $projectid, 1))
    {
    $response['requirelogin'] = 1;
    echo json_encode($response);
    return;
    }

  $response = begin_JSON_response();

  $response['title'] = "CDash - " . $Project->Name;

  $banners = array();
  $Banner = new Banner;
  $Banner->SetProjectId(0);
  $text = $Banner->GetText();
  if($text !== false)
    {
    $banners[] = $text;
    }

  $Banner->SetProjectId($projectid);
  $text = $Banner->GetText();
  if($text !== false)
    {
    $banners[] = $text;
    }
  $response['banners'] = $banners;

  global $CDASH_SHOW_LAST_SUBMISSION;
  if ($CDASH_SHOW_LAST_SUBMISSION)
    {
    $response['showlastsubmission'] = 1;
    }

  list ($previousdate, $currentstarttime, $nextdate) = get_dates($date,$Project->NightlyTime);

  $vcsurl = make_cdash_url(htmlentities($Project->CvsUrl));
  $homeurl = make_cdash_url(htmlentities($Project->HomeUrl));
  $bugurl = make_cdash_url(htmlentities($Project->BugTrackerUrl));
  $googletracker = htmlentities($Project->GoogleTracker);
  $docurl = make_cdash_url(htmlentities($Project->DocumentationUrl));

  // Main dashboard section
  $projectname_encoded = urlencode($Project->Name);
  $response = array();
  $response['datetime'] = date("l, F d Y H:i:s T",time());
  $response['date'] = $date;
  $response['unixtimestamp'] = $currentstarttime;
  $response['vcs'] = $vcsurl;
  $response['bugtracker'] = $bugurl;
  $response['googletracker'] = $googletracker;
  $response['documentation'] = $docurl;
  $response['logoid'] = $Project->getLogoID();
  $response['projectid'] = $projectid;
  $response['projectname'] = $Project->Name;
  $response['projectname_encoded'] = $projectname_encoded;
  $response['previousdate'] = $previousdate;
  $response['projectpublic'] = $Project->Public;
  $response['nextdate'] = $nextdate;

  if(empty($Project->HomeUrl))
    {
    $response['home'] =
      "index.php?project=".urlencode($Project->Name);
    }
  else
    {
    $response['home'] = $homeurl;
    }

  if($CDASH_USE_LOCAL_DIRECTORY && file_exists("local/models/proProject.php"))
    {
    include_once("local/models/proProject.php");
    $pro= new proProject;
    $pro->ProjectId=$projectid;
    $response['proedition'] = $pro->GetEdition(1);
    }

  if($currentstarttime>time())
    {
    $response['future'] = 1;
    }
  else
    {
    $response['future'] = 0;
    }

  $linkparams = "project=" . urlencode($Project->Name);
  if (!empty($date))
    {
    $linkparams .= "&date=$date";
    }
  $response['linkparams'] = $linkparams;

  // Menu definition
  $menu_response = array();
  $menu_response['subprojects'] = 1;
  $menu_response['previous'] = "viewSubProjects.php?project=$projectname_encoded&date=$previousdate";
  $menu_response['current'] = "viewSubProjects.php?project=$projectname_encoded";
  if(!has_next_date($date, $currentstarttime))
    {
    $menu_response['nonext'] = 1;
    }
  else
    {
    $menu_response['next'] = "viewSubProjects.php?project=$projectname_encoded&date=$nextdate";
    }
  $response['menu'] = $menu_response;

  $beginning_timestamp = $currentstarttime;
  $end_timestamp = $currentstarttime+3600*24;

  $beginning_UTCDate = gmdate(FMT_DATETIME,$beginning_timestamp);
  $end_UTCDate = gmdate(FMT_DATETIME,$end_timestamp);

  // User
  if(isset($_SESSION['cdash']))
    {
    $user_response = array();
    $userid = $_SESSION['cdash']['loginid'];
    $user2project = pdo_query("SELECT role FROM user2project WHERE userid='$userid' and projectid='$projectid'");
    $user2project_array = pdo_fetch_array($user2project);
    $user = pdo_query("SELECT admin FROM ".qid("user")."  WHERE id='$userid'");
    $user_array = pdo_fetch_array($user);
    $user_response['id'] = $userid;
    $response['userid'] = $userid;
    $isadmin=0;
    if($user2project_array["role"]>1 || $user_array["admin"])
      {
      $isadmin=1;
       }
    $user_response['admin'] = $isadmin;
    $user_response['projectrole'] = $user2project_array['role'];
    $response['user'] = $user_response;
    }

  // Get some information about the project
  $project_response = array();
  $project_response['nbuilderror'] =
    $Project->GetNumberOfErrorBuilds($beginning_UTCDate, $end_UTCDate, true);
  $project_response['nbuildwarning'] =
    $Project->GetNumberOfWarningBuilds($beginning_UTCDate, $end_UTCDate, true);
  $project_response['nbuildpass'] =
    $Project->GetNumberOfPassingBuilds($beginning_UTCDate, $end_UTCDate, true);
  $project_response['nconfigureerror'] =
    $Project->GetNumberOfErrorConfigures($beginning_UTCDate, $end_UTCDate, true);
  $project_response['nconfigurewarning'] =
    $Project->GetNumberOfWarningConfigures($beginning_UTCDate, $end_UTCDate, true);
  $project_response['nconfigurepass'] =
    $Project->GetNumberOfPassingConfigures($beginning_UTCDate,$end_UTCDate, true);
  $project_response['ntestpass'] =
    $Project->GetNumberOfPassingTests($beginning_UTCDate,$end_UTCDate, true);
  $project_response['ntestfail'] =
    $Project->GetNumberOfFailingTests($beginning_UTCDate,$end_UTCDate, true);
  $project_response['ntestnotrun'] =
    $Project->GetNumberOfNotRunTests($beginning_UTCDate,$end_UTCDate, true);
  if(strlen($Project->GetLastSubmission()) == 0)
    {
    $project_response['lastsubmission'] = "NA";
    }
  else
    {
    $project_response['lastsubmission'] = $Project->GetLastSubmission();
    }
  $response['project'] = $project_response;

  // Look for the subproject
  $row=0;
  $subprojectids = $Project->GetSubProjects();
  $subprojProp = array();
  foreach($subprojectids as $subprojectid)
    {
    $SubProject = new SubProject();
    $SubProject->SetId($subprojectid);
    $subprojProp[$subprojectid] = array('name'=>$SubProject->GetName());
    }
  $testSubProj = new SubProject();
  $result = $testSubProj->GetNumberOfErrorBuilds($beginning_UTCDate, $end_UTCDate, True);
  if ($result)
    {
    foreach ($result as $row)
      {
      $subprojProp[$row['subprojectid']]['nbuilderror'] = intval($row[1]);
      }
    }
  $result = $testSubProj->GetNumberOfWarningBuilds($beginning_UTCDate, $end_UTCDate, True);
  if ($result)
    {
    foreach ($result as $row)
      {
      $subprojProp[$row['subprojectid']]['nbuildwarning'] = intval($row[1]);
      }
    }
  $result = $testSubProj->GetNumberOfPassingBuilds($beginning_UTCDate, $end_UTCDate, True);
  if ($result)
    {
    foreach ($result as $row)
      {
      $subprojProp[$row['subprojectid']]['nbuildpass'] = intval($row[1]);
      }
    }
  $result = $testSubProj->GetNumberOfErrorConfigures($beginning_UTCDate, $end_UTCDate, True);
  if ($result)
    {
    foreach ($result as $row)
      {
      $subprojProp[$row['subprojectid']]['nconfigureerror'] = intval($row[1]);
      }
    }
  $result = $testSubProj->GetNumberOfWarningConfigures($beginning_UTCDate, $end_UTCDate, True);
  if ($result)
    {
    foreach ($result as $row)
      {
      $subprojProp[$row['subprojectid']]['nconfigurewarning'] = intval($row[1]);
      }
    }
  $result = $testSubProj->GetNumberOfPassingConfigures($beginning_UTCDate, $end_UTCDate, True);
  if ($result)
    {
    foreach ($result as $row)
      {
      $subprojProp[$row['subprojectid']]['nconfigurepass'] = intval($row[1]);
      }
    }
  $result = $testSubProj->GetNumberOfPassingTests($beginning_UTCDate, $end_UTCDate, True);
  if ($result)
    {
    foreach ($result as $row)
      {
      $subprojProp[$row['subprojectid']]['ntestpass'] = intval($row[1]);
      }
    }
  $result = $testSubProj->GetNumberOfFailingTests($beginning_UTCDate, $end_UTCDate, True);
  if ($result)
    {
    foreach ($result as $row)
      {
      $subprojProp[$row['subprojectid']]['ntestfail'] = intval($row[1]);
      }
    }
  $result = $testSubProj->GetNumberOfNotRunTests($beginning_UTCDate, $end_UTCDate, True);
  if ($result)
    {
    foreach ($result as $row)
      {
      $subprojProp[$row['subprojectid']]['ntestnotrun'] = intval($row[1]);
      }
    }
  $reportArray = array('nbuilderror', 'nbuildwarning', 'nbuildpass',
                       'nconfigureerror', 'nconfigurewarning', 'nconfigurepass',
                       'ntestpass', 'ntestfail', 'ntestnotrun');
  $subprojects_response = array();
  foreach($subprojectids as $subprojectid)
    {
    $SubProject = new SubProject();
    $SubProject->SetId($subprojectid);
    $subproject_response = array();
    $subproject_response['name'] = $SubProject->GetName();
    $subproject_response['name_encoded'] = urlencode($SubProject->GetName());

    foreach ($reportArray as $reportnum)
      {
      $reportval = array_key_exists($reportnum, $subprojProp[$subprojectid]) ?
                   $subprojProp[$subprojectid][$reportnum] : 0;
      $subproject_response[$reportnum] = $reportval;
      }
    if(strlen($SubProject->GetLastSubmission()) == 0)
      {
      $subproject_response['lastsubmission'] = "NA";
      }
    else
      {
      $subproject_response['lastsubmission'] = $SubProject->GetLastSubmission();
      }
    $subprojects_response[] = $subproject_response;
    } // end for each subproject
  $response['subprojects'] = $subprojects_response;

  $end = microtime_float();
  $response['generationtime'] = round($end-$start, 3);

  echo json_encode($response);
}

?>
