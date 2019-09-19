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
include_once 'include/common.php';
include_once 'include/filterdataFunctions.php';

$response = get_filterdata_array_from_request($_GET['page_id']);
echo json_encode(cast_data_for_JSON($response));

// Parse filter data from the request into an array.
//
function get_filterdata_array_from_request($page_id = '')
{
    $filterdata = get_filterdata_from_request($page_id);
    $fields_to_preserve = [
        'filters',
        'filtercombine',
        'limit',
        'othercombine',
        'showfilters',
        'showlimit'
    ];
    foreach ($filterdata as $key => $value) {
        if (!in_array($key, $fields_to_preserve)) {
            unset($filterdata[$key]);
        }
    }
    $filterdata['availablefilters'] = getFiltersForPage($page_id);
    $filterdata['showdaterange'] = isDatePage($page_id);
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
        case 'viewBuildGroup.php':
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
                'hasusernotes', 'label', 'buildgenerator', 'subprojects',
                'testsduration', 'testsfailed', 'testsnotrun',
                'testspassed', 'testtimestatus', 'updateduration',
                'updatedfiles'];
            break;

        case 'queryTests.php':
            return [
                'buildname', 'buildstarttime', 'details', 'groupname', 'label',
                'site', 'status', 'testname', 'testoutput', 'time'];
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

function isDatePage($page_id)
{
    switch ($page_id) {
        case 'compareCoverage.php':
        case 'index.php':
        case 'indexchildren.php':
        case 'project.php':
        case 'queryTests.php':
        case 'testOverview.php':
        case 'viewBuildGroup.php':
            return true;
            break;

        case 'getviewcoverage.php':
        case 'viewCoverage.php':
        case 'viewTest.php':
        default:
            return false;
            break;
    }
}
