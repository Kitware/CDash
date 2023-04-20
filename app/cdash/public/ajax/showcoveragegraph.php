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
require_once 'include/api_common.php';

use CDash\Database;

$buildid = $_GET['buildid'];
if (!isset($buildid) || !is_numeric($buildid)) {
    echo 'Not a valid buildid!';
    return;
}
$buildid = intval($buildid);

$db = Database::getInstance();

// Find the project variables
$build = $db->executePreparedSingleRow('SELECT name, type, siteid, projectid, starttime FROM build WHERE id=?', [$buildid]);

$buildtype = intval($build['type']);
$buildname = $build['name'];
$siteid = intval($build['siteid']);
$starttime = $build['starttime'];
$projectid = intval($build['projectid']);

if (!can_access_project($projectid)) {
    return 'You do not have permission to view this project.';
}

// Find the other builds
$previousbuilds = $db->executePrepared('
                      SELECT id, starttime, endtime, loctested, locuntested
                      FROM build, coveragesummary as cs
                      WHERE
                          cs.buildid=build.id
                          AND siteid=?
                          AND type=?
                          AND name=?
                          AND projectid=?
                          AND starttime<=?
                      ORDER BY starttime ASC
                  ', [$siteid, $buildtype, $buildname, $projectid, $starttime]);
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
foreach ($previousbuilds as $build_array) {
    $t = strtotime($build_array['starttime']) * 1000; //flot expects milliseconds
    @$percent = round(intval($build_array['loctested']) / (intval($build_array['loctested']) + intval($build_array['locuntested'])) * 100, 2); ?>
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
