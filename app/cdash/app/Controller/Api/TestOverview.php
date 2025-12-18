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

use App\Services\ProjectService;
use CDash\Database;
use CDash\Model\BuildGroup;
use CDash\Model\Project;

class TestOverview extends ResultsApi
{
    public function __construct(Database $db, Project $project)
    {
        parent::__construct($db, $project);
    }

    public function getResponse(): array
    {
        $has_subprojects = ProjectService::getNumberOfSubProjects((int) $this->project->Id) > 0;

        // Begin our JSON response.
        $response = begin_JSON_response();
        $response['title'] = "{$this->project->Name} - Test Overview";
        $response['showcalendar'] = 1;
        $response['hassubprojects'] = $has_subprojects;

        // Handle the optional arguments that dictate our time range.
        $this->determineDateRange($response);

        // Check if the user specified a buildgroup.
        $groupid = get_param('group', false) ?: 0;
        if ($groupid) {
            $group_join = 'JOIN build2group b2g ON (b2g.buildid=b.id)';
            $group_clause = 'b2g.groupid=:groupid';
            $group_link = "&group=$groupid";
        } else {
            $group_join = '';
            $group_clause = "b.type != 'Experimental'";
            $group_link = '';
        }
        $response['groupid'] = $groupid;

        // Handle optional "showpassed" argument.
        $showpassed = false;
        if (isset($_GET['showpassed']) && (int) $_GET['showpassed'] === 1) {
            $showpassed = true;
        }
        if ($showpassed) {
            $response['showpassed'] = 1;
        } else {
            $response['showpassed'] = 0;
        }

        get_dashboard_JSON($this->project->Name, $this->date, $response);

        // Setup the menu of relevant links.
        $menu = [];
        $menu['previous'] = 'testOverview.php?project=' . urlencode($this->project->Name) . "&date={$this->previousDate}$group_link";
        if (date(FMT_DATE, $this->currentStartTime) != date(FMT_DATE)) {
            $menu['next'] = 'testOverview.php?project=' . urlencode($this->project->Name) . "&date={$this->nextDate}$group_link";
        } else {
            $menu['next'] = false;
        }
        $today = date(FMT_DATE);
        $menu['current'] = 'testOverview.php?project=' . urlencode($this->project->Name) . "&date=$today$group_link";
        $menu['back'] = 'index.php?project=' . urlencode($this->project->Name) . "&date=$this->date";
        $response['menu'] = $menu;

        // List all active buildgroups for this project.
        $buildgroups = BuildGroup::GetBuildGroups($this->project->Id, $this->beginDate);
        $groups_response = [];

        // Begin with an entry for the default "Non-Experimental Builds" selection.
        $default_group = [];
        $default_group['id'] = 0;
        $default_group['name'] = 'Non-Experimental Builds';
        $default_group['position'] = 0;
        $groups_response[] = $default_group;

        foreach ($buildgroups as $buildgroup) {
            $group_response = [];
            $group_response['id'] = $buildgroup->GetId();
            $group_response['name'] = $buildgroup->GetName();
            $group_response['position'] = $buildgroup->GetPosition();
            $groups_response[] = $group_response;
        }
        $response['groups'] = $groups_response;

        // Filters
        $filterdata = get_filterdata_from_request();
        unset($filterdata['xml']);
        $response['filterdata'] = $filterdata;
        $filter_sql = $filterdata['sql'];
        $response['filterurl'] = get_filterurl();

        $sp_select = '';
        $sp_join = '';
        if ($has_subprojects) {
            $sp_select = ', sp.name AS subproject';
            $sp_join = '
                JOIN subproject AS sp ON (b.subprojectid=sp.id)';
        }

        // Main query: find all the requested tests.
        $stmt = $this->db->prepare(
            "SELECT b2t.testname, b2t.details, b2t.status, b2t.time $sp_select FROM build b
                JOIN build2test b2t ON (b2t.buildid=b.id)
                $group_join
                $sp_join
                WHERE b.projectid = :projectid AND b.parentid != -1 AND $group_clause
                AND b.starttime < :end AND b.starttime >= :begin
                $filter_sql");
        $stmt->bindParam(':projectid', $this->project->Id);
        $stmt->bindParam(':begin', $this->beginDate);
        $stmt->bindParam(':end', $this->endDate);
        if ($groupid > 0) {
            $stmt->bindParam(':groupid', $groupid);
        }
        $this->db->execute($stmt);

        $tests_response[] = [];
        $all_tests = [];
        while ($row = $stmt->fetch()) {
            // Only track tests that passed or failed.
            $status = $row['status'];
            if ($status !== 'passed' && $status !== 'failed') {
                continue;
            }

            $test_name = $row['testname'];
            if (!array_key_exists($test_name, $all_tests)) {
                $test = [];
                $test['name'] = $test_name;
                if ($has_subprojects) {
                    $test['subproject'] = $row['subproject'];
                }
                $test['passed'] = 0;
                $test['failed'] = 0;
                $test['timeout'] = 0;
                $test['time'] = $row['time'];
                $all_tests[$test_name] = $test;
            }

            if ($status === 'passed') {
                $all_tests[$test_name]['passed']++;
            } elseif (str_contains($row['details'], 'Timeout')) {
                $all_tests[$test_name]['timeout']++;
            } else {
                $all_tests[$test_name]['failed']++;
            }
            if ($row['time'] > $all_tests[$test_name]['time']) {
                $all_tests[$test_name]['time'] = $row['time'];
            }
        }

        // Compute fail percentage for each test found.
        $tests_response = [];
        foreach ($all_tests as $name => $test) {
            $total_runs = $test['passed'] + $test['failed'] + $test['timeout'];
            // Avoid divide by zero.
            if ($total_runs === 0) {
                continue;
            }
            // Only include tests that failed at least once unless the user requests
            // all tests.
            if (!$showpassed && $test['failed'] === 0 && $test['timeout'] === 0) {
                continue;
            }

            $test_response = [];
            $test_response['name'] = $name;
            if ($has_subprojects) {
                $test_response['subproject'] = $test['subproject'];
            }
            $test_response['failpercent'] =
                round(($test['failed'] / $total_runs) * 100, 2);
            $test_response['timeoutpercent'] =
                round(($test['timeout'] / $total_runs) * 100, 2);
            $test_response['link'] = "queryTests.php?project={$this->project->Name}&filtercount=1&showfilters=1&field1=testname&compare1=61&value1={$name}&date={$this->date}";
            $test_response['totalruns'] = $total_runs;
            $test_response['prettytime'] = time_difference($test['time'], true, '', true);
            $test_response['time'] = $test['time'];
            $tests_response[] = $test_response;
        }

        $response['tests'] = $tests_response;

        $this->pageTimer->end($response);
        return $response;
    }
}
