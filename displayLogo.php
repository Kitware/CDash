<?php
include "config.php";

$projectid = $_GET["projectid"];

$db = mysql_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN","$CDASH_DB_PASS");
mysql_select_db("$CDASH_DB_NAME",$db);

$project = mysql_query("SELECT logo FROM project WHERE id='$projectid '");
$project_array = mysql_fetch_array($project);

header("Content-type: image/jpeg");
print $project_array["logo"];
exit ();

?>
