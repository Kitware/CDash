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

$testid = $_GET["testid"];

$db = mysql_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN","$CDASH_DB_PASS");
mysql_select_db("$CDASH_DB_NAME",$db);

// Find the project variables
$build2test = mysql_query("SELECT buildid FROM build2test WHERE testid='$testid'");
$build2test_array = mysql_fetch_array($build2test);
$buildid = $build2test_array["buildid"];

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

$project = mysql_query("SELECT name FROM project WHERE id='$projectid'");
$project_array = mysql_fetch_array($project);

// Find the other builds
$previousbuilds = mysql_query("SELECT build.id,build.starttime,build2test.status FROM build,build2test WHERE siteid='$siteid' AND type='$buildtype' AND name='$buildname'
                               AND projectid='$projectid' AND starttime<='$starttime' AND build2test.buildid=build.id
															 AND test.id=build2test.testid AND test.name='$testname'
															 ORDER BY starttime ASC");
																																																			
?>

    
<br>
<script id="source" language="javascript" type="text/javascript">
$(function () {
    var d1 = [];
    var tx = [];
    var ty = [];
		 ty.push([0,"Failed"]);
		 ty.push([1,"Passed"]);
    <?php
    while($build_array = mysql_fetch_array($previousbuilds))
      {
      $t = strtotime($build_array["starttime"]);
						if(strtolower($build_array["status"]) == "passed")
						  {
								$status = 1;
						  }
						else
						  {
								$status = 0;
								}
    ?>
      d1.push([<?php echo $t; ?>,<?php echo $status; ?>]);
      tx.push([<?php echo $t; ?>,"<?php echo $build_array["starttime"]; ?>"]);

    <?php
      }
    ?>
    
    $("#passinggrapholder").bind("selected", function (event, area) {
    $.plot($("#passinggrapholder"), [{label: "Failed/Passed",  data: d1}],
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
   
  $.plot($("#passinggrapholder"), [{label: "Failed/Passed",  data: d1}],
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
