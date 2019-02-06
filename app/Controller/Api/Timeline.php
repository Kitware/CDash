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

use CDash\Database;
use CDash\Model\Build;
use CDash\Model\BuildGroup;
use CDash\Model\Project;
use CDash\ServiceContainer;

require_once 'include/api_common.php';
require_once 'include/filterdataFunctions.php';

class Timeline extends Index
{
    private $defectTypes;
    private $includeCleanBuilds;
    // timeData is used to record the data that will populate our
    // chart.  Its format is timeData[date][defect_type] = num_builds.
    private $timeData;
    // timeToDate is a mapping of timestamp (int) to date (str).
    // We record this info here we don't have to reimplement this logic
    // in Javascript.
    private $timeToDate;

    const ERROR = 0;
    const WARNING = 1;
    const FAILURE = 2;
    const CLEAN = 3;

    public function __construct(Database $db, Project $project)
    {
        parent::__construct($db, $project);
        $this->defectTypes = [];
        $this->includeCleanBuilds = true;
        $this->timeData = [];
        $this->timeToDate = [];

        $this->colors = [];
    }

    public function getResponse()
    {
        $this->filterdata = json_decode($_REQUEST['filterdata'], true);
        $page = $this->filterdata['pageId'];
        $this->filterSQL = generate_filterdata_sql($this->filterdata);
        $this->generateColorMap();

        $this->project->Fill();
        $response = [];
        $this->determineDateRange($response);

        // Generate data based on the page that's requesting this chart.
        switch ($page) {
            case 'buildProperties.php':
                return $this->chartForBuildProperties();
                break;
            case 'index.php':
                return $this->chartForIndex();
                break;
            case 'testOverview.php':
                return $this->chartForTestOverview();
                break;
            case 'viewBuildGroup.php':
                return $this->chartForBuildGroup();
                break;
            default:
                json_error_response("Unexpected value for page: $page");
                break;
        }
    }

    private function generateColorMap()
    {
        if (array_key_exists('colorblind', $this->filterdata) &&
                $this->filterdata['colorblind']) {
            $this->colors[self::ERROR] = '#fc8d59';
            $this->colors[self::WARNING] = '#ffffbf';
            $this->colors[self::FAILURE] = '#fee090';
            $this->colors[self::CLEAN] = '#91bfdb';
        } else {
            $this->colors[self::ERROR] = '#de6868';
            $this->colors[self::WARNING] = '#fd9e40';
            $this->colors[self::FAILURE] = '#ffff99';
            $this->colors[self::CLEAN] = '#bfefbf';
        }
    }

    private function chartForBuildProperties()
    {
        if (!isset($_SESSION['defecttypes'])) {
            json_error_response('No defecttypes defined in your session');
        }
        $this->defectTypes = $_SESSION['defecttypes'];

        // Construct an SQL SELECT clause for the requested types of defects.
        $defect_keys = [];
        foreach ($this->defectTypes as $type) {
            $defect_keys[] = "{$type['name']}";
        }
        $defect_selection = implode(', ', $defect_keys);
        $query =
            "SELECT id, $defect_selection, starttime
            FROM build b WHERE projectid = ? AND parentid IN (0, -1)
            ORDER BY starttime";
        $stmt = $this->db->prepare($query);
        if (!pdo_execute($stmt, [$this->project->Id])) {
            json_error_response('Failed to load results');
        }

        return $this->getTimelineChartData($stmt);
    }

    private function chartForIndex()
    {
        $this->defectTypes = [
            [
                'name' => 'builderrors',
                'prettyname' => 'Errors',
            ],
            [
                'name' => 'buildwarnings',
                'prettyname' => 'Warnings',
            ],
            [
                'name' => 'testfailed',
                'prettyname' => 'Test Failures',
            ]
        ];

        // Query for defects on expected builds only.
        $stmt = $this->db->prepare("
                SELECT b.id, b.starttime, b.builderrors, b.buildwarnings, b.testfailed
                FROM build b
                JOIN build2group b2g ON b2g.buildid = b.id
                JOIN build2grouprule b2gr ON
                b2g.groupid = b2gr.groupid AND b2gr.buildtype = b.type AND
                b2gr.buildname = b.name AND b2gr.siteid = b.siteid
                WHERE b.projectid = :projectid AND b.parentid IN (0, -1)
                AND b2gr.expected = 1
                $this->filterSQL
                ORDER BY starttime");
        if (!pdo_execute($stmt, [':projectid' => $this->project->Id])) {
            json_error_response('Failed to load results');
            return [];
        }
        $response = $this->getTimelineChartData($stmt);
        $response['colors'] = [
            $this->colors[self::ERROR],
            $this->colors[self::WARNING],
            $this->colors[self::FAILURE],
            $this->colors[self::CLEAN]
        ];
        return $response;
    }

    private function chartForTestOverview()
    {
        $this->defectTypes = [
            [
                'name' => 'testfailed',
                'prettyname' => 'Failing Tests',
            ],
            [
                'name' => 'testnotrun',
                'prettyname' => 'Not Run Tests',
            ],
            [
                'name' => 'testpassed',
                'prettyname' => 'Passing Tests',
            ]
        ];

        $stmt = $this->db->prepare("
                SELECT b.id, b.starttime, b.testfailed, b.testnotrun, b.testpassed
                FROM build b
                WHERE b.projectid = :projectid AND b.parentid IN (0, -1)
                $this->filterSQL
                ORDER BY starttime");
        if (!pdo_execute($stmt, [':projectid' => $this->project->Id])) {
            json_error_response('Failed to load results');
            return [];
        }
        $this->includeCleanBuilds = false;
        $response = $this->getTimelineChartData($stmt);
        $response['colors'] = [
            $this->colors[self::ERROR],
            $this->colors[self::WARNING],
            $this->colors[self::CLEAN]
        ];
        return $response;
    }

    private function chartForBuildGroup()
    {
        $groupname = urldecode(get_param('buildgroup'));
        $service = ServiceContainer::getInstance();
        $buildgroup = $service->create(BuildGroup::class);
        $buildgroup->SetProjectId($this->project->Id);
        $buildgroup->SetName($groupname);
        if (!$buildgroup->Exists()) {
            $error_msg =
                "BuildGroup '$groupname' does not exist for project '$project->Name'";
            json_error_response(['error' => $error_msg], 404);
            return[];
        }

        $this->defectTypes = [
            [
                'name' => 'builderrors',
                'prettyname' => 'Errors',
            ],
            [
                'name' => 'buildwarnings',
                'prettyname' => 'Warnings',
            ],
            [
                'name' => 'testfailed',
                'prettyname' => 'Test Failures',
            ]
        ];
        $colors = [
            $this->colors[self::ERROR],
            $this->colors[self::WARNING],
            $this->colors[self::FAILURE],
            $this->colors[self::CLEAN]
        ];
        $build_data = [];

        $group_type = $buildgroup->GetType();
        if ($group_type == 'Daily') {
            // Query for defects on builds from this group.
            $stmt = $this->db->prepare('
                    SELECT b.id, b.starttime, b.builderrors, b.buildwarnings,
                    b.testfailed
                    FROM build b
                    JOIN build2group b2g ON b2g.buildid = b.id
                    JOIN buildgroup bg ON bg.id = b2g.groupid
                    WHERE b.projectid = :projectid AND b.parentid IN (0, -1) AND
                    bg.name = :buildgroupname
                    ORDER BY starttime');
            $query_params = [
                ':projectid'      => $this->project->Id,
                ':buildgroupname' => $groupname
            ];
            if (!pdo_execute($stmt, $query_params)) {
                json_error_response('Failed to load results');
                return [];
            }
            $response = $this->getTimelineChartData($stmt);
            $response['colors'] = $colors;
            return $response;
        } elseif ($group_type == 'Latest') {
            $this->filterOnBuildGroup($groupname);

            // Save endDate before changing it.
            $end_date = $this->endDate;

            // Iterate backwards in time from now until builds stop appearing
            // in this group.
            $this->endDate = gmdate(FMT_DATETIME);
            $datetime = new \DateTime();
            // We want our date range to extend all the way through the current
            // testing day (to the beginning of tomorrow). So we add one extra
            // day to our range.
            $datetime->add(new \DateInterval('P1D'));
            $builds = [];
            while (true) {
                $dynamic_builds = $this->getDynamicBuilds();
                if (empty($dynamic_builds)) {
                    break;
                }
                // We want to associate builds with the testing day they occurred
                // during (not the next one) so we subtract a day here before
                // determining "build time".
                $datetime->sub(new \DateInterval('P1D'));
                $build_time = gmdate(FMT_DATETIME, $datetime->getTimestamp());
                foreach ($dynamic_builds as $dynamic_build) {
                    // Isolate the build fields that we need to make the chart.
                    $build = [];
                    $build['builderrors'] = $dynamic_build['countbuilderrors'];
                    $build['buildwarnings'] = $dynamic_build['countbuildwarnings'];
                    $build['testfailed'] = $dynamic_build['counttestsfailed'];
                    $build['starttime'] = $build_time;
                    $builds[] = $build;
                }
                unset($dynamic_builds);
                $this->endDate = gmdate(FMT_DATETIME, $datetime->getTimestamp());
            }
            $this->endDate = $end_date;
            $response = $this->getTimelineChartData($builds);
            $response['colors'] = $colors;
            return $response;
        }
    }

    private function getTimelineChartData($builds)
    {
        $response = [];
        $oldest_time_ms = null;
        $newest_time_ms = null;
        $nightly_timestamp = strtotime($this->project->NightlyTime);
        foreach ($builds as $build) {
            // Use this build's starttime to get the beginning of the appropriate
            // testing day.
            $test_date =
                Build::GetTestingDate($build['starttime'], $nightly_timestamp);
            list($unused, $start_of_day) =
                get_dates($test_date, $this->project->NightlyTime);

            // Convert timestamp to milliseconds for our JS charting library.
            $start_of_day_ms = $start_of_day * 1000;
            $this->initializeDate($start_of_day_ms, $test_date);

            // Keep track of oldest and newest date.
            if (is_null($oldest_time_ms) || $start_of_day_ms < $oldest_time_ms) {
                $oldest_time_ms = $start_of_day_ms;
            }
            if (is_null($newest_time_ms) || $start_of_day_ms > $newest_time_ms) {
                $newest_time_ms = $start_of_day_ms;
            }

            // Update our chart data to reflect this build's defects (if any).
            $clean_build = true;
            foreach ($this->defectTypes as $defect_type) {
                $key = $defect_type['name'];
                if ($build[$key] > 0) {
                    $this->timeData[$start_of_day_ms][$key] += 1;
                    $clean_build = false;
                }
            }
            if ($this->includeCleanBuilds && $clean_build) {
                $this->timeData[$start_of_day_ms]['clean'] += 1;
            }
        }

        if (is_null($oldest_time_ms)) {
            // No builds found.
            return [];
        }

        // Determine the range of the chart that should be selected by default.
        // This is referred to as the extent.
        list($unused, $begin_extent) = get_dates($this->beginDate, $this->project->NightlyTime);
        $begin_extent *= 1000;
        list($unused, $end_extent) = get_dates($this->endDate, $this->project->NightlyTime);
        $end_extent *= 1000;
        if ($begin_extent > $end_extent) {
            $begin_extent = $end_extent;
        }
        $response['extentstart'] = $begin_extent;
        $response['extentend'] = $end_extent;
        $this->initializeDate($begin_extent, gmdate(FMT_DATE, strtotime($this->beginDate)));
        $this->initializeDate($end_extent, gmdate(FMT_DATE, strtotime($this->endDate)));

        // Make sure the date range of our chart includes all the days specified
        // in the request.
        if ($oldest_time_ms > $begin_extent) {
            $oldest_time_ms = $begin_extent;
        }
        if ($newest_time_ms < $end_extent) {
            $newest_time_ms = $end_extent;
        }

        // Record min and max timestamps.
        $response['min'] = $oldest_time_ms;
        $response['max'] = $newest_time_ms;

        // Create empty entries for any dates in our range that did not have
        // any builds.
        $period = new \DatePeriod(
            new \DateTime($this->timeToDate[$oldest_time_ms]),
            new \DateInterval('P1D'),
            new \DateTime($this->timeToDate[$newest_time_ms]));
        foreach ($period as $datetime) {
            $date = $datetime->format('Y-m-d');
            list($unused, $start_of_day) = get_dates($date, $this->project->NightlyTime);
            $start_of_day_ms = $start_of_day * 1000;
            $this->initializeDate($start_of_day_ms, $date);
        }
        ksort($this->timeToDate);
        $response['time_to_date'] = $this->timeToDate;

        // Now that we've collected all this data, massage into the format used
        // by nvd3.
        // time_chart_data = [{
        //   key: defect_type,
        //   values = [[timestamp, num_builds], ...]
        //   }, ...]
        $chart_keys = [];
        foreach ($this->defectTypes as $defect_type) {
            $chart_keys[] = [
                'name' => $defect_type['name'],
                'prettyname' => $defect_type['prettyname']
            ];
        }
        if ($this->includeCleanBuilds) {
            $chart_keys[] = ['name' => 'clean', 'prettyname' => 'Clean Builds'];
        }

        ksort($this->timeData);
        $time_chart_data = [];
        foreach ($chart_keys as $key) {
            $trend = [];
            $trend['key'] = $key['prettyname'];
            $trend['values'] = [];
            foreach ($this->timeData as $start_of_day => $day_values) {
                $data_point = [$start_of_day, $day_values[$key['name']]];
                $trend['values'][] = $data_point;
            }
            $time_chart_data[] = $trend;
        }

        $response['data'] = $time_chart_data;
        return $response;
    }

    private function initializeDate($timestamp_ms, $date)
    {
        // Initialize trends for this date if necessary.
        if (!array_key_exists($timestamp_ms, $this->timeData)) {
            if ($this->includeCleanBuilds) {
                $this->timeData[$timestamp_ms] = ['clean' => 0];
            } else {
                $this->timeData[$timestamp_ms] = [];
            }
            foreach ($this->defectTypes as $defect_type) {
                $key = $defect_type['name'];
                $this->timeData[$timestamp_ms][$key] = 0;
            }
            $this->timeToDate[$timestamp_ms] = $date;
        }
    }
}
