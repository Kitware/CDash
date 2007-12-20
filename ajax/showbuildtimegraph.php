<?php
/*=========================================================================

  Program:   CDash - Cross-Platform Dashboard System
  Module:    $RCSfile: common.php,v $
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

$buildid = $_GET["buildid"];

$db = mysql_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN","$CDASH_DB_PASS");
mysql_select_db("$CDASH_DB_NAME",$db);

// Find the project variables
$build = mysql_query("SELECT name,type,siteid,projectid,starttime FROM build WHERE id='$buildid'");
$build_array = mysql_fetch_array($build);

$buildtype = $build_array["type"];
$buildname = $build_array["name"];
$siteid = $build_array["siteid"];
$starttime = $build_array["starttime"];
$projectid = $build_array["projectid"];

$project = mysql_query("SELECT name FROM project WHERE id='$projectid'");
$project_array = mysql_fetch_array($project);

// Find the other builds
$previousbuilds = mysql_query("SELECT id,starttime,endtime FROM build WHERE siteid='$siteid' AND type='$buildtype' AND name='$buildname'
                               AND projectid='$projectid' AND starttime<='$starttime' ORDER BY starttime ASC");
?>

    
<br>
<script id="source" language="javascript" type="text/javascript">
$(function () {
    var d1 = [];
    var tx = [];
    var ty = [];
    <?php
    $i=0;
    while($build_array = mysql_fetch_array($previousbuilds))
      {
      $t = strtotime($build_array["starttime"]);
    ?>
      d1.push([<?php echo $t; ?>,<?php echo strtotime($build_array["endtime"])-strtotime($build_array["starttime"]); ?>]);
      tx.push([<?php echo $t; ?>,"<?php echo $build_array["starttime"]; ?>"]);
      ty.push([<?php echo strtotime($build_array["endtime"])-strtotime($build_array["starttime"]); ?>,"<?php echo strtotime($build_array["endtime"])-strtotime($build_array["starttime"]); ?>"]);
    <?php
    $i++;
      }
    ?>
    
    $("#grapholder").bind("selected", function (event, area) {
    $.plot($("#grapholder"), [{label: "Build Time (seconds)",  data: d1}],
           {
           lines: { show: true },
           points: { show: true },
           xaxis: {
             ticks: tx,
             min: area.x1,
	     max: area.x2
	     },
           yaxis: {
             ticks: ty
             },
           grid: {
            backgroundColor: "#fffaff"
             },
           colors: ["#0000FF", "#dba255", "#919733"],
           selection: { mode: "x" }
    }

   );

  });
   
  $.plot($("#grapholder"), [{label: "Build Time (seconds)",  data: d1}],
        {
        lines: { show: true },
        points: { show: true },
        xaxis: {
         ticks: tx
        },
        yaxis: {
         ticks: ty
        },
        grid: {
            backgroundColor: "#fffaff"
        },
        colors: ["#0000FF", "#dba255", "#919733"],
        selection: { mode: "x" }     
        
        }
  );
});
</script>
