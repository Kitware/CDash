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
include_once 'include/common.php';
include 'include/version.php';

require_once 'include/filterdataFunctions.php';

use CDash\Model\CoverageFile2User;
use CDash\Model\User;

@set_time_limit(0);

$noforcelogin = 1;
require 'public/login.php';

$buildid = pdo_real_escape_numeric($_GET['buildid']);
if (!isset($buildid) || !is_numeric($buildid)) {
    echo 'Not a valid buildid!';
    return;
}

$userid = 0;
if (isset($_GET['userid']) && is_numeric($_GET['userid'])) {
    $userid = pdo_real_escape_numeric($_GET['userid']);
}

// Find the project variables
$build = pdo_query("SELECT name,type,siteid,projectid,starttime FROM build WHERE id='$buildid'");
$build_array = pdo_fetch_array($build);
$projectid = $build_array['projectid'];

if (!isset($projectid) || $projectid == 0 || !is_numeric($projectid)) {
    echo "This project doesn't exist. Maybe it has been deleted.";
    exit();
}

checkUserPolicy(@$_SESSION['cdash']['loginid'], $projectid);

$project = pdo_query("SELECT name,coveragethreshold,nightlytime,showcoveragecode FROM project WHERE id='$projectid'");
if (pdo_num_rows($project) == 0) {
    echo "This project doesn't exist.";
    exit();
}

$role = 0;
$user2project = pdo_query("SELECT role FROM user2project WHERE userid='$userid' AND projectid='$projectid'");
if (pdo_num_rows($user2project) > 0) {
    $user2project_array = pdo_fetch_array($user2project);
    $role = $user2project_array['role'];
}

$project_array = pdo_fetch_array($project);
$projectname = $project_array['name'];

$projectshowcoveragecode = 1;
if (!$project_array['showcoveragecode'] && $role < 2) {
    $projectshowcoveragecode = 0;
}

$start = 0;
$end = 10000000;

/* Paging */
if (isset($_GET['iDisplayStart']) && $_GET['iDisplayLength'] != '-1') {
    $start = pdo_real_escape_numeric($_GET['iDisplayStart']);
    $end = pdo_real_escape_numeric($_GET['iDisplayStart']) + pdo_real_escape_numeric($_GET['iDisplayLength']);
}

/* Sorting */
$sortby = 'filename';
if (isset($_GET['iSortCol_0'])) {
    switch ($_GET['iSortCol_0']) {
        case 0:
            $sortby = 'filename';
            break;
        case 1:
            $sortby = 'status';
            break;
        case 2:
            $sortby = 'percentage';
            break;
        case 3:
            $sortby = 'lines';
            break;
        case 4:
            $sortby = 'branchpercentage';
            break;
        case 5:
            $sortby = 'branches';
            break;
        case 6:
            $sortby = 'priority';
            break;
    }
}

$sortdir = 'asc';
if (isset($_GET['sSortDir_0'])) {
    $sortdir = $_GET['sSortDir_0'];
}

function sort_filename_asc($a, $b)
{
    if ($a['fullpath'] == $b['fullpath']) {
        return 0;
    }
    return $a['fullpath'] > $b['fullpath'] ? 1 : -1;
}
function sort_filename_desc($a, $b)
{
    if ($a['fullpath'] == $b['fullpath']) {
        return 0;
    }
    return $a['fullpath'] > $b['fullpath'] ? -1 : 1;
}
function sort_status_asc($a, $b)
{
    if ($a['coveragemetric'] == $b['coveragemetric']) {
        return 0;
    }
    return $a['coveragemetric'] > $b['coveragemetric'] ? 1 : -1;
}
function sort_status_desc($a, $b)
{
    if ($a['coveragemetric'] == $b['coveragemetric']) {
        return 0;
    }
    return $a['coveragemetric'] > $b['coveragemetric'] ? -1 : 1;
}
function sort_percentage_asc($a, $b)
{
    if ($a['percentcoverage'] == $b['percentcoverage']) {
        return 0;
    }
    return $a['percentcoverage'] > $b['percentcoverage'] ? 1 : -1;
}
function sort_percentage_desc($a, $b)
{
    if ($a['percentcoverage'] == $b['percentcoverage']) {
        return 0;
    }
    return $a['percentcoverage'] > $b['percentcoverage'] ? -1 : 1;
}
function sort_branchpercentage_asc($a, $b)
{
    if ($a['branchpercentcoverage'] == $b['branchpercentcoverage']) {
        return 0;
    }
    return $a['branchpercentcoverage'] > $b['branchpercentcoverage'] ? 1 : -1;
}
function sort_branchpercentage_desc($a, $b)
{
    if ($a['branchpercentcoverage'] == $b['branchpercentcoverage']) {
        return 0;
    }
    return $a['branchpercentcoverage'] > $b['branchpercentcoverage'] ? -1 : 1;
}
function sort_lines_asc($a, $b)
{
    if ($a['locuntested'] == $b['locuntested']) {
        return 0;
    }
    return $a['locuntested'] > $b['locuntested'] ? 1 : -1;
}
function sort_lines_desc($a, $b)
{
    if ($a['locuntested'] == $b['locuntested']) {
        return 0;
    }
    return $a['locuntested'] > $b['locuntested'] ? -1 : 1;
}
function sort_branches_asc($a, $b)
{
    if ($a['branchesuntested'] == $b['branchesuntested']) {
        return 0;
    }
    return $a['branchesuntested'] > $b['branchesuntested'] ? 1 : -1;
}
function sort_branches_desc($a, $b)
{
    if ($a['branchesuntested'] == $b['branchesuntested']) {
        return 0;
    }
    return $a['branchesuntested'] > $b['branchesuntested'] ? -1 : 1;
}
function sort_functions_asc($a, $b)
{
    if ($a['functionsuntested'] == $b['functionsuntested']) {
        return 0;
    }
    return $a['functionsuntested'] > $b['functionsuntested'] ? 1 : -1;
}
function sort_functions_desc($a, $b)
{
    if ($a['functionsuntested'] == $b['functionsuntested']) {
        return 0;
    }
    return $a['functionsuntested'] > $b['functionsuntested'] ? -1 : 1;
}
function sort_priority_asc($a, $b)
{
    if ($a['priority'] == $b['priority']) {
        return 0;
    }
    return $a['priority'] > $b['priority'] ? 1 : -1;
}
function sort_priority_desc($a, $b)
{
    if ($a['priority'] == $b['priority']) {
        return 0;
    }
    return $a['priority'] > $b['priority'] ? -1 : 1;
}
function sort_user($a, $b)
{
    if (isset($a['user'][0]) && !isset($b['user'][0])) {
        return 0;
    }
    if (!isset($a['user'][0]) && isset($b['user'][0])) {
        return 1;
    }
    if (!isset($a['user'][0]) && !isset($b['user'][0])) {
        return 0;
    }
    return $a['user'][0] < $b['user'][0] ? 1 : 0;
}
function sort_user_asc($a, $b)
{
    sort_user($a, $b);
}
function sort_user_desc($a, $b)
{
    sort_user($a, $b);
}

$SQLsearchTerm = '';
if (isset($_GET['sSearch']) && $_GET['sSearch'] != '') {
    $SQLsearchTerm = " AND cf.fullpath LIKE '%" . htmlspecialchars(pdo_real_escape_string($_GET['sSearch'])) . "%'";
}

$SQLDisplayAuthors = '';
$SQLDisplayAuthor = '';
if ($userid) {
    $SQLDisplayAuthor = ',cfu.userid ';
    $SQLDisplayAuthors = ' LEFT JOIN coveragefile2user AS cfu ON (cfu.fileid=cf.id) ';
}

// Filters:
//
$filterdata = get_filterdata_from_request();
$filter_sql = $filterdata['sql'];
$limit_sql = '';
if ($filterdata['limit'] > 0) {
    $limit_sql = ' LIMIT ' . $filterdata['limit'];
}

if (isset($_GET['dir']) && $_GET['dir'] != '') {
    $escaped_dir = htmlspecialchars(pdo_real_escape_string($_GET['dir']));
    $SQLsearchTerm .= " AND (cf.fullpath LIKE '$escaped_dir/%' OR cf.fullpath LIKE './$escaped_dir/%')";
}

// Coverage files
$sql = 'SELECT cf.fullpath,c.fileid,' .
    'c.locuntested,c.loctested,' .
    'c.branchstested,c.branchsuntested,' .
    'c.functionstested,c.functionsuntested,' .
    'cfp.priority ' . $SQLDisplayAuthor . ' ' .
    'FROM coverage AS c,coveragefile AS cf ' .
    $SQLDisplayAuthors . ' ' .
    'LEFT JOIN coveragefilepriority AS cfp ON ' .
    '(cfp.fullpath=cf.fullpath AND projectid=' . qnum($projectid) . ') ' .
    "WHERE c.buildid='$buildid' AND cf.id=c.fileid AND c.covered=1 " .
    $filter_sql . ' ' . $SQLsearchTerm . $limit_sql;
$coveragefile = pdo_query($sql);
if (false === $coveragefile) {
    add_log('error: pdo_query failed: ' . pdo_error(),
        __FILE__, LOG_ERR);
}

// Add the coverage type
$status = -1;
if (isset($_GET['status'])) {
    $status = pdo_real_escape_numeric($_GET['status']);
}

$covfile_array = array();
while ($coveragefile_array = pdo_fetch_array($coveragefile)) {
    $covfile['filename'] = substr($coveragefile_array['fullpath'], strrpos($coveragefile_array['fullpath'], '/') + 1);
    $fullpath = $coveragefile_array['fullpath'];
    // Remove the ./ so that it's cleaner
    if (substr($fullpath, 0, 2) == './') {
        $fullpath = substr($fullpath, 2);
    }
    if (isset($_GET['dir']) && $_GET['dir'] != '' && $_GET['dir'] != '.') {
        $fullpath = substr($fullpath, strlen($_GET['dir']) + 1);
    }

    $covfile['fullpath'] = $fullpath;
    $covfile['fileid'] = $coveragefile_array['fileid'];
    $covfile['locuntested'] = $coveragefile_array['locuntested'];
    $covfile['loctested'] = $coveragefile_array['loctested'];
    $covfile['covered'] = 1;
    // Compute the coverage metric for bullseye (branch coverage without line coverage)
    if (($coveragefile_array['loctested'] == 0 &&
                $coveragefile_array['locuntested'] == 0) &&
            ($coveragefile_array['branchstested'] > 0 ||
            $coveragefile_array['branchsuntested'] > 0 ||
            $coveragefile_array['functionstested'] > 0 ||
            $coveragefile_array['functionsuntested'] > 0)) {
        // Metric coverage
        $metric = 0;
        if ($coveragefile_array['functionstested'] + $coveragefile_array['functionsuntested'] > 0) {
            $metric += $coveragefile_array['functionstested'] / ($coveragefile_array['functionstested'] + $coveragefile_array['functionsuntested']);
        }
        if ($coveragefile_array['branchstested'] + $coveragefile_array['branchsuntested'] > 0) {
            $metric += $coveragefile_array['branchstested'] / ($coveragefile_array['branchstested'] + $coveragefile_array['branchsuntested']);
            $metric /= 2.0;
        }
        $covfile['branchesuntested'] = $coveragefile_array['branchsuntested'];
        $covfile['branchestested'] = $coveragefile_array['branchstested'];
        $covfile['functionsuntested'] = $coveragefile_array['functionsuntested'];
        $covfile['functionstested'] = $coveragefile_array['functionstested'];

        $covfile['percentcoverage'] = sprintf('%3.2f', $metric * 100);
        $covfile['coveragemetric'] = $metric;
        $coveragetype = 'bullseye';
    } else {
        // coverage metric for gcov
        if ($coveragefile_array['branchstested'] + $coveragefile_array['branchsuntested'] > 0) {
            $metric = 0;
            $covfile['branchesuntested'] = $coveragefile_array['branchsuntested'];
            $covfile['branchestested'] = $coveragefile_array['branchstested'];
            $metric += $coveragefile_array['branchstested'] / ($coveragefile_array['branchstested'] + $coveragefile_array['branchsuntested']);
            $covfile['branchpercentcoverage'] = sprintf('%3.2f', $metric * 100);
            $covfile['branchcoveragemetric'] = $metric;
        }

        $covfile['percentcoverage'] = sprintf('%3.2f', $covfile['loctested'] / ($covfile['loctested'] + $covfile['locuntested']) * 100);
        $covfile['coveragemetric'] = ($covfile['loctested'] + 10) / ($covfile['loctested'] + $covfile['locuntested'] + 10);
        $coveragetype = 'gcov';
    }

    // Add the priority
    $CoverageFile2User = new CoverageFile2User();
    $CoverageFile2User->ProjectId = $projectid;
    $CoverageFile2User->FullPath = $covfile['fullpath'];

    $covfile['priority'] = $coveragefile_array['priority'];

    // If the user is logged in we set the users
    if (isset($coveragefile_array['userid'])) {
        $covfile['user'] = $coveragefile_array['userid'];
    }
    if ($covfile['coveragemetric'] != 1.0 || $status != -1) {
        $covfile_array[] = $covfile;
    }
}


// Contruct the directory view
if ($status == -1) {
    $directory_array = array();
    foreach ($covfile_array as $covfile) {
        $fullpath = $covfile['fullpath'];
        $fullpath = dirname($fullpath);
        if (!isset($directory_array[$fullpath])) {
            $directory_array[$fullpath] = array();
            $directory_array[$fullpath]['priority'] = 0;
            $directory_array[$fullpath]['directory'] = 1;
            $directory_array[$fullpath]['covered'] = 1;
            $directory_array[$fullpath]['fileid'] = 0;
            $directory_array[$fullpath]['locuntested'] = 0;
            $directory_array[$fullpath]['loctested'] = 0;
            $directory_array[$fullpath]['branchesuntested'] = 0;
            $directory_array[$fullpath]['branchestested'] = 0;
            $directory_array[$fullpath]['functionsuntested'] = 0;
            $directory_array[$fullpath]['functionstested'] = 0;
            $directory_array[$fullpath]['percentcoverage'] = 0;
            $directory_array[$fullpath]['coveragemetric'] = 0;
            $directory_array[$fullpath]['nfiles'] = 0;
            $directory_array[$fullpath]['branchpercentcoverage'] = 0;
            $directory_array[$fullpath]['branchcoveragemetric'] = 0;
        }

        $directory_array[$fullpath]['fullpath'] = $fullpath;
        $directory_array[$fullpath]['locuntested'] += $covfile['locuntested'];
        $directory_array[$fullpath]['loctested'] += $covfile['loctested'];
        if (isset($covfile['branchesuntested'])) {
            $directory_array[$fullpath]['branchesuntested'] += $covfile['branchesuntested'];
            $directory_array[$fullpath]['branchestested'] += $covfile['branchestested'];

            $directory_array[$fullpath]['branchcoveragemetric'] += $covfile['branchcoveragemetric'];
        }
        if (isset($covfile['functionsuntested'])) {
            $directory_array[$fullpath]['functionsuntested'] += $covfile['functionsuntested'];
            $directory_array[$fullpath]['functionstested'] += $covfile['functionstested'];
        }
        $directory_array[$fullpath]['coveragemetric'] += $covfile['coveragemetric'];
        $directory_array[$fullpath]['nfiles']++;
    }

    // Compute the average
    foreach ($directory_array as $fullpath => $covdir) {
        $directory_array[$fullpath]['percentcoverage'] = sprintf('%3.2f',
            100.0 * ($covdir['loctested'] / ($covdir['loctested'] + $covdir['locuntested'])));
        $directory_array[$fullpath]['coveragemetric'] = sprintf('%3.2f', $covdir['coveragemetric'] / $covdir['nfiles']);

        // Compute the branch average
        if ($covfile['branchestested'] + $covfile['branchesuntested'] > 0 && $coveragetype == 'gcov') {
            $directory_array[$fullpath]['branchpercentcoverage'] = sprintf('%3.2f',
                100.0 * ($covdir['branchestested'] / ($covdir['branchestested'] + $covdir['branchesuntested'])));
            $directory_array[$fullpath]['branchcoveragemetric'] = sprintf('%3.2f', $covdir['branchcoveragemetric']);
        }

    }

    $covfile_array = array_merge($covfile_array, $directory_array);
//$covfile_array = $directory_array;
} elseif ($status == 0) {
    // Add the untested files if the coverage is low

    $sql = 'SELECT cf.fullpath,cfp.priority' . $SQLDisplayAuthor . ' FROM coverage AS c,coveragefile AS cf ' . $SQLDisplayAuthors . '
              LEFT JOIN coveragefilepriority AS cfp ON (cfp.fullpath=cf.fullpath AND projectid=' . qnum($projectid) . ")
              WHERE c.buildid='$buildid' AND cf.id=c.fileid AND c.covered=0 " .
        $SQLsearchTerm;
    $coveragefile = pdo_query($sql);
    if (false === $coveragefile) {
        add_log('error: pdo_query 2 failed: ' . pdo_error(),
            __FILE__, LOG_ERR);
    }
    while ($coveragefile_array = pdo_fetch_array($coveragefile)) {
        $covfile['filename'] = substr($coveragefile_array['fullpath'], strrpos($coveragefile_array['fullpath'], '/') + 1);
        $covfile['fullpath'] = $coveragefile_array['fullpath'];
        $covfile['fileid'] = 0;
        $covfile['covered'] = 0;
        $covfile['locuntested'] = 0;
        $covfile['loctested'] = 0;
        $covfile['branchesuntested'] = 0;
        $covfile['branchestested'] = 0;
        $covfile['functionsuntested'] = 0;
        $covfile['functionstested'] = 0;
        $covfile['percentcoverage'] = 0;
        $covfile['coveragemetric'] = 0;

        $covfile['priority'] = $coveragefile_array['priority'];
        if (isset($coveragefile_array['userid'])) {
            $covfile['user'] = $coveragefile_array['userid'];
        }
        $covfile_array[] = $covfile;
    }
}

// Array to return to the datatable
$output = array(
    'sEcho' => intval($_GET['sEcho']),
    'aaData' => array()
);

$sorting_function_name = "sort_{$sortby}_{$sortdir}";
usort($covfile_array, $sorting_function_name);

$ncoveragefiles = 0;
$filestatus = -1;

foreach ($covfile_array as $covfile) {
    // Show only the low coverage
    if (isset($covfile['directory'])) {
        $filestatus = -1; //no
    } elseif ($covfile['covered'] == 0) {
        $filestatus = 0; //no
    } elseif ($covfile['covered'] == 1 && $covfile['percentcoverage'] == 0.0) {
        $filestatus = 1; //zero
    } elseif (($covfile['covered'] == 1 && $covfile['coveragemetric'] < $_GET['metricerror'])) {
        $filestatus = 2; //low
    } elseif ($covfile['covered'] == 1 && $covfile['coveragemetric'] == 1.0) {
        $filestatus = 5; //complete
    } elseif ($covfile['covered'] == 1 && $covfile['coveragemetric'] >= $_GET['metricpass']) {
        $filestatus = 4; // satisfactory
    } else {
        $filestatus = 3; // medium
    }
    if ($covfile['covered'] == 1 && $status == 6) {
        $filestatus = 6; // All
    }

    if ($status != $filestatus) {
        continue;
    }
    $ncoveragefiles++;
    if ($ncoveragefiles < $start) {
        continue;
    } elseif ($ncoveragefiles > $end) {
        break;
    }

    // For display purposes
    $roundedpercentage = round($covfile['percentcoverage']);
    if ($roundedpercentage > 98) {
        $roundedpercentage = 98;
    };

    // For display branch purposes
    if ($covfile['branchestested'] + $covfile['branchesuntested'] > 0 && $coveragetype == 'gcov') {
        $roundedpercentage2 = round($covfile['branchpercentcoverage']);
        if ($roundedpercentage2 > 98) {
            $roundedpercentage2 = 98;
        };
    }

    $row = array();

    // First column (Filename)
    if ($status == -1) {
        //directory view

        $row[] = '<a href="viewCoverage.php?buildid=' . $buildid . '&#38;status=6&#38;dir=' . $covfile['fullpath'] . '">' . $covfile['fullpath'] . '</a>';
    } elseif (!$covfile['covered'] || !$projectshowcoveragecode) {
        $row[] = $covfile['fullpath'];
    } else {
        $row[] = '<a href="viewCoverageFile.php?buildid=' . $buildid . '&#38;fileid=' . $covfile['fileid'] . '">' . $covfile['fullpath'] . '</a>';
    }

    // Second column (Status)
    switch ($status) {
        case 0:
            $row[] = 'No';
            break;
        case 1:
            $row[] = 'Zero';
            break;
        case 2:
            $row[] = 'Low';
            break;
        case 3:
            $row[] = 'Medium';
            break;
        case 4:
            $row[] = 'Satisfactory';
            break;
        case 5:
            $row[] = 'Complete';
            break;
        case 6:
        case -1:
            if ($covfile['covered'] == 0) {
                $row[] = 'N/A'; // No coverage
            } elseif ($covfile['covered'] == 1 && $covfile['percentcoverage'] == 0.0) {
                $row[] = 'Zero'; // zero
            } elseif (($covfile['covered'] == 1 && $covfile['coveragemetric'] < $_GET['metricerror'])) {
                $row[] = 'Low'; // low
            } elseif ($covfile['covered'] == 1 && $covfile['coveragemetric'] == 1.0) {
                $row[] = 'Complete'; //complete
            } elseif ($covfile['covered'] == 1 && $covfile['coveragemetric'] >= $_GET['metricpass']) {
                $row[] = 'Satisfactory'; // satisfactory
            } else {
                $row[] = 'Medium'; // medium
            }
            break;
    }

    // Third column (Percentage)
    $thirdcolumn = '<div style="position:relative; width: 190px;">
       <div style="position:relative; float:left;
       width: 123px; height: 12px; background: #bdbdbd url(\'img/progressbar.gif\') top left no-repeat;">
       <div class=';
    switch ($status) {
        case 0:
            $thirdcolumn .= '"error" ';
            break;
        case 1:
            $thirdcolumn .= '"error" ';
            break;
        case 2:
            $thirdcolumn .= '"error" ';
            break;
        case 3:
            $thirdcolumn .= '"warning" ';
            break;
        case 4:
            $thirdcolumn .= '"normal" ';
            break;
        case 5:
            $thirdcolumn .= '"normal" ';
            break;
        case 6:
        case -1:
            if (($covfile['coveragemetric'] < $_GET['metricerror'])) {
                $thirdcolumn .= '"error"'; //low
            } elseif ($covfile['coveragemetric'] == 1.0) {
                $thirdcolumn .= '"normal"'; //complete
            } elseif ($covfile['coveragemetric'] >= $_GET['metricpass']) {
                $thirdcolumn .= '"normal"'; // satisfactory
            } else {
                $thirdcolumn .= '"warning"'; // medium
            }
            break;
    }
    $thirdcolumn .= 'style="height: 10px;margin-left:1px; ';
    $thirdcolumn .= 'border-top:1px solid grey; border-top:1px solid grey; ';
    $thirdcolumn .= 'width:' . $roundedpercentage . '%;">';
    $thirdcolumn .= '</div></div><div class="percentvalue" style="position:relative; float:left; margin-left:10px">' . $covfile['percentcoverage'] . '%</div></div>';
    $row[] = $thirdcolumn;

    // Fourth column (Line not covered)
    $fourthcolumn = '';
    if ($coveragetype == 'gcov') {
        $fourthcolumn = '<span';
        if ($covfile['covered'] == 0) {
            $fourthcolumn .= ' class="error">' . $covfile['locuntested'] . '</span>';
        } else {
            // covered > 0

            switch ($status) {
                case 0:
                    $fourthcolumn .= ' class="error">';
                    break;
                case 1:
                    $fourthcolumn .= ' class="error">';
                    break;
                case 2:
                    $fourthcolumn .= ' class="error">';
                    break;
                case 3:
                    $fourthcolumn .= ' class="warning">';
                    break;
                case 4:
                    $fourthcolumn .= ' class="normal">';
                    break;
                case 5:
                    $fourthcolumn .= ' class="normal">';
                    break;
                case 6:
                case -1:
                    if (($covfile['coveragemetric'] < $_GET['metricerror'])) {
                        $fourthcolumn .= ' class="error">'; //low
                    } elseif ($covfile['coveragemetric'] == 1.0) {
                        $fourthcolumn .= ' class="normal">'; //complete
                    } elseif ($covfile['coveragemetric'] >= $_GET['metricpass']) {
                        $fourthcolumn .= ' class="normal">'; // satisfactory
                    } else {
                        $fourthcolumn .= ' class="warning">'; // medium
                    }
                    break;
            }
            $totalloc = $covfile['loctested'] + $covfile['locuntested'];
            $fourthcolumn .= $covfile['locuntested'] . '/' . $totalloc . '</span>';
        }
        $row[] = $fourthcolumn;
    } elseif ($coveragetype == 'bullseye') {
        $fourthcolumn = '<span';
        // branches
        if ($covfile['covered'] == 0) {
            $fourthcolumn .= ' class="error">' . $covfile['branchesuntested'] . '</span>';
        } else {
            // covered > 0

            switch ($status) {
                case 0:
                    $fourthcolumn .= ' class="error">';
                    break;
                case 1:
                    $fourthcolumn .= ' class="error">';
                    break;
                case 2:
                    $fourthcolumn .= ' class="error">';
                    break;
                case 3:
                    $fourthcolumn .= ' class="warning">';
                    break;
                case 4:
                    $fourthcolumn .= ' class="normal">';
                    break;
                case 5:
                    $fourthcolumn .= ' class="normal">';
                    break;
                case 6:
                case -1:
                    if (($covfile['coveragemetric'] < $_GET['metricerror'])) {
                        $fourthcolumn .= ' class="error">'; //low
                    } elseif ($covfile['coveragemetric'] == 1.0) {
                        $fourthcolumn .= ' class="normal">'; //complete
                    } elseif ($covfile['coveragemetric'] >= $_GET['metricpass']) {
                        $fourthcolumn .= ' class="normal">'; // satisfactory
                    } else {
                        $fourthcolumn .= ' class="warning">'; // medium
                    }
                    break;
            }
            $totalloc = @$covfile['branchestested'] + @$covfile['branchesuntested'];
            $fourthcolumn .= $covfile['branchesuntested'] . '/' . $totalloc . '</span>';
        }
        $row[] = $fourthcolumn;

        $fourthcolumn2 = '<span';
        //functions
        if ($covfile['covered'] == 0) {
            $fourthcolumn2 .= ' class="error">0</span>';
        } else {
            // covered > 0

            switch ($status) {
                case 0:
                    $fourthcolumn2 .= ' class="error">';
                    break;
                case 1:
                    $fourthcolumn2 .= ' class="error">';
                    break;
                case 2:
                    $fourthcolumn2 .= ' class="error">';
                    break;
                case 3:
                    $fourthcolumn2 .= ' class="warning">';
                    break;
                case 4:
                    $fourthcolumn2 .= ' class="normal">';
                    break;
                case 5:
                    $fourthcolumn2 .= ' class="normal">';
                    break;
                case 6:
                case -1:
                    if (($covfile['coveragemetric'] < $_GET['metricerror'])) {
                        $fourthcolumn2 .= ' class="error">'; //low
                    } elseif ($covfile['coveragemetric'] == 1.0) {
                        $fourthcolumn2 .= ' class="normal">'; //complete
                    } elseif ($covfile['coveragemetric'] >= $_GET['metricpass']) {
                        $fourthcolumn2 .= ' class="normal">'; // satisfactory
                    } else {
                        $fourthcolumn2 .= ' class="warning">'; // medium
                    }
                    break;
            }
            $totalfunctions = @$covfile['functionstested'] + @$covfile['functionsuntested'];
            $fourthcolumn2 .= $covfile['functionsuntested'] . '/' . $totalfunctions . '</span>';
        }
        $row[] = $fourthcolumn2;
    } else {
        // avoid displaying a DataTables warning to our user if coveragetype is
        // blank or unrecognized.
        $row[] = $fourthcolumn;
    }

    //Next column (Branch Percentage)
    if ($covfile['branchestested'] + $covfile['branchesuntested'] > 0 && $coveragetype == 'gcov') {
        $nextcolumn = '<div style="position:relative; width: 190px;">
           <div style="position:relative; float:left;
           width: 123px; height: 12px; background: #bdbdbd url(\'img/progressbar.gif\') top left no-repeat;">
           <div class=';
        switch ($status) {
            case 0:
                $nextcolumn .= '"error" ';
                break;
            case 1:
                $nextcolumn .= '"error" ';
                break;
            case 2:
                $nextcolumn .= '"error" ';
                break;
            case 3:
                $nextcolumn .= '"warning" ';
                break;
            case 4:
                $nextcolumn .= '"normal" ';
                break;
            case 5:
                $nextcolumn .= '"normal" ';
                break;
            case 6:
            case -1:
                if (($covfile['branchcoveragemetric'] < $_GET['metricerror'])) {
                    $nextcolumn .= '"error"'; //low
                } elseif ($covfile['branchcoveragemetric'] == 1.0) {
                    $nextcolumn .= '"normal"'; //complete
                } elseif ($covfile['branchcoveragemetric'] >= $_GET['metricpass']) {
                    $nextcolumn .= '"normal"'; // satisfactory
                } else {
                    $nextcolumn .= '"warning"'; // medium
                }
                break;
        }
        $nextcolumn .= 'style="height: 10px;margin-left:1px; ';
        $nextcolumn .= 'border-top:1px solid grey; border-top:1px solid grey; ';
        $nextcolumn .= 'width:' . $roundedpercentage2 . '%;">';
        $nextcolumn .= '</div></div><div class="percentvalue" style="position:relative; float:left; margin-left:10px">' . $covfile['branchpercentcoverage'] . '%</div></div>';
        $row[] = $nextcolumn;

        // Next column (branch not covered)
        $nextcolumn2 = '';
        if ($coveragetype == 'gcov') {
            $nextcolumn2 = '<span';
            if ($covfile['covered'] == 0) {
                $nextcolumn2 .= ' class="error">' . $covfile['branchestested'] . '</span>';
            } else {
                // covered > 0

                switch ($status) {
                    case 0:
                        $nextcolumn2 .= ' class="error">';
                        break;
                    case 1:
                        $nextcolumn2 .= ' class="error">';
                        break;
                    case 2:
                        $nextcolumn2 .= ' class="error">';
                        break;
                    case 3:
                        $nextcolumn2 .= ' class="warning">';
                        break;
                    case 4:
                        $nextcolumn2 .= ' class="normal">';
                        break;
                    case 5:
                        $nextcolumn2 .= ' class="normal">';
                        break;
                    case 6:
                    case -1:
                        if (($covfile['branchcoveragemetric'] < $_GET['metricerror'])) {
                            $nextcolumn2 .= ' class="error">'; //low
                        } elseif ($covfile['branchcoveragemetric'] == 1.0) {
                            $nextcolumn2 .= ' class="normal">'; //complete
                        } elseif ($covfile['branchcoveragemetric'] >= $_GET['metricpass']) {
                            $nextcolumn2 .= ' class="normal">'; // satisfactory
                        } else {
                            $nextcolumn2 .= ' class="warning">'; // medium
                        }
                        break;
                }
                $totalloc = @$covfile['branchestested'] + @$covfile['branchesuntested'];
                $nextcolumn2 .= $covfile['branchesuntested'] . '/' . $totalloc . '</span>';
            }
            $row[] = $nextcolumn2;
        }
    }

    // Fifth column (Priority)
    // Get the priority
    $priority = 'NA';
    switch ($covfile['priority']) {
        case 0:
            $priority = '<div>None</div>';
            break;
        case 1:
            $priority = '<div>Low</div>';
            break;
        case 2:
            $priority = '<div class="warning">Medium</div>';
            break;
        case 3:
            $priority = '<div class="error">High</div>';
            break;
        case 4:
            $priority = '<div class="error">Urgent</div>';
            break;
    }
    $row[] = $priority;

    // Sixth colum (Authors)
    if ($userid > 0) {
        $author = '';
        if (isset($covfile['user'])) {
            $User = new User();
            $User->Id = $covfile['user'];
            $author = $User->GetName();
        }
        $row[] = $author;
    }

    // Seventh colum (Label)
    if (isset($_GET['displaylabels']) && $_GET['displaylabels'] == 1) {
        $fileid = $covfile['fileid'];
        $labels = '';
        $coveragelabels = pdo_query('SELECT text FROM label, label2coveragefile WHERE ' .
            'label.id=label2coveragefile.labelid AND ' .
            "label2coveragefile.coveragefileid='$fileid' AND " .
            "label2coveragefile.buildid='$buildid' " .
            'ORDER BY text ASC');
        while ($coveragelabels_array = pdo_fetch_array($coveragelabels)) {
            if ($labels != '') {
                $labels .= ', ';
            }
            $labels .= $coveragelabels_array['text'];
        }

        $row[] = $labels;
    }

    $output['aaData'][] = $row;
}

switch ($status) {
    case -1:
        $output['iTotalRecords'] = $output['iTotalDisplayRecords'] = $_GET['ndirectories'];
        break;
    case 0:
        $output['iTotalRecords'] = $output['iTotalDisplayRecords'] = $_GET['nno'];
        break;
    case 1:
        $output['iTotalRecords'] = $output['iTotalDisplayRecords'] = $_GET['nzero'];
        break;
    case 2:
        $output['iTotalRecords'] = $output['iTotalDisplayRecords'] = $_GET['nlow'];
        break;
    case 3:
        $output['iTotalRecords'] = $output['iTotalDisplayRecords'] = $_GET['nmedium'];
        break;
    case 4:
        $output['iTotalRecords'] = $output['iTotalDisplayRecords'] = $_GET['nsatisfactory'];
        break;
    case 5:
        $output['iTotalRecords'] = $output['iTotalDisplayRecords'] = $_GET['ncomplete'];
        break;
    case 6:
        $output['iTotalRecords'] = $output['iTotalDisplayRecords'] = $_GET['nall'];
        break;
}

echo(json_encode(cast_data_for_JSON($output)));
