<?php
include("dailyupdates.php");
  
$projectid = $_GET['projectid'];
addDailyChanges($projectid);
?>
