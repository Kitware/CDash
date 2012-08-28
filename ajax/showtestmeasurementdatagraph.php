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
$cdashpath = str_replace('\\', '/', dirname(dirname(__FILE__)));
set_include_path($cdashpath . PATH_SEPARATOR . get_include_path());

require_once("../cdash/config.php");
require_once("../cdash/pdo.php");
require_once("../cdash/common.php");

$testid = $_GET["testid"];
$buildid = $_GET["buildid"];
$measurementname = $_GET["measurement"];


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
if(!isset($measurementname) || !is_string($measurementname))
  {
  echo "Not a valid measurementname!";
  return;
  }

$db = pdo_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN","$CDASH_DB_PASS");
pdo_select_db("$CDASH_DB_NAME",$db);

// Find the project variables
$test = pdo_query("SELECT name FROM test WHERE id='$testid'");
$test_array = pdo_fetch_array($test);
$testname = $test_array["name"];


$build = pdo_query("SELECT name,type,siteid,projectid,starttime
FROM build WHERE id='$buildid'");
$build_array = pdo_fetch_array($build);

$buildname = $build_array["name"];
$siteid = $build_array["siteid"];
$buildtype = $build_array["type"];
$starttime = $build_array["starttime"];
$projectid = $build_array["projectid"];

// Find the other builds
$previousbuilds = pdo_query("SELECT build.id,build.starttime,build2test.testid,testmeasurement.value
FROM build
JOIN build2test ON (build.id = build2test.buildid)
JOIN testmeasurement ON(build2test.testid = testmeasurement.testid)
WHERE testmeasurement.name = '$measurementname'
AND build.siteid = '$siteid'
AND build.projectid = '$projectid'
AND build.starttime <= '$starttime'
AND build.type = '$buildtype'
AND build.name = '$buildname'
AND build2test.testid IN (SELECT id FROM test WHERE name = '$testname')
ORDER BY build.starttime DESC
");

?>

&nbsp;
<script language="javascript" type="text/javascript">
$(function () {
    var d1 = [];
    var buildids = [];
    var testids = [];
    <?php
    $tarray = array();
    while($build_array = pdo_fetch_array($previousbuilds))
      {
      $t['x'] = strtotime($build_array["starttime"])*1000;
      $t['y'] = $build_array["value"];
      $t['builid'] = $build_array["id"];
      $t['testid'] = $build_array["testid"];

      $tarray[]=$t;
      }

    $tarray = array_reverse($tarray);
    foreach($tarray as $axis)
  {
  ?>
      buildids[<?php echo $axis['x']; ?>]=<?php echo $axis['builid']; ?>;
      testids[<?php echo $axis['x']; ?>]=<?php echo $axis['testid']; ?>;
      d1.push([<?php echo $axis['x']; ?>,<?php echo $axis['y']; ?>]);
    <?php
      $t = $axis['x'];
      } ?>

  var options = {
    //bars: { show: true,  barWidth: 35000000, lineWidth:0.9  },
    lines: { show: true },
    points: { show: true },
    xaxis: { mode: "time"},
    grid: {backgroundColor: "#fffaff",
      clickable: true,
      hoverable: true,
      hoverFill: '#444',
      hoverRadius: 4
    },
    selection: { mode: "x" },
    colors: ["#0000FF", "#dba255", "#919733"]
  };

    var divname = <? echo("\"#$measurementname"."grapholder\""); ?>;

    $(divname).bind("selected", function (event, area) {
    plot = $.plot($(divname), [{label: <? echo("\"$measurementname\""); ?>,  data: d1}],
           $.extend(true, {}, options, {xaxis: { min: area.x1, max: area.x2 }}));

    });

    $(divname).bind("plotclick", function (e, pos, item) {
        if (item) {
            plot.highlight(item.series, item.datapoint);
            buildid = buildids[item.datapoint[0]];
            testid = testids[item.datapoint[0]];
            window.location = "testDetails.php?test="+testid+"&build="+buildid;
            }
     });

    plot = $.plot($(divname),
                  [{label: <? echo("\"$measurementname\""); ?> ,data: d1}],
                  $.extend(true,{}, options, {xaxis: { min: <?php echo $t-2000000000?>, max: <?php echo $t+50000000; ?>}})
                 );
});


</script>
