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

require_once("cdash/config.php");
require_once("cdash/pdo.php");
require_once("cdash/common.php");

$testid = $_GET["testid"];
$buildid = $_GET["buildid"];
$zoomout = $_GET["zoomout"];
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
$previousbuilds = pdo_query("SELECT
build.id,build.starttime,build2test.testid,testmeasurement.value
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

    $tarray = array();
    while($build_array = pdo_fetch_array($previousbuilds))
      {
      $t['x'] = strtotime($build_array["starttime"])*1000;
    $time[] = date("Y-m-d H:i:s",strtotime($build_array["starttime"]));
      $t['y'] = $build_array["value"];
      $t['builid'] = $build_array["id"];
      $t['testid'] = $build_array["testid"];

      $tarray[]=$t;
      }
    if($_GET['export']=="csv") // If user wants to export as CSV file
  {
  header("Cache-Control: public");
  header("Content-Description: File Transfer");
  $exportfilename = $measurementname.".csv";
  header("Content-Disposition: attachment; filename=".$exportfilename); // Prepare some headers to download
  header("Content-Type: application/octet-stream;");
  header("Content-Transfer-Encoding: binary");
  $filecontent = "Date;$measurementname\n"; // Standard columns
  for($c=0;$c<count($tarray);$c++) $filecontent .= "{$time[$c]};{$tarray[$c]['y']}\n";
  echo ($filecontent); // Start file download
  die; // to suppress unwanted output

  }
?>
&nbsp;
<script language="javascript" type="text/javascript">
$(function () {
    var d1 = [];
    var buildids = [];
    var testids = [];
    <?php
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

    var divname = '#graph_holder';

    $(divname).bind("selected", function (event, area) {
    plot = $.plot($(divname), [{label: <?php echo("\"$measurementname\""); ?>, data: d1}],
           $.extend(true, {}, options, {xaxis: { min: area.x1, max: area.x2 }, yaxis: { min: 0}}));

    });

    $(divname).bind("plotclick", function (e, pos, item) {
        if (item) {
            plot.highlight(item.series, item.datapoint);
            buildid = buildids[item.datapoint[0]];
            testid = testids[item.datapoint[0]];
            window.location = "testDetails.php?test="+testid+"&build="+buildid;
            }
     });

<?php if(isset($zoomout))
{
?>
  plot = $.plot($(divnplot = $.plot($(divname),
                  [{label: <?php echo("\".$measurementname\""); ?> ,data: d1}], options))
          );
<?php } else { ?>
    plot = $.plot($(divname),
                  [{label: <?php echo("\".$measurementname\""); ?> ,data: d1}],
                  $.extend(true,{}, options, {xaxis: { min: <?php echo $t-2000000000?>, max: <?php echo $t+50000000; ?>}, yaxis: { min: 0}})
                 );
<?php }
?>
});
</script>
