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

use App\Http\Controllers\Auth\LoginController;

function echo_currently_processing_submissions()
{
    if (config('database.default') == 'pgsql') {
        $sql_query = "SELECT now() AT TIME ZONE 'UTC'";
    } else {
        $sql_query = 'SELECT UTC_TIMESTAMP()';
    }
    $current_time = pdo_single_row_query($sql_query);

    $sql_query = 'SELECT project.name, submission.*, ';
    if (config('database.default') == 'pgsql') {
        $sql_query .= 'round((extract(EPOCH FROM now() - created)/3600)::numeric, 2) AS hours_ago ';
    } else {
        $sql_query .= 'ROUND(TIMESTAMPDIFF(SECOND, created, UTC_TIMESTAMP)/3600, 2) AS hours_ago ';
    }

    $sql_query .= 'FROM ' . qid('project') . ', ' . qid('submission') . ' ' .
        'WHERE project.id = submission.projectid AND status = 1';
    $rows = pdo_all_rows_query($sql_query);

    $sep = ', ';

    echo '<h1>Currently Processing Submissions as of ' . $current_time[0] . ' UTC</h1>';
    echo '<pre>';
    if (count($rows) > 0) {
        echo 'project name, backlog in hours' . "\n";
        echo '    submission.id, filename, projectid, status, attempts, filesize, filemd5sum, lastupdated, created, started, finished' . "\n";
        echo "\n";
        foreach ($rows as $row) {
            echo $row['name'] . $sep . $row['hours_ago'] . ' hours behind' . "\n";
            echo '    ' . $row['id'] .
                $sep . $row['filename'] .
                $sep . $row['projectid'] .
                $sep . $row['status'] .
                $sep . $row['attempts'] .
                $sep . $row['filesize'] .
                $sep . $row['filemd5sum'] .
                $sep . $row['lastupdated'] .
                $sep . $row['created'] .
                $sep . $row['started'] .
                $sep . $row['finished'] .
                "\n";
            echo "\n";
        }
    } else {
        echo 'Nothing is currently processing...' . "\n";
    }
    echo '</pre>';
    echo '<br/>';
}

function echo_pending_submissions()
{
    $rows = pdo_all_rows_query(
        'SELECT project.name, project.id, COUNT(submission.id) AS c FROM ' .
        qid('project') . ', ' . qid('submission') . ' ' .
        'WHERE project.id = submission.projectid ' .
        'AND status = 0 ' .
        'GROUP BY project.name,project.id'
    );

    $sep = ', ';

    echo '<h1>Pending Submissions</h1>';
    echo '<pre>';
    if (count($rows) > 0) {
        echo 'project.name, project.id, count of pending queued submissions' . "\n";
        echo "\n";
        foreach ($rows as $row) {
            echo $row['name'] .
                $sep . $row['id'] .
                $sep . $row['c'] .
                "\n";
        }
    } else {
        echo 'Nothing queued...' . "\n";
    }
    echo '</pre>';
    echo '<br/>';
}

function echo_average_wait_time($projectid)
{
    $project_name = get_project_name($projectid);
    if (config('database.default') == 'pgsql') {
        $sql_query = "SELECT extract(EPOCH FROM now() - created)/3600 as hours_ago,
            current_time AS time_local,
            count(created) AS num_files,
            round(avg((extract(EPOCH FROM started - created)/3600)::numeric), 1) AS avg_hours_delay,
            avg(extract(EPOCH FROM finished - started)) AS mean,
            min(extract(EPOCH FROM finished - started)) AS shortest,
            max(extract(EPOCH FROM finished - started)) AS longest
            FROM submission WHERE status = 2 AND projectid = $projectid
            GROUP BY hours_ago ORDER BY hours_ago ASC LIMIT 48";
    } else {
        $sql_query = "SELECT TIMESTAMPDIFF(HOUR, created, UTC_TIMESTAMP) as hours_ago,
            TIME_FORMAT(CONVERT_TZ(created, '+00:00', 'SYSTEM'), '%l:00 %p') AS time_local,
            COUNT(created) AS num_files,
            ROUND(AVG(TIMESTAMPDIFF(SECOND, created, started))/3600, 1) AS avg_hours_delay,
            AVG(TIMESTAMPDIFF(SECOND, started, finished)) AS mean,
            MIN(TIMESTAMPDIFF(SECOND, started, finished)) AS shortest,
            MAX(TIMESTAMPDIFF(SECOND, started, finished)) AS longest
            FROM submission WHERE status = 2 AND projectid = $projectid
            GROUP BY hours_ago ORDER BY hours_ago ASC LIMIT 48";
    }


    $rows = pdo_all_rows_query($sql_query);

    if (count($rows) > 0) {
        echo "<h2>Wait times for $project_name</h2>\n";
        echo "<table border=1>\n";
        echo "<tr>\n";
        echo "<th>Hours Ago</th>\n";
        echo "<th>Local Time</th>\n";
        echo "<th>Files Processed Successfully</th>\n";
        echo "<th>Avg Hours Spent Queued Before Processing</th>\n";
        echo "<th>Avg Seconds Spent Processing a File</th>\n";
        echo "<th>Min Seconds Spent Processing a File</th>\n";
        echo "<th>Max Seconds Spent Processing a File</th>\n";
        echo "</tr>\n";
        foreach ($rows as $row) {
            echo "<tr>\n";
            echo "<td style='text-align:center'>{$row['hours_ago']}</td>\n";
            echo "<td style='text-align:center'>{$row['time_local']}</td>\n";
            echo "<td style='text-align:center'>{$row['num_files']}</td>\n";
            echo "<td style='text-align:center'>{$row['avg_hours_delay']}</td>\n";
            echo "<td style='text-align:center'>{$row['mean']}</td>\n";
            echo "<td style='text-align:center'>{$row['shortest']}</td>\n";
            echo "<td style='text-align:center'>{$row['longest']}</td>\n";
            echo "</tr>\n";
        }
        echo "</table>\n";
    } else {
        echo "<h2>No average wait time data for $project_name</h2>\n";
    }
}

function echo_average_wait_times()
{
    $rows = pdo_all_rows_query(
        'SELECT projectid, COUNT(*) AS c FROM submission ' .
        'WHERE status=2 GROUP BY projectid');

    echo '<h1>Average Wait Times per Project</h1>';
    if (count($rows) > 0) {
        foreach ($rows as $row) {
            if ($row['c'] > 0) {
                echo_average_wait_time($row['projectid']);
            }
        }
    } else {
        echo 'No finished submissions for average wait time measurement...' . "\n";
    }
    echo '<br/>';
}

function echo_submissionprocessor_table()
{
    $rows = pdo_all_rows_query(
        'SELECT project.name, submissionprocessor.* FROM ' .
        qid('project') . ', ' . qid('submissionprocessor') . ' ' .
        'WHERE project.id = submissionprocessor.projectid '
    );

    echo '<h1>Table `submissionprocessor` (one row per project)</h1>';
    echo "<table border=1>\n";
    echo "<tr>\n";
    echo "<th>Project Name</th>\n";
    echo "<th>Project ID</th>\n";
    echo "<th>Process ID</th>\n";
    echo "<th>Last Updated</th>\n";
    echo "<th>Locked</th>\n";
    echo "</tr>\n";
    foreach ($rows as $row) {
        echo "<tr>\n";
        echo "<td style='text-align:center'>{$row['name']}</td>\n";
        echo "<td style='text-align:center'>{$row['projectid']}</td>\n";
        echo "<td style='text-align:center'>{$row['pid']}</td>\n";
        echo "<td style='text-align:center'>{$row['lastupdated']}</td>\n";
        echo "<td style='text-align:center'>{$row['locked']}</td>\n";
        echo "</tr>\n";
    }
    echo "</table>\n";
    echo '<br/>';
}

function echo_submission_table()
{
    @$limit = $_REQUEST['limit'];
    if (!isset($limit)) {
        $limit = 25;
    } else {
        $limit = pdo_real_escape_numeric($limit);
    }

    echo "<h1>Table `submission` (most recently queued $limit)</h1>";
    echo "<table border=1>\n";
    echo "<tr>\n";
    echo "<th>id</th>\n";
    echo "<th>filename</th>\n";
    echo "<th>projectid</th>\n";
    echo "<th>status</th>\n";
    echo "<th>attempts</th>\n";
    echo "<th>filesize</th>\n";
    echo "<th>filemd5sum</th>\n";
    echo "<th>lastupdated</th>\n";
    echo "<th>created</th>\n";
    echo "<th>started</th>\n";
    echo "<th>finished</th>\n";
    echo "</tr>\n";

    $rows = pdo_all_rows_query(
        'SELECT * FROM ' . qid('submission') . ' ORDER BY id DESC LIMIT ' . $limit
    );


    foreach ($rows as $row) {
        echo "<tr>\n";
        echo "<td style='text-align:center'>{$row['id']}</td>\n";
        echo "<td style='text-align:center'>{$row['filename']}</td>\n";
        echo "<td style='text-align:center'>{$row['projectid']}</td>\n";
        echo "<td style='text-align:center'>{$row['status']}</td>\n";
        echo "<td style='text-align:center'>{$row['attempts']}</td>\n";
        echo "<td style='text-align:center'>{$row['filesize']}</td>\n";
        echo "<td style='text-align:center'>{$row['filemd5sum']}</td>\n";
        echo "<td style='text-align:center'>{$row['lastupdated']}</td>\n";
        echo "<td style='text-align:center'>{$row['created']}</td>\n";
        echo "<td style='text-align:center'>{$row['started']}</td>\n";
        echo "<td style='text-align:center'>{$row['finished']}</td>\n";
        echo "</tr>\n";
    }
    echo "</table>\n";
    echo "<br/>\n";
}

if (Auth::check()) {
    $user = Auth::user();
    if ($user->admin) {
        echo_currently_processing_submissions();
        echo_pending_submissions();
        echo_average_wait_times();
        echo_submissionprocessor_table();
        echo_submission_table();
    } else {
        echo 'Admin login required to display monitoring info.';
    }
} else {
    return LoginController::staticShowLoginForm();
}
