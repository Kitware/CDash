<?php
// To be able to access files in this CDash installation regardless
// of getcwd() value:
//
$cdashpath = dirname(dirname(__FILE__));
set_include_path($cdashpath . PATH_SEPARATOR . get_include_path());

require_once("cdash/dailyupdates.php");

ob_start();
set_time_limit(0);
ignore_user_abort(TRUE);

$projectid = $_GET['projectid'];
addDailyChanges($projectid);
?>
