<?php

// If the project name is not set we display the table of projects.
if (!isset($_GET["project"])) {
    header('Location: viewProjects.php');
    exit;
}

include_once("cdash/common.php");
load_view("index");
