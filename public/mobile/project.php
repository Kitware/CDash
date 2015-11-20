<?php
require_once(dirname(dirname(__DIR__))."/config/config.php");
$NoXSLGenerate = 1;
include("old_index.php");
include_once("models/project.php");

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
