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

use App\Services\ProjectPermissions;
use CDash\Model\Project;
use CDash\Database;

require_once 'include/pdo.php';
require_once 'include/common.php';

$projectid = $_GET['projectid'];
$project = new Project();
$project->Id = intval($projectid);
if (!ProjectPermissions::userCanViewProject($project)) {
    echo 'You are not authorized to view this page.';
    return;
}

$testname = htmlspecialchars($_GET['testname']);
$starttime = $_GET['starttime'];
@$zoomout = $_GET['zoomout'];

if (!isset($projectid) || !is_numeric($projectid)) {
    echo 'Not a valid projectid!';
    return;
}
$projectid = intval($projectid);

if (!isset($testname)) {
    echo 'Not a valid test name!';
    return;
}

if (!isset($starttime)) {
    echo 'Not a valid starttime!';
    return;
}

$db = Database::getInstance();

// We have to loop for the previous days
$failures = [];
for ($beginning_timestamp = $starttime; $beginning_timestamp > $starttime - 3600 * 24 * 7; $beginning_timestamp -= 3600 * 24) {
    $end_timestamp = $beginning_timestamp + 3600 * 24;

    $beginning_UTCDate = gmdate(FMT_DATETIME, $beginning_timestamp);
    $end_UTCDate = gmdate(FMT_DATETIME, $end_timestamp);

    $result = $db->executePreparedSingleRow("
                 SELECT count(*) AS c
                 FROM build
                 JOIN build2test ON (build.id = build2test.buildid)
                 WHERE
                     build.projectid = ?
                     AND build.starttime >= ?
                     AND build.starttime < ?
                     AND build2test.testid IN (
                         SELECT id
                         FROM test
                         WHERE name = ?
                     )
                     AND (
                         build2test.status <> 'passed'
                         OR build2test.timestatus <> 0
                     )
            ", [$projectid, $beginning_UTCDate, $end_UTCDate, $testname]);
    echo pdo_error();
    $failures[$beginning_timestamp] = intval($result['c']);
}
?>

<br>


<script language="javascript" type="text/javascript">
    $(function () {
        var d1 = [];
        var ty = [];

        <?php
        $tarray = array();
foreach ($failures as $key=>$value) {
    $t['x'] = $key;
    $t['y'] = $value;
    $tarray[] = $t;
}

$tarray = array_reverse($tarray);
foreach ($tarray as $axis) {
    ?>
        d1.push([<?php echo $axis['x']; ?>,<?php echo $axis['y']; ?>]);
        <?php
        $t = $axis['x'];
} ?>

        var options = {
            series: {
                bars: {
                    show: true,
                    barWidth: 0.5,
                },
            },
            legend: {
              show: true,
              position: "ne",
            },
            yaxis: {min: 0},
            xaxis: {
              mode: "time",
              min: <?php echo $t - 604800 ?>,
              max: <?php echo $t + 100000 ?>,
          },
            grid: {backgroundColor: "#fffaff"},
            selection: {mode: "x"},
            colors: ["#0000FF", "#dba255", "#919733"]
        };

        var plot = $.plot($("#testfailuregrapholder"), [{label: "# builds failed", data: d1}], options);

        $("#testfailuregrapholder").bind("plotselected", function (event, ranges) {
            $.each(plot.getXAxes(), function(_, axis) {
              var opts = axis.options;
              opts.min = ranges.xaxis.from;
              opts.max = ranges.xaxis.to;
            });
            plot.setupGrid();
            plot.draw();
            plot.clearSelection();
        });
    });

</script>
