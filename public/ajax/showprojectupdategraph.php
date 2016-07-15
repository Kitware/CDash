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

$projectid = pdo_real_escape_numeric($_GET['projectid']);
if (!isset($projectid) || !is_numeric($projectid)) {
    echo 'Not a valid projectid!';
    return;
}
$timestamp = pdo_real_escape_numeric($_GET['timestamp']);

$db = pdo_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN", "$CDASH_DB_PASS");
pdo_select_db("$CDASH_DB_NAME", $db);

// Find the project variables
$files = pdo_query('SELECT d.date,count(df.dailyupdateid) FROM dailyupdate as d
                    LEFT JOIN dailyupdatefile AS df ON (df.dailyupdateid=d.id)
                    WHERE d.projectid=' . $projectid . "
                    AND date<='" . date('Y-m-d', $timestamp) . "'
                    GROUP BY d.date ORDER BY d.date");
?>


<br>
<script language="javascript" type="text/javascript">
    $(function () {
        var d1 = [];
        var dates = [];
        <?php
        $i = 0;
        while ($files_array = pdo_fetch_array($files)) {
            $t = strtotime($files_array[0]) * 1000; //flot expects milliseconds
        ?>
        d1.push([<?php echo $t; ?>,<?php echo $files_array[1]; ?>]);
        dates[<?php echo $t; ?>] = '<?php echo $files_array[0]; ?>';
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
                date = dates[item.datapoint[0]];
                window.location = "viewChanges.php?project=<?php echo get_project_name($projectid);?>&date=" + date;
            }
        });

        plot = $.plot($("#grapholder"), [{label: "Number of changed files", data: d1}], options);
    });
</script>
