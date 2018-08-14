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

include dirname(dirname(dirname(__DIR__))) . '/config/config.php';
require_once 'include/pdo.php';
include_once 'include/common.php';
include_once 'include/filterdataFunctions.php';

$response = get_filterdata_array_from_request($_GET['page_id']);
echo json_encode(cast_data_for_JSON($response));

// Parse filter data from the request into an array.
//
function get_filterdata_array_from_request($page_id = '')
{
    $filterdata = [];
    $filters = [];

    if (empty($page_id)) {
        $pos = strrpos($_SERVER['SCRIPT_NAME'], '/');
        $page_id = substr($_SERVER['SCRIPT_NAME'], $pos + 1);
    }
    $filterdata['availablefilters'] = getFiltersForPage($page_id);

    $pageSpecificFilters = createPageSpecificFilters($page_id);

    if (isset($_GET['value1']) && strlen($_GET['value1']) > 0) {
        $filtercount = $_GET['filtercount'];
    } else {
        $filtercount = 0;
    }

    $showfilters = pdo_real_escape_numeric(@$_REQUEST['showfilters']);
    if ($showfilters) {
        $filterdata['showfilters'] = 1;
    } else {
        if ($filtercount > 0) {
            $filterdata['showfilters'] = 1;
        } else {
            $filterdata['showfilters'] = 0;
        }
    }

    $showlimit = pdo_real_escape_numeric(@$_REQUEST['showlimit']);
    if ($showlimit) {
        $filterdata['showlimit'] = 1;
    } else {
        $filterdata['showlimit'] = 0;
    }

    $limit = intval(pdo_real_escape_numeric(@$_REQUEST['limit']));
    if (!is_int($limit)) {
        $limit = 0;
    }
    $filterdata['limit'] = $limit;

    @$filtercombine = htmlspecialchars(pdo_real_escape_string($_REQUEST['filtercombine']));
    $filterdata['filtercombine'] = $filtercombine;

    // Check for filters passed in via the query string
    for ($i = 1; $i <= $filtercount; ++$i) {
        if (empty($_REQUEST['field' . $i])) {
            continue;
        }
        $field = htmlspecialchars(pdo_real_escape_string($_REQUEST['field' . $i]));
        if ($field == 'block') {
            // Handle filter blocks here.
            $filter = [
                'filters' => []
            ];
            $subfiltercount = pdo_real_escape_numeric($_REQUEST["field{$i}count"]);
            for ($j = 1; $j <= $subfiltercount; ++$j) {
                $field = htmlspecialchars(pdo_real_escape_string($_REQUEST["field{$i}field{$j}"]));
                $compare = htmlspecialchars(pdo_real_escape_string($_REQUEST["field{$i}compare{$j}"]));
                $value = htmlspecialchars(pdo_real_escape_string($_REQUEST["field{$i}value{$j}"]));
                $filter['filters'][] = [
                    'key' => $field,
                    'value' => $value,
                    'compare' => $compare
                ];
            }
            $filters[] = $filter;
            continue;
        }
        $compare = htmlspecialchars(pdo_real_escape_string($_REQUEST['compare' . $i]));
        $value = htmlspecialchars(pdo_real_escape_string($_REQUEST['value' . $i]));
        $filters[] = [
            'key' => $field,
            'value' => $value,
            'compare' => $compare
        ];
    }

    // If no filters were passed in as parameters,
    // then add one default filter so that the user sees
    // somewhere to enter filter queries in the GUI:
    //
    if (count($filters) === 0) {
        $filters[] = getDefaultFilter($page_id);
    }
    $filterdata['filters'] = $filters;
    return $filterdata;
}

/**
 * Similar to createPageSpecificFilters, but it just returns a list of filter
 * names which is handled in javascript.
 **/
function getFiltersForPage($page_id)
{
    switch ($page_id) {
        case 'index.php':
        case 'project.php':
            return [
                'buildduration', 'builderrors', 'buildwarnings',
                'buildname', 'buildstamp', 'buildstarttime', 'buildtype',
                'configureduration', 'configureerrors', 'configurewarnings',
                'expected', 'groupname', 'hascoverage', 'hasctestnotes',
                'hasdynamicanalysis', 'hasusernotes', 'label', 'revision',
                'site', 'buildgenerator', 'subprojects', 'testsduration',
                'testsfailed', 'testsnotrun', 'testspassed',
                'testtimestatus', 'updateduration', 'updatedfiles'];
            break;

        case 'indexchildren.php':
            return [
                'buildduration', 'builderrors', 'buildwarnings',
                'buildstarttime', 'buildtype', 'configureduration',
                'configureerrors', 'configurewarnings', 'groupname',
                'hascoverage', 'hasctestnotes', 'hasdynamicanalysis',
                'hasusernotes', 'label', 'buildgenerator', 'subproject',
                'testsduration', 'testsfailed', 'testsnotrun',
                'testspassed', 'testtimestatus', 'updateduration',
                'updatedfiles'];
            break;

        case 'queryTests.php':
            return [
                'buildname', 'buildstarttime', 'details', 'groupname', 'label',
                'site', 'status', 'testname', 'time'];
            break;

        case 'viewCoverage.php':
        case 'getviewcoverage.php':
            return [
                'coveredlines', 'filename', 'labels', 'priority',
                'totallines', 'uncoveredlines' ];
            break;

        case 'viewTest.php':
            return ['details', 'label', 'status', 'testname',
                'timestatus', 'time'];
            break;

        case 'testOverview.php':
            return ['buildname', 'subproject', 'testname'];
            break;

        case 'compareCoverage.php':
            return ['subproject'];
            break;

        default:
            return [];
            break;
    }
}

// Get the default filter for a given page.
function getDefaultFilter($page_id)
{
    switch ($page_id) {
        case 'index.php':
        case 'project.php': {
            return ['key' => 'site', 'value' => '', 'compare' => 63];
        }

        case 'indexchildren.php':
        case 'compareCoverage.php': {
            return ['key' => 'subproject', 'value' => '', 'compare' => 61];
        }

        case 'queryTests.php':
        case 'viewTest.php': {
            return ['key' => 'testname', 'value' => '', 'compare' => 63];
        }

        case 'testOverview.php':
            return ['key' => 'buildname', 'value' => '', 'compare' => 63];

        case 'viewCoverage.php':
        case 'getviewcoverage.php': {
            return ['key' => 'filename', 'value' => '', 'compare' => 63];
        }

        default: {
            return [];
        }
    }
}
