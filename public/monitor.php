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

require_once 'login.php';

function echo_currently_processing_submissions()
{
    include dirname(__DIR__) . '/config/config.php';

    if ($CDASH_DB_TYPE == 'pgsql') {
        $sql_query = "SELECT now() AT TIME ZONE 'UTC'";
    } else {
        $sql_query = 'SELECT UTC_TIMESTAMP()';
    }
    $current_time = pdo_single_row_query($sql_query);

    $sql_query = 'SELECT project.name, submission.*, ';
    if ($CDASH_DB_TYPE == 'pgsql') {
        $sql_query .= 'round((extract(EPOCH FROM now() - created)/3600)::numeric, 2) AS hours_ago ';
    } else {
        $sql_query .= 'ROUND(TIMESTAMPDIFF(SECOND, created, UTC_TIMESTAMP)/3600, 2) AS hours_ago ';
    }

    $sql_query .= 'FROM ' . qid('project') . ', ' . qid('submission') . ' ' .
        'WHERE project.id = submission.projectid AND status = 1';
    $rows = pdo_all_rows_query($sql_query);

    $sep = ', ';

    echo '<h3>Currently Processing Submissions as of ' . $current_time[0] . ' UTC</h3>';
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

    echo '<h3>Pending Submissions</h3>';
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
    include dirname(__DIR__) . '/config/config.php';

    if ($CDASH_DB_TYPE == 'pgsql') {
        $sql_query = 'SELECT extract(EPOCH FROM now() - created)/3600 as hours_ago, ' .
            'current_time AS time_local, ' .
            'count(created) AS num_files, ' .
            'round(avg((extract(EPOCH FROM started - created)/3600)::numeric), 1) AS avg_hours_delay, ' .
            'avg(extract(EPOCH FROM finished - started)) AS mean, ' .
            'min(extract(EPOCH FROM finished - started)) AS shortest, ' .
            'max(extract(EPOCH FROM finished - started)) AS longest ' .
            "FROM submission WHERE status = 2 AND projectid = $projectid " .
            'GROUP BY hours_ago ORDER BY hours_ago ASC LIMIT 48';
    } else {
        $sql_query = 'SELECT TIMESTAMPDIFF(HOUR, created, UTC_TIMESTAMP) as hours_ago, ' .
            "TIME_FORMAT(CONVERT_TZ(created, '+00:00', 'SYSTEM'), '%l:00 %p') AS time_local, " .
            'COUNT(created) AS num_files, ' .
            'ROUND(AVG(TIMESTAMPDIFF(SECOND, created, started))/3600, 1) AS avg_hours_delay, ' .
            'AVG(TIMESTAMPDIFF(SECOND, started, finished)) AS mean, ' .
            'MIN(TIMESTAMPDIFF(SECOND, started, finished)) AS shortest, ' .
            'MAX(TIMESTAMPDIFF(SECOND, started, finished)) AS longest ' .
            "FROM submission WHERE status = 2 AND projectid = $projectid " .
            'GROUP BY hours_ago ORDER BY hours_ago ASC LIMIT 48';
    }

    $rows = pdo_all_rows_query($sql_query);

    $sep = ', ';

    echo 'projectid ' . $projectid . ' wait time' . "\n";
    if (count($rows) > 0) {
        echo 'hours_ago, time_local, num_files, avg_hours_delay, mean, shortest, longest' . "\n";
        foreach ($rows as $row) {
            echo '    ' . $row['hours_ago'] .
                $sep . $row['time_local'] .
                $sep . $row['num_files'] .
                $sep . $row['avg_hours_delay'] .
                $sep . $row['mean'] .
                $sep . $row['shortest'] .
                $sep . $row['longest'] .
                "\n";
        }
    } else {
        echo 'No average wait time data for projectid ' . $projectid . "\n";
    }
    echo "\n";
}

function echo_average_wait_times()
{
    $rows = pdo_all_rows_query(
        'SELECT projectid, COUNT(*) AS c FROM submission ' .
        'WHERE status=2 GROUP BY projectid');

    echo '<h3>Average Wait Times</h3>';
    echo '<pre>';
    if (count($rows) > 0) {
        foreach ($rows as $row) {
            if ($row['c'] > 0) {
                echo_average_wait_time($row['projectid']);
            }
        }
    } else {
        echo 'No finished submissions for average wait time measurement...' . "\n";
    }
    echo '</pre>';
    echo '<br/>';
}

function echo_submissionprocessor_table()
{
    $rows = pdo_all_rows_query(
        'SELECT project.name, submissionprocessor.* FROM ' .
        qid('project') . ', ' . qid('submissionprocessor') . ' ' .
        'WHERE project.id = submissionprocessor.projectid '
    );

    $sep = ', ';

    echo '<h3>Table `submissionprocessor` (one row per project)</h3>';
    echo '<pre>';
    echo 'project.name, projectid, pid, lastupdated, locked' . "\n";
    echo "\n";
    foreach ($rows as $row) {
        echo $row['name'] . $sep . $row['projectid'] . $sep . $row['pid'] . $sep . $row['lastupdated'] . $sep . $row['locked'] . "\n";
    }
    echo '</pre>';
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

    $rows = pdo_all_rows_query(
        'SELECT * FROM ' . qid('submission') . ' ORDER BY id DESC LIMIT ' . $limit
    );

    $sep = ', ';

    echo "<h3>Table `submission` (most recently queued $limit)</h3>";
    echo '<pre>';
    echo 'id, filename, projectid, status, attempts, filesize, filemd5sum, ' .
        'lastupdated, created, started, finished' . "\n";
    echo "\n";
    foreach ($rows as $row) {
        echo $row['id'] .
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
    }
    echo '</pre>';
    echo '<br/>';
}

if ($session_OK) {
    $userid = $_SESSION['cdash']['loginid'];

    $user_is_admin = pdo_get_field_value(
        'SELECT admin FROM ' . qid('user') . " WHERE id='$userid'",
        'admin',
        0);

    if ($user_is_admin) {
        echo_currently_processing_submissions();
        echo_pending_submissions();
        echo_average_wait_times();
        echo_submissionprocessor_table();
        echo_submission_table();
    } else {
        echo 'Admin login required to display monitoring info.';
    }
}
