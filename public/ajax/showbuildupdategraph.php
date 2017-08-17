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

$db = pdo_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN", "$CDASH_DB_PASS");
pdo_select_db("$CDASH_DB_NAME", $db);

// Find the project variables
$build = pdo_query("SELECT name,type,siteid,projectid,starttime FROM build WHERE id='$buildid'");
$build_array = pdo_fetch_array($build);

$buildtype = $build_array['type'];
$buildname = $build_array['name'];
$siteid = $build_array['siteid'];
$starttime = $build_array['starttime'];
$projectid = $build_array['projectid'];

// Find the other builds
$previousbuilds = pdo_query("SELECT b.id,b.starttime,bu.nfiles FROM build as b,build2update AS b2u, buildupdate as bu
                             WHERE b2u.updateid=bu.id AND b2u.buildid=b.id
                               AND b.siteid='$siteid' AND b.type='$buildtype' AND b.name='$buildname'
                               AND b.projectid='$projectid' AND b.starttime<='$starttime' ORDER BY b.starttime ASC");
?>


<br>
<script language="javascript" type="text/javascript">
    $(function () {
        var d1 = [];
        var buildids = [];
        <?php
        $i = 0;
        while ($build_array = pdo_fetch_array($previousbuilds)) {
            $t = strtotime($build_array['starttime']) * 1000; //flot expects milliseconds ?>
        d1.push([<?php echo $t; ?>,<?php echo $build_array['nfiles']; ?>]);
        buildids[<?php echo $t; ?>] = <?php echo $build_array['id']; ?>;
        <?php
        $i++;
        }
        ?>

        var options = {
            lines: {show: true},
            points: {show: true},
            xaxis: {mode: "time"},
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
            plot = $.plot($("#grapholder"), [{
                label: "Number of changed files",
                data: d1
            }], $.extend(true, {}, options, {xaxis: {min: area.x1, max: area.x2}}));
        });

        $("#grapholder").bind("plotclick", function (e, pos, item) {
            if (item) {
                plot.highlight(item.series, item.datapoint);
                buildid = buildids[item.datapoint[0]];
                window.location = "buildSummary.php?buildid=" + buildid;
            }
        });

        plot = $.plot($("#grapholder"), [{label: "Number of changed files", data: d1}], options);
    });
</script>
