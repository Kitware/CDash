<?php
// Put the CDash root directory in the path
$splitchar = '/';
if (DIRECTORY_SEPARATOR == '\\') {
    $splitchar='\\\\';
}
$path = join(array_slice(split($splitchar, dirname(__FILE__)), 0, -1), DIRECTORY_SEPARATOR);
set_include_path(get_include_path() . PATH_SEPARATOR . $path);

$NoXSLGenerate = 1;
include("old_index.php");
include_once("../models/project.php");

@$projectname = $_GET["project"];
if ($projectname != null) {
    $projectname = htmlspecialchars(pdo_real_escape_string($projectname));
}

$projectid = get_project_id($projectname);
$Project = new Project();
$Project->Id = $projectid;
$Project->Fill();

@$date = $_GET["date"];
if ($date != null) {
    $date = htmlspecialchars(pdo_real_escape_string($date));
}

$xml = generate_main_dashboard_XML($Project, $date);
// Now doing the xslt transition
generate_XSLT($xml, "project");
