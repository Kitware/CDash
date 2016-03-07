<?php
// If the project name is not set we display the table of projects.
if (!isset($_GET['project'])) {
    header('Location: viewProjects.php');
    exit;
}
require_once dirname(__DIR__).'/config/config.php';
include_once 'include/common.php';
load_view('compareCoverage');
