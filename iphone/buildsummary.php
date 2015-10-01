<?php
$cdashpath = str_replace('\\', '/', dirname(dirname(__FILE__)));
set_include_path($cdashpath . PATH_SEPARATOR . get_include_path());

require_once("cdash/pdo.php");

@$buildid = $_GET["buildid"];
// Checks
if (!isset($buildid) || !is_numeric($buildid)) {
    echo "Not a valid buildid!";
    return;
}

// Put the CDash root directory in the path
$splitchar = '/';
if (DIRECTORY_SEPARATOR == '\\') {
    $splitchar='\\\\';
}
$path = join(array_slice(split($splitchar, dirname(__FILE__)), 0, -1), DIRECTORY_SEPARATOR);
set_include_path(get_include_path() . PATH_SEPARATOR . $path);

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

include("../buildSummary.php");

// Now doing the xslt transition
generate_XSLT($xml, "buildsummary");
