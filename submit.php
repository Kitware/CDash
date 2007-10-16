<?php
include("ctestparser.php");
include_once("common.php");

$putdata = fopen("php://input", "r");
$contents = "";
$content = fread($putdata,1000);
while($content)
  {
  $contents .= $content;
  $content = fread($putdata,1000);
  }

$projectname = $_GET["project"];
$projectid = get_project_id($projectname);

ctest_parse($contents,$projectid);
?>
