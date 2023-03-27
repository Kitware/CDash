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
require_once 'include/pdo.php';
require_once 'include/common.php';

$buildid = pdo_real_escape_numeric($_GET['buildid']);
if (!isset($buildid) || !is_numeric($buildid)) {
    echo 'Not a valid buildid!';
    return;
}

// Find the project variables
$build = pdo_query("SELECT name,type,siteid,projectid,starttime FROM build WHERE id='$buildid'");
$build_array = pdo_fetch_array($build);

$buildtype = $build_array['type'];
$buildname = $build_array['name'];
$siteid = $build_array['siteid'];
$starttime = $build_array['starttime'];
$projectid = $build_array['projectid'];

// Find the other builds
$previousbuilds = pdo_query("SELECT id,starttime,endtime,loctested,locuntested FROM build,coveragesummary as cs WHERE cs.buildid=build.id AND siteid='$siteid' AND type='$buildtype' AND name='$buildname'
                             AND projectid='$projectid' AND starttime<='$starttime' ORDER BY starttime ASC");
?>


<br>
<script language="javascript" type="text/javascript">
    $(function () {
        var percent_array = [];
        var loctested_array = [];
        var locuntested_array = [];
        var buildids = [];
        <?php
        $i = 0;
while ($build_array = pdo_fetch_array($previousbuilds)) {
    $t = strtotime($build_array['starttime']) * 1000; //flot expects milliseconds
    @$percent = round($build_array['loctested'] / ($build_array['loctested'] + $build_array['locuntested']) * 100, 2); ?>
        percent_array.push([<?php echo $t; ?>,<?php echo $percent; ?>]);
        loctested_array.push([<?php echo $t; ?>,<?php echo $build_array['loctested']; ?>]);
        locuntested_array.push([<?php echo $t; ?>,<?php echo $build_array['locuntested']; ?>]);
        buildids[<?php echo $t; ?>] = <?php echo $build_array['id']; ?>;
        <?php
        $i++;
}
?>

        var options = {
            lines: {show: true},
            points: {show: true},
            xaxis: {mode: "time"},
            yaxis: {min: 0, max: 100},
            legend: {position: "nw"},
            grid: {
                backgroundColor: "#fffaff",
                clickable: true,
                hoverable: true,
                hoverFill: '#444',
                hoverRadius: 4
            },
            selection: {mode: "x"},
            colors: ["#0000FF", "#dba255", "#919733"]
        };

        $("#grapholder").bind("selected", function (event, area) {
            plot = $.plot($("#grapholder"),
                [{label: "% coverage", data: percent_array},
                    {label: "loc tested", data: loctested_array, yaxis: 2},
                    {label: "loc untested", data: locuntested_array, yaxis: 2}],
                $.extend(true, {}, options, {xaxis: {min: area.x1, max: area.x2}}));
        });

        $("#grapholder").bind("plotclick", function (e, pos, item) {
            if (item) {
                plot.highlight(item.series, item.datapoint);
                buildid = buildids[item.datapoint[0]];
                window.location = "build/" + buildid;
            }
        });

        plot = $.plot($("#grapholder"), [{label: "% coverage", data: percent_array},
            {label: "loc tested", data: loctested_array, yaxis: 2},
            {label: "loc untested", data: locuntested_array, yaxis: 2}], options);
    });
</script>
