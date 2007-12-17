<?php
$NoXSLGenerate = 1;
include("../index.php");

@$projectname = $_GET["project"];
$projectid = get_project_id($projectname);
@$date = $_GET["date"];
$xml = generate_main_dashboard_XML($projectid,$date);
// Now doing the xslt transition
generate_XSLT($xml,"project");
?>