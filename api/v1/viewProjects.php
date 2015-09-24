<?php

$noforcelogin = 1;
include_once("api_setpath.php");
include("cdash/config.php");
require_once("cdash/pdo.php");
include('login.php');
include_once('models/banner.php');

$response = begin_JSON_response();

$Banner = new Banner;
$Banner->SetProjectId(0);
$text = $Banner->GetText();
if($text !== false)
  {
  $response['banner'] = $text;
  }

$response['hostname'] = $_SERVER['SERVER_NAME'];
$response['date'] = date("r");

// Check if the database is up to date
$query = "SELECT * FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA = '$CDASH_DB_NAME'
          AND TABLE_NAME = 'buildfailuredetails'
          AND COLUMN_NAME = 'id'";
$dbTest = pdo_single_row_query($query);
if (empty($dbTest))
  {
  $response['upgradewarning'] = 1;
  }

$response['title'] = $CDASH_MAININDEX_TITLE;
$response['subtitle'] = $CDASH_MAININDEX_SUBTITLE;
$response['googletracker'] = $CDASH_DEFAULT_GOOGLE_ANALYTICS;
if (isset($CDASH_NO_REGISTRATION) && $CDASH_NO_REGISTRATION==1)
 {
 $response['noregister'] = 1;
 }

// User
$userid = 0;
if(isset($_SESSION['cdash']) && isset($_SESSION['cdash']['loginid']))
  {
  $userid = $_SESSION['cdash']['loginid'];
  $user = pdo_query("SELECT admin FROM ".qid("user")." WHERE id='$userid'");
  $user_array = pdo_fetch_array($user);
  $user_response = array();
  $user_response['id'] = $userid;
  $user_response['admin'] = $user_array["admin"];
  $response['user'] = $user_response;
  }

if(isset($_GET['allprojects']) && $_GET['allprojects'] == 1)
  {
  $response['allprojects'] = 1;
  }
else
  {
  $response['allprojects'] = 0;
  }
$showallprojects = $response['allprojects'];
$response['nprojects'] = get_number_public_projects();

$projects = get_projects(!$showallprojects);
$projects_response = array();
foreach($projects as $project)
  {
  $project_response = array();
  $project_response['name'] = $project['name'];
  $name_encoded = urlencode($project['name']);
  $project_response['name_encoded'] = $name_encoded;
  $project_response['description'] = $project['description'];
  if ($project['numsubprojects'] == 0)
    {
    $project_response['link'] = "index.php?project=$name_encoded";
    }
  else
    {
    $project_response['link'] = "viewSubProjects.php?project=$name_encoded";
    }

  if($project['last_build'] == "NA")
    {
    $project_response['lastbuild'] = 'NA';
    }
  else
    {
    $lastbuild = strtotime($project['last_build']. "UTC");
    $project_response['lastbuild'] = date(FMT_DATETIMEDISPLAY, $lastbuild);
    $project_response['lastbuilddate'] = date(FMT_DATE, $lastbuild);
    $project_response['lastbuild_elapsed'] =
      time_difference(time() - $lastbuild, false, 'ago');
    $project_response['lastbuilddatefull'] = $lastbuild;
    }

  if(!isset($project['nbuilds']) || $project['nbuilds'] == 0)
    {
    $project_response['activity'] = 'none';
    }
  else if($project['nbuilds'] < 20) // 2 builds day
    {
    $project_response['activity'] = 'low';
    }
  else if($project['nbuilds'] < 70) // 10 builds a day
    {
    $project_response['activity'] = 'medium';
    }
  else if($project['nbuilds'] >= 70)
    {
    $project_response['activity'] = 'high';
    }

  $projects_response[] = $project_response;
  }
$response['projects'] = $projects_response;

echo json_encode($response);

?>

