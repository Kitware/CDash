<?php
/*=========================================================================

  Program:   CDash - Cross-Platform Dashboard System
  Module:    $Id: showtestmeasurementdatagraphviewerphp.php 3363 2013-09-08 17:44:41Z jjomier $
  Language:  PHP
  Date:      $Date: 2013-09-08 19:44:41 +0200 (Sun, 08 Sep 2013) $
  Version:   $Revision: 3363 $

  Copyright (c) 2002 Kitware, Inc.  All rights reserved.
  See Copyright.txt or http://www.cmake.org/HTML/Copyright.html for details.

     This software is distributed WITHOUT ANY WARRANTY; without even
     the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR
     PURPOSE.  See the above copyright notices for more information.

  Copyright (c) 2014 Volkan Gezer <volkangezer@gmail.com>
=========================================================================*/
$cdashpath = str_replace('\\', '/', dirname(dirname(__FILE__)));
set_include_path($cdashpath . PATH_SEPARATOR . get_include_path());

require_once("cdash/config.php");
require_once("cdash/pdo.php");
require_once("cdash/common.php");

$testname = pdo_real_escape_numeric($_GET["test"]);
$sitename = pdo_real_escape_numeric($_GET["site"]);
$graph_nr = pdo_real_escape_numeric($_GET["graph"]);
$starttime = pdo_real_escape_numeric($_GET["starttime"]);
$endtime = pdo_real_escape_numeric($_GET["endtime"]);
@$zoomout = $_GET["zoomout"];
$measurementname = htmlspecialchars(pdo_real_escape_string($_GET["measurement"]));


if(!isset($sitename))
  {
  echo "Not a valid build!";
  return;
  }
if(!isset($testname))
  {
  echo "Not a valid test!";
  return;
  }
if(!isset($measurementname) || !is_string($measurementname))
  {
  echo "Not a valid measurementname!";
  return;
  }

$db = pdo_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN","$CDASH_DB_PASS");
pdo_select_db("$CDASH_DB_NAME",$db);

// Find the other builds
$previousbuilds = "SELECT test.id, testmeasurement.name AS mname,
            test.name AS tname, site.name AS site, build2test.buildid,
            testmeasurement.value, build.starttime, build.endtime
            FROM (test, site, build)
            JOIN testmeasurement ON (testmeasurement.testid = test.id)
            JOIN build2test ON (build2test.buildid = build.id AND test.id = build2test.testid)
            WHERE site.id = build.siteid AND testmeasurement.type LIKE '%numeric%'
            AND test.name='$testname' AND testmeasurement.name='$measurementname' AND site.name='$sitename'
            ";

if($starttime !== '')
  {
  $previousbuilds .= " AND build.starttime >= '$starttime'";
  }
if($endtime !== '')
  {
  $previousbuilds .= " AND build.endtime <= '$endtime'";
  }

$previousbuilds=pdo_query($previousbuilds);

    $tarray = array();
    while($build_array = pdo_fetch_array($previousbuilds))
      {
      $t['x'] = strtotime($build_array["starttime"])*1000;
      $time[] = date("Y-m-d H:i:s",strtotime($build_array["starttime"]));
      $t['y'] = $build_array["value"];
      $t['builid'] = $build_array["buildid"];
      $t['testid'] = $build_array["id"];

      $tarray[]=$t;
      }

    if(@$_GET['export']=="csv") // If user wants to export as CSV file
  {
  header("Cache-Control: public");
  header("Content-Description: File Transfer");
  header("Content-Disposition: attachment; filename=".$testname."_".$measurementname.".csv"); // Prepare some headers to download
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
    selection: { mode: "xy" },
    colors: ["#0000FF", "#dba255", "#919733"]
  };

    var divname = '#graph_holder_<?php echo($graph_nr)?>';

    $(divname).bind("selected", function (event, area) {
    plot = $.plot($(divname), [{label: <?php echo("\"$measurementname <a href='ajax/showtestmeasurementdatagraphviewerphp.php?test=$testname&site=$sitename&measurement=$measurementname&graph=$graph_nr&export=csv'>Export as CSV</a>\""); ?>, data: d1}],
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
    plot = $.plot($(divname),
                  [{label: <?php echo("\"$measurementname\""); ?> ,data: d1}], options,{xaxis: { min: <?php echo $t-2000000000?>, max: <?php echo $t+50000000; ?>}, yaxis: { min: 0}});

<?php } else { ?>
    plot = $.plot($(divname),
                  [{label: <?php echo("\"$measurementname\""); ?> ,data: d1}],
                  $.extend(true,{}, options, {xaxis: { min: <?php echo $t-2000000000?>, max: <?php echo $t+50000000; ?>}, yaxis: { min: 0}})
                 );
<?php }
?>
});
