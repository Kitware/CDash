<?php
require_once(dirname(dirname(__DIR__))."/config/config.php");
require_once("include/dailyupdates.php");

$projectid = pdo_real_escape_numeric($_GET['projectid']);
addDailyChanges($projectid);
