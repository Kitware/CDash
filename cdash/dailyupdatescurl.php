<?php
include("cdash/dailyupdates.php");
  
$projectid = $_GET['projectid'];
addDailyChanges($projectid);
?>
