<?php
require_once(dirname(dirname(__DIR__))."/config/config.php");
require_once("include/pdo.php");

@$buildid = $_GET["buildid"];
// Checks
if (!isset($buildid) || !is_numeric($buildid)) {
    echo "Not a valid buildid!";
    return;
}

$NoXSLGenerate = 1;
$FormatTextForIphone = 1;

@$buildid = $_GET["project"];
if ($buildid != null) {
    $buildid = pdo_real_escape_numeric($buildid);
}

@$date = $_GET["date"];
if ($date != null) {
    $date = htmlspecialchars(pdo_real_escape_string($date));
}

include("web/buildSummary.php");

// Now doing the xslt transition
generate_XSLT($xml, "buildsummary");
