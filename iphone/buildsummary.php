<?php
// Put the CDash root directory in the path
$path = join(array_slice(split( "/" ,dirname(__FILE__)),0,-1),"/");
set_include_path(get_include_path() . PATH_SEPARATOR . $path);

$NoXSLGenerate = 1;
$FormatTextForIphone = 1;

@$buildid = $_GET["project"];
@$date = $_GET["date"];

include("../buildSummary.php");

//$xml = generate_main_dashboard_XML($projectid,$date);
// Now doing the xslt transition
generate_XSLT($xml,"buildsummary");
?>