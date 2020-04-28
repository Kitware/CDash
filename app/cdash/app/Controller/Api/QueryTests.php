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

namespace CDash\Controller\Api;

use App\Models\TestOutput;

use CDash\Config;
use CDash\Database;
use CDash\Model\Build;
use CDash\Model\Project;

require_once 'include/filterdataFunctions.php';

class QueryTests extends ResultsApi
{
    public function __construct(Database $db, Project $project)
    {
        parent::__construct($db, $project);
        $this->filterOnBuildGroup = false;
        $this->filterOnTestOutput = false;

        $this->testOutputInclude = [];
        $this->testOutputIncludeRegex = [];
        $this->testOutputExclude = [];
        $this->testOutputExcludeRegex = [];

        $this->delimiters = ['/', '#', '%', '~', '+', '!', '@', '_', ';', '`',
                             '-', '=', ','];
    }

    private function checkForSpecialFilters($filterdata)
    {
        $filters = $this->flattenFilters();
        foreach ($filters as $filter) {
            if ($filter['field'] == 'groupname') {
                $this->filterOnBuildGroup = true;
            } elseif ($filter['field'] == 'testoutput') {
                $this->filterOnTestOutput = true;
                if ($filter['compare'] == 94) {
                    $this->testOutputExclude[] = $filter['value'];
                } elseif ($filter['compare'] == 95) {
                    $this->testOutputInclude[] = $filter['value'];
                } elseif ($filter['compare'] == 96) {
                    $this->testOutputExcludeRegex[] = $filter['value'];
                } elseif ($filter['compare'] == 97) {
                    $this->testOutputIncludeRegex[] = $filter['value'];
                }
            }
        }
    }

    private function rowSurvivesTestOutputFilter($row, &$build)
    {
        if (!$this->filterOnTestOutput) {
            return true;
        }

        $test_output = TestOutput::DecompressOutput($row['output']);

        // Make sure test output matches (or does not match) the
        // specified filter values.
        $first_match_idx = false;
        $match_length = 0;

        foreach ($this->testOutputExclude as $exclude) {
            if (strpos($test_output, $exclude) !== false) {
                return false;
            }
        }

        foreach ($this->testOutputExcludeRegex as $exclude_regex) {
            $exclude_regex = $this->applySafeDelimiter($exclude_regex);
            if (preg_match($exclude_regex, $test_output)) {
                return false;
            }
        }

        foreach ($this->testOutputInclude as $include) {
            $idx = strpos($test_output, $include);
            if ($idx === false) {
                return false;
            }
            if (!$first_match_idx) {
                $first_match_idx = $idx;
                $match_length = strlen($include);
            }
        }

        foreach ($this->testOutputIncludeRegex as $include_regex) {
            $include_regex = $this->applySafeDelimiter($include_regex);
            if (preg_match($include_regex, $test_output, $matches,
                        PREG_OFFSET_CAPTURE)) {
                if (!$first_match_idx) {
                    $first_match_idx = $matches[0][1];
                    $match_length = strlen($include_regex);
                }
            } else {
                return false;
            }
        }

        // Isolate a relevant subset of the test output to display.
        $context_size = 200;
        if ($this->testOutputInclude || $this->testOutputIncludeRegex) {
            // Showing tests whose output includes some string(s).
            // Show context surrounding the first filter specified.
            $pre_post_context_size = ($context_size - $match_length) / 2;
            if ($first_match_idx < $pre_post_context_size) {
                // Match shows up near the beginning, start context from there.
                $build['matchingoutput'] = substr($test_output, 0, $context_size);
            } elseif ($first_match_idx > (strlen($test_output) - ($context_size / 2))) {
                // Match shows up near the end, show the end of test output.
                $build['matchingoutput'] = substr($test_output, -$context_size);
            } else {
                // Show context surrounding the match.
                $build['matchingoutput'] =
                    substr($test_output,
                            $first_match_idx - $pre_post_context_size,
                            $context_size);
            }
        } else {
            // Showing tests whose output does NOT include some string(s).
            // Show the end of test output.
            $build['matchingoutput'] = substr($test_output, -$context_size);
        }

        return true;
    }

    // Find and apply a safe delimiter for converting a substring into a
    // regular expression.
    private function applySafeDelimiter($pattern)
    {
        foreach ($this->delimiters as $delimiter) {
            if (strpos($pattern, $delimiter) === false) {
                return $delimiter . $pattern . $delimiter;
            }
        }
        return $pattern;
    }

    public function getResponse()
    {
        $response = begin_JSON_response();
        $response['title'] = "CDash : {$this->project->Name}";
        $response['showcalendar'] = 1;

        // If parentid is set we need to lookup the date for this build
        // because it is not specified as a query string parameter.
        if (isset($_GET['parentid'])) {
            $parentid = pdo_real_escape_numeric($_GET['parentid']);
            $parent_build = new Build();
            $parent_build->Id = $parentid;
            $this->setDate($parent_build->GetDate());
        } else {
            // Handle the optional arguments that dictate our time range.
            $this->determineDateRange($response);
        }

        get_dashboard_JSON_by_name($this->project->Name, $this->date, $response);

        list($previousdate, $currentstarttime, $nextdate) =
            get_dates($this->date, $this->project->NightlyTime);

        // Filters
        $filterdata = get_filterdata_from_request();
        unset($filterdata['xml']);
        $response['filterdata'] = $filterdata;
        $this->setFilterData($filterdata);
        $response['filterurl'] = get_filterurl();

        // Menu
        $menu = [];
        $limit_param = '&limit=' . $filterdata['limit'];
        $base_url = 'queryTests.php?project=' . urlencode($this->project->Name);
        if (isset($_GET['parentid'])) {
            // When a parentid is specified, we should link to the next build,
            // not the next day.
            $previous_buildid = $parent_build->GetPreviousBuildId();
            $current_buildid = $parent_build->GetCurrentBuildId();
            $next_buildid = $parent_build->GetNextBuildId();

            $menu['back'] = 'index.php?project=' . urlencode($this->project->Name) . '&parentid=' . $_GET['parentid'];

            if ($previous_buildid > 0) {
                $menu['previous'] = "$base_url&parentid=$previous_buildid" . $limit_param;
            } else {
                $menu['previous'] = false;
            }

            $menu['current'] = "$base_url&parentid=$current_buildid" . $limit_param;

            if ($next_buildid > 0) {
                $menu['next'] = "$base_url&parentid=$next_buildid" . $limit_param;
            } else {
                $menu['next'] = false;
            }
        } else {
            if ($this->date == '') {
                $back = 'index.php?project=' . urlencode($this->project->Name);
            } else {
                $back = 'index.php?project=' . urlencode($this->project->Name) . '&date=' . $this->date;
            }
            $menu['back'] = $back;

            $menu['previous'] = $base_url . '&date=' . $previousdate . $limit_param;

            $menu['current'] = $base_url . $limit_param;

            if (has_next_date($this->date, $currentstarttime)) {
                $menu['next'] = $base_url . '&date=' . $nextdate . $limit_param;
            } else {
                $menu['next'] = false;
            }
        }

        $response['menu'] = $menu;

        // Project
        $project_response = [];
        $project_response['showtesttime'] = $this->project->ShowTestTime;
        $response['project'] = $project_response;

        // Get information about all the builds for the given project
        // and date range.
        $builds = [];

        // Add the date/time
        $builds['projectid'] = $this->project->Id;
        $builds['currentstarttime'] = $this->currentStartTime;
        $builds['teststarttime'] = $this->beginDate;
        $builds['testendtime'] = $this->endDate;

        // Start constructing the main SQL query for this page.
        $pdo = Database::getInstance()->getPdo();
        $query_params = [];

        // Check if we should display 'Proc Time'.
        $response['hasprocessors'] = false;
        $proc_select = '';
        $proc_join = '';
        $stmt = $pdo->prepare(
                "SELECT * FROM measurement WHERE projectid = ? AND name = 'Processors'");
        pdo_execute($stmt, [$this->project->Id]);
        $row = $stmt->fetch();
        if ($row['summarypage'] == 1) {
            $response['hasprocessors'] = true;
            $proc_select = ', tm.value';
            $proc_join =
                "LEFT JOIN testmeasurement tm ON (build2test.outputid = tm.outputid AND tm.name = 'Processors')";
        }

        $date_clause = '';
        $parent_clause = '';
        if (isset($_GET['parentid'])) {
            // If we have a parentid, then we should only show children of that build.
            // Date becomes irrelevant in this case.
            $parent_clause = 'AND b.parentid = :parentid';
            $query_params[':parentid'] = $_GET['parentid'];
        } elseif (!$filterdata['hasdateclause']) {
            $date_clause = 'AND b.starttime >= :starttime AND b.starttime < :endtime';
            $query_params[':starttime'] = $this->beginDate;
            $query_params[':endtime'] = $this->endDate;
        }

        // Check for the presence of a filters that modify our querying behavior.
        $this->checkForSpecialFilters($filterdata);

        // If we are filtering on Build Groups we need to join additional tables into our query.
        $filter_joins = '';
        if ($this->filterOnBuildGroup) {
            $filter_joins = '
                JOIN build2group b2g ON b2g.buildid = b.id
                JOIN buildgroup bg ON bg.id = b2g.groupid';
        }

        // Select extra data if we are filtering on test output.
        $output_select = '';
        $output_joins = '';
        if ($this->filterOnTestOutput) {
            $output_joins = '
                JOIN testoutput ON (testoutput.id = build2test.outputid)';
            $output_select = ', testoutput.output';
        }

        $sql = "SELECT b.id, b.name, b.starttime, b.siteid, b.parentid,
            build2test.testid AS testid, build2test.details, build2test.status,
            build2test.time, build2test.timestatus, site.name AS sitename,
            test.name AS testname $proc_select $output_select
                FROM build AS b
                JOIN build2test ON (b.id = build2test.buildid)
                JOIN site ON (b.siteid = site.id)
                JOIN test ON (test.id = build2test.testid)
                $proc_join
                $filter_joins
                $output_joins
                WHERE b.projectid = :projectid
                $parent_clause $date_clause $this->filterSQL
                ORDER BY build2test.status, test.name
                $this->limitSQL";
        $query_params[':projectid'] = $this->project->Id;
        $stmt = $pdo->prepare($sql);
        pdo_execute($stmt, $query_params);

        // Builds
        $config = Config::getInstance();
        $builds = [];
        while ($row = $stmt->fetch()) {
            $build = [];

            if (!$this->rowSurvivesTestOutputFilter($row, $build)) {
                continue;
            }

            $buildid = $row['id'];
            $testid = $row['testid'];

            $build['testname'] = $row['testname'];
            $build['site'] = $row['sitename'];
            $build['buildName'] = $row['name'];

            $build['buildstarttime'] =
                date(FMT_DATETIMETZ, strtotime($row['starttime'] . ' UTC'));
            // use the default timezone, same as index.php

            $build['time'] = $row['time'];
            $build['prettyTime'] = time_difference($build['time'], true, '', true);

            $build['details'] = $row['details'] . "\n";

            $siteLink = 'viewSite.php?siteid=' . $row['siteid'];
            $build['siteLink'] = $siteLink;

            $buildSummaryLink = "build/$buildid";
            $build['buildSummaryLink'] = $buildSummaryLink;

            $testDetailsLink = "testDetails.php?test=$testid&build=$buildid";
            $build['testDetailsLink'] = $testDetailsLink;

            switch ($row['status']) {
                case 'passed':
                    $build['status'] = 'Passed';
                    $build['statusclass'] = 'normal';
                    break;

                case 'failed':
                    $build['status'] = 'Failed';
                    $build['statusclass'] = 'error';
                    break;

                case 'notrun':
                    $build['status'] = 'Not Run';
                    $build['statusclass'] = 'warning';
                    break;
            }

            if ($this->project->ShowTestTime) {
                if ($row['timestatus'] < $this->project->TestTimeMaxStatus) {
                    $build['timestatus'] = 'Passed';
                    $build['timestatusclass'] = 'normal';
                } else {
                    $build['timestatus'] = 'Failed';
                    $build['timestatusclass'] = 'error';
                }
            }

            if ($response['hasprocessors']) {
                $num_procs = $row['value'];
                $build['nprocs'] = $num_procs;
                if (!$num_procs) {
                    $num_procs = 1;
                    $build['nprocs'] = 'N/A';
                }
                $build['procTime'] = $row['time'] * $num_procs;
                $build['prettyProcTime'] = time_difference($build['procTime'], true, '', true);
            }

            $builds[] = $build;
        }
        $response['builds'] = $builds;
        $response['filterontestoutput'] = $this->filterOnTestOutput;

        $this->pageTimer->end($response);
        return $response;
    }
}
