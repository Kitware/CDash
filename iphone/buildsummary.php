<?php
$NoXSLGenerate = 1;
$FormatTextForIphone = 1;

@$buildid = $_GET["project"];
@$date = $_GET["date"];

include("../buildSummary.php");

//$xml = generate_main_dashboard_XML($projectid,$date);
// Now doing the xslt transition
generate_XSLT($xml,"buildsummary");
?>