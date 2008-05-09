<?php
/*=========================================================================

  Program:   CDash - Cross-Platform Dashboard System
  Module:    $Id$
  Language:  PHP
  Date:      $Date$
  Version:   $Revision$

  Copyright (c) 2002 Kitware, Inc.  All rights reserved.
  See Copyright.txt or http://www.cmake.org/HTML/Copyright.html for details.

     This software is distributed WITHOUT ANY WARRANTY; without even 
     the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR 
     PURPOSE.  See the above copyright notices for more information.

=========================================================================*/
include("../config.php");
include("../common.php");

$testid = $_GET["testid"];
$buildid = $_GET["buildid"];
@$zoomout = $_GET["zoomout"];

if(!isset($buildid) || !is_numeric($buildid))
  {
  echo "Not a valid buildid!";
  return;
  }
if(!isset($testid) || !is_numeric($testid))
  {
  echo "Not a valid testid!";
  return;
  }
  
$db = mysql_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN","$CDASH_DB_PASS");
mysql_select_db("$CDASH_DB_NAME",$db);

// Find the project variables
$test = mysql_query("SELECT name FROM test WHERE id='$testid'");
$test_array = mysql_fetch_array($test);
$testname = $test_array["name"];

$build = mysql_query("SELECT name,type,siteid,projectid,starttime FROM build WHERE id='$buildid'");
$build_array = mysql_fetch_array($build);

$buildname = $build_array["name"];
$siteid = $build_array["siteid"];
$buildtype = $build_array["type"];
$starttime = $build_array["starttime"];
$projectid = $build_array["projectid"];


// Find the other builds
$previousbuilds = mysql_query("SELECT build.id, build.starttime, build2test.status
FROM build
JOIN build2test ON (build.id = build2test.buildid)
WHERE build.siteid = '$siteid'
AND build.projectid = '$projectid'
AND build.starttime <= '$starttime'
AND build.type = '$buildtype'
AND build.name = '$buildname'
AND build2test.testid IN (SELECT id FROM test WHERE name = '$testname')
ORDER BY build.starttime DESC
");
?>

    
<br>
<script id="source" language="javascript" type="text/javascript">
$(function () {
  var d1 = [];
  var ty = [];
  ty.push([-1,"Failed"]);
  ty.push([1,"Passed"]);
  
  <?php
    $tarray = array();
    while($build_array = mysql_fetch_array($previousbuilds))
      {
      $t['x'] = strtotime($build_array["starttime"])*1000; 
      if(strtolower($build_array["status"]) == "passed")
        {
        $t['y'] = 1;
        }
      else
        {
        $t['y'] = -1;
        }
      $tarray[]=$t;
    ?>
    <?php
      }
    
    $tarray = array_reverse($tarray);
    foreach($tarray as $axis)
      {
    ?>
      d1.push([<?php echo $axis['x']; ?>,<?php echo $axis['y']; ?>]);
    <?php 
      $t = $axis['x'];
      } ?>

  var options = {
    bars: { show: true,
      barWidth: 35000000,
      lineWidth: 0.9 
      },
    //points: { show: true },
    yaxis: { ticks: ty, min: -1.2, max: 1.2 }, 
    xaxis: { mode: "time" }, 
    grid: {backgroundColor: "#fffaff"},
    selection: { mode: "x" },
    colors: ["#0000FF", "#dba255", "#919733"]
  };
  
  $("#passinggrapholder").bind("selected", function (event, area) {
  $.plot($("#passinggrapholder"), [{label: "Failed/Passed",  data: d1}],
         $.extend(true, {}, options, {xaxis: { min: area.x1, max: area.x2 }}));
  });

<?php if(isset($zoomout))
{
?>
  $.plot($("#passinggrapholder"), [{label: "Failed/Passed",  data: d1}],options);
<?php } else { ?>
  $.plot($("#passinggrapholder"), [{label: "Failed/Passed",  data: d1}],
$.extend(true,{},options,{xaxis: { min: <?php echo $t-2000000000?>,max: <?php echo $t+50000000 ?>}} )); 
<?php } ?>
});


</script>
