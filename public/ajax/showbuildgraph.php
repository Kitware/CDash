<?php
/*=========================================================================
  Program:   CDash - Cross-Platform Dashboard System
  Module:    $Id$
  Language:  PHP
  Date:      $Date$
  Version:   $Revision$

  Copyright (c) Kitware, Inc. All rights reserved.
  See LICENSE or http://www.cdash.org/licensing/ for details.

  This software is distributed WITHOUT ANY WARRANTY; without even
  the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR
  PURPOSE. See the above copyright notices for more information.
=========================================================================*/

require_once dirname(dirname(__DIR__)) . '/config/config.php';
require_once 'include/pdo.php';
require_once 'include/common.php';

$buildid = pdo_real_escape_numeric($_GET['buildid']);
if (!isset($buildid) || !is_numeric($buildid)) {
    echo 'Not a valid buildid!';
    return;
}

$graphtype = pdo_real_escape_string($_GET['graphtype']);
if (!isset($graphtype)) {
    echo 'Not a valid graphtype!';
    return;
}

$db = pdo_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN", "$CDASH_DB_PASS");
pdo_select_db("$CDASH_DB_NAME", $db);

// Find the project variables
$build = pdo_query("SELECT name,type,siteid,projectid,starttime
                    FROM build WHERE id='$buildid'");
$build_array = pdo_fetch_array($build);

$buildtype = $build_array['type'];
$buildname = $build_array['name'];
$siteid = $build_array['siteid'];
$starttime = $build_array['starttime'];
$projectid = $build_array['projectid'];

$project = pdo_query("SELECT name FROM project WHERE id='$projectid'");
$project_array = pdo_fetch_array($project);

// Find the other builds
$previousbuilds = pdo_query("SELECT id,starttime,endtime,buildwarnings,builderrors,testfailed
                             FROM build WHERE siteid='$siteid' AND type='$buildtype' AND name='$buildname'
                             AND projectid='$projectid' AND starttime<='$starttime' ORDER BY starttime ASC");
?>


<br>
<script language="javascript" type="text/javascript">
    $(function () {
        var buildtime = [];
        var builderrors = [];
        var buildwarnings = [];
        var testfailed = [];
        var buildids = [];
        <?php
        $i = 0;
        while ($build_array = pdo_fetch_array($previousbuilds)) {
            $t = strtotime($build_array['starttime']) * 1000; //flot expects milliseconds
        ?>
        buildtime.push([<?php echo $t; ?>,<?php echo(strtotime($build_array['endtime']) - strtotime($build_array['starttime'])) / 60; ?>]);
        builderrors.push([<?php echo $t; ?>,<?php echo $build_array['builderrors'] ?>]);
        buildwarnings.push([<?php echo $t; ?>,<?php echo $build_array['buildwarnings'] ?>]);
        testfailed.push([<?php echo $t; ?>,<?php echo $build_array['testfailed'] ?>]);
        buildids[<?php echo $t; ?>] = <?php echo $build_array['id']; ?>;
        <?php
        $i++;
        }
        ?>

        var buildgrapholder = "#build"+<?php echo json_encode($graphtype); ?>+"grapholder";
        var graphtype = <?php echo json_encode($graphtype); ?>;

        var label;
        var data;
        var color;
        var yaxis = {minTickSize: 1};
        if (graphtype == "time") {
          label = "Build Time";
          data = buildtime;
          color = "#41A317";
          yaxis = { tickFormatter: function (v, axis) {
                    return v.toFixed(axis.tickDecimals) + " mins"} };
        } else if (graphtype == "errors") {
          label = "# errors";
          data = builderrors;
          color = "#FDD017";
        } else if (graphtype == "warnings") {
          label = "# warning";
          data = buildwarnings;
          color = "#FF0000";
        } else if (graphtype == "testsfailed") {
          label = "# tests failed";
          data = testfailed;
          color = "#0000FF";
        }

        var options = {
            lines: {show: true},
            points: {show: true},
            xaxis: {mode: "time"},
            yaxis: yaxis,
            grid: {
                backgroundColor: "#fffaff",
                clickable: true,
                hoverable: true,
                hoverFill: '#444',
                hoverRadius: 4
            },
            selection: {mode: "x"},
            colors: [color]
        };

        $(buildgrapholder).bind("selected", function (event, area) {
            plot = $.plot($(buildgrapholder), [{label: label, data: data}],
              $.extend(true, {}, options, {xaxis: {min: area.x1, max: area.x2}}));
        });

        $(buildgrapholder).bind("plotclick", function (e, pos, item) {
            if (item) {
                plot.highlight(item.series, item.datapoint);
                buildid = buildids[item.datapoint[0]];
                window.location = "buildSummary.php?buildid=" + buildid;
            }
        });

        plot = $.plot($(buildgrapholder), [{label: label, data: data}],
            options);
    });
</script>
