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
    $filterdata = array();
    $filters = array();
    $sql = '';
    $clauses = 0;

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
    $filterdata['filtercount'] = $filtercount;

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

    if (strtolower($filtercombine) == 'or') {
        $sql_combine = 'OR';
    } else {
        $sql_combine = 'AND';
    }

    $sql = 'AND (';

    // Check for filters passed in via the query string
    for ($i = 1; $i <= $filtercount; ++$i) {
        if (empty($_REQUEST['field' . $i])) {
            continue;
        }
        $field = htmlspecialchars(pdo_real_escape_string($_REQUEST['field' . $i]));
        $compare = htmlspecialchars(pdo_real_escape_string($_REQUEST['compare' . $i]));
        $value = htmlspecialchars(pdo_real_escape_string($_REQUEST['value' . $i]));

        $cv = get_sql_compare_and_value($compare, $value);
        $sql_compare = $cv[0];
        $sql_value = $cv[1];

        $sql_field = $pageSpecificFilters->getSqlField($field);

        /* TODO: handle fieldtype.  currently defined in JS.
           Here's how its done the old way:
           $fieldinfo =  htmlspecialchars(pdo_real_escape_string($_REQUEST['field'.$i]));
           $fieldinfo = preg_split('#/#', $fieldinfo, 2);
           $field = $fieldinfo[0];
           $fieldtype = $fieldinfo[1];
           (end old way)

           if ($fieldtype == 'date')
           {
           $filterdata['hasdateclause'] = 1;
           }
         */

        // Treat the buildstamp field as if it were a date clause so that the
        // default date clause of "builds from today only" is not used...
        //
        if ($field == 'buildstamp') {
            $filterdata['hasdateclause'] = 1;
        }

        if ($sql_field != '' && $sql_compare != '') {
            if ($clauses > 0) {
                $sql .= ' ' . $sql_combine . ' ';
            }

            $sql .= $sql_field . ' ' . $sql_compare . ' ' . $sql_value;

            ++$clauses;
        }

        $filters[] = array(
            'key' => $field,
            'value' => $value,
            'compare' => $compare
        );
    }

    if ($clauses == 0) {
        $sql = '';
    } else {
        $sql .= ')';
    }

    $filterdata['sql'] = $sql;

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
            return array(
                'buildduration', 'builderrors', 'buildwarnings',
                'buildname', 'buildstamp', 'buildstarttime', 'buildtype',
                'configureduration', 'configureerrors', 'configurewarnings',
                'expected', 'groupname', 'hascoverage', 'hasctestnotes',
                'hasdynamicanalysis', 'hasusernotes', 'label', 'revision',
                'site', 'buildgenerator', 'subprojects', 'testsduration',
                'testsfailed', 'testsnotrun', 'testspassed',
                'testtimestatus', 'updateduration', 'updatedfiles');
            break;

        case 'indexchildren.php':
            return array(
                'buildduration', 'builderrors', 'buildwarnings',
                'buildstarttime', 'buildtype', 'configureduration',
                'configureerrors', 'configurewarnings', 'groupname',
                'hascoverage', 'hasctestnotes', 'hasdynamicanalysis',
                'hasusernotes', 'label', 'buildgenerator', 'subproject',
                'testsduration', 'testsfailed', 'testsnotrun',
                'testspassed', 'testtimestatus', 'updateduration',
                'updatedfiles');
            break;

        case 'queryTests.php':
            return array(
                'buildname', 'buildstarttime', 'details', 'groupname', 'label',
                'site', 'status', 'testname', 'time');
            break;

        case 'viewCoverage.php':
        case 'getviewcoverage.php':
            return array(
                'coveredlines', 'filename', 'labels', 'priority',
                'totallines', 'uncoveredlines');
            break;

        case 'viewTest.php':
            return array('details', 'label', 'status', 'testname',
                'timestatus', 'time');
            break;

        case 'testOverview.php':
            return array('buildname', 'subproject', 'testname');
            break;

        case 'compareCoverage.php':
            return array('subproject');
            break;

        default:
            return array();
            break;
    }
}

// Get the default filter for a given page.
function getDefaultFilter($page_id)
{
    switch ($page_id) {
        case 'index.php':
        case 'project.php': {
            return array('key' => 'site', 'value' => '', 'compare' => 63);
        }

        case 'indexchildren.php':
        case 'compareCoverage.php': {
            return array('key' => 'subproject', 'value' => '', 'compare' => 61);
        }

        case 'queryTests.php':
        case 'viewTest.php': {
            return array('key' => 'testname', 'value' => '', 'compare' => 63);
        }

        case 'testOverview.php':
            return array('key' => 'buildname', 'value' => '', 'compare' => 63);

        case 'viewCoverage.php':
        case 'getviewcoverage.php': {
            return array('key' => 'filename', 'value' => '', 'compare' => 63);
        }

        default: {
            return array();
        }
    }
}
