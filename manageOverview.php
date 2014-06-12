<?php

include("cdash/config.php");
require_once("cdash/pdo.php");
include_once("cdash/common.php");
include('login.php');
include('cdash/version.php');
include_once("models/project.php");
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

$xml = begin_XML_for_XSLT();
$xml .= "<backurl>user.php</backurl>";
$xml .= "<title>CDash - Manage Overview</title>";
$xml .= "<menutitle>CDash</menutitle>";
$xml .= "<menusubtitle>Overview</menusubtitle>";

@$projectid = $_GET["projectid"];
if ($projectid != NULL)
  {
  $projectid = pdo_real_escape_numeric($projectid);
  }
// If the projectid is not set and there is only one project we go directly to the page
$Project = new Project;
if(!isset($projectid))
  {
  $projectids = $Project->GetIds();
  if(count($projectids)==1)
    {
    $projectid = $projectids[0];
    }
  }
if(!isset($projectid))
  {
  echo "No projectid specified";
  return;
  }

$User = new User;
$User->Id = $userid;
$Project->Id = $projectid;

$role = $Project->GetUserRole($userid);

if($User->IsAdmin()===FALSE && $role<=1)
  {
  echo "You don't have the permissions to access this page";
  return;
  }

// check if we are saving an overview layout
if (isset($_POST['saveLayout']))
  {
  $inputRows = json_decode($_POST['saveLayout'], true);

  if (count($inputRows) > 0)
    {
    // remove old overview layout from this project
    pdo_query(
      "DELETE FROM overviewbuildgroups WHERE projectid=" .
        qnum(pdo_real_escape_numeric($projectid)));
    add_last_sql_error("manageOverview::saveLayout::DELETE", $projectid);

    // construct query to insert the new layout
    $query = "INSERT INTO overviewbuildgroups (projectid, buildgroupid, position) VALUES ";
    foreach ($inputRows as $inputRow)
      {
      $query .= "(" .
        qnum(pdo_real_escape_numeric($projectid)) . ", " .
        qnum(pdo_real_escape_numeric($inputRow["buildgroupid"])) . ", " .
        qnum(pdo_real_escape_numeric($inputRow["position"])) . "), ";
      }

    // remove the trailing comma and space, then insert our new values
    $query = rtrim($query, ", ");
    pdo_query($query);
    add_last_sql_error("manageOverview::saveLayout::INSERT", $projectid);
    }

  // since this is called by AJAX, we don't need to render the page below.
  exit(0);
  }

// otherwise generate the .xml to render this page
$xml .= "<project>";
$xml .= add_XML_value("id",$Project->Id);
$xml .= add_XML_value("name",$Project->GetName());
$xml .= add_XML_value("name_encoded",urlencode($Project->GetName()));
$xml .= "</project>";

// Get the groups for this project
$query = "SELECT id, name FROM buildgroup WHERE projectid='$projectid'";
$buildgroup_rows = pdo_query($query);
add_last_sql_error("manageOverview::buildgroups", $projectid);
while($buildgroup_row = pdo_fetch_array($buildgroup_rows))
  {
  $xml .= "<buildgroup>";
  $xml .= add_XML_value("id", $buildgroup_row["id"]);
  $xml .= add_XML_value("name", $buildgroup_row["name"]);
  $xml .= "</buildgroup>";
  }

// Get the groups that are already included in the overview
$query =
  "SELECT bg.id, bg.name FROM overviewbuildgroups AS obg
   LEFT JOIN buildgroup AS bg ON (obg.buildgroupid = bg.id)
   WHERE obg.projectid = " . qnum(pdo_real_escape_numeric($projectid)) . "
   ORDER BY obg.position";
$overviewgroup_rows = pdo_query($query);
add_last_sql_error("manageOverview::overviewgroups", $projectid);
while($overviewgroup_row = pdo_fetch_array($overviewgroup_rows))
  {
  $xml .= "<overviewgroup>";
  $xml .= add_XML_value("id", $overviewgroup_row["id"]);
  $xml .= add_XML_value("name", $overviewgroup_row["name"]);
  $xml .= "</overviewgroup>";
  }

$xml .= "</cdash>";

// Now doing the xslt transition
generate_XSLT($xml,"manageOverview");

} // end session OK
?>

