<?php
// Put the CDash root directory in the path
$path = join(array_slice(split( "/" ,dirname(__FILE__)),0,-1),"/");
set_include_path(get_include_path() . PATH_SEPARATOR . $path);

$NoXSLGenerate = 1;
include("../index.php");
include("../models/project.php");

@$projectname = $_GET["project"];
$projectid = get_project_id($projectname);
$Project = new Project();
$Project->Id = $projectid;

@$date = $_GET["date"];
$xml = generate_main_dashboard_XML($projectid,$date);
// Now doing the xslt transition
generate_XSLT($xml,"project");
?>