<?php

namespace App\Http\Controllers;

use App\Utils\PageTimer;
use CDash\Database;
use CDash\Model\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

final class ProjectOverviewController extends AbstractProjectController
{
    public function overview(): Response
    {
        return response()->angular_view('overview');
    }

    public function apiOverview(): JsonResponse
    {
        $this->setProjectByName(htmlspecialchars($_GET['project'] ?? ''));

        $pageTimer = new PageTimer();

        // Check if this project has SubProjects.
        $has_subprojects = $this->project->GetNumberOfSubProjects() > 0;

        // Handle optional date argument.
        $date = htmlspecialchars($_GET['date'] ?? date(FMT_DATE));
        [$previousdate, $currentstarttime, $nextdate] = get_dates($date, $this->project->NightlyTime);

        // Date range is currently hardcoded to two weeks in the past.
        // This could become a configurable value instead.
        $date_range = 14;

        // begin JSON response that is used to render this page
        $response = begin_JSON_response();
        get_dashboard_JSON_by_name($this->project->Name, $date, $response);
        $response['title'] = "{$this->project->Name} - Overview";
        $response['showcalendar'] = 1;

        $menu['previous'] = "overview.php?project={$this->project->Name}&date=$previousdate";
        $menu['current'] = "overview.php?project={$this->project->Name}";
        $menu['next'] = "overview.php?project={$this->project->Name}&date=$nextdate";
        $response['menu'] = $menu;
        $response['hasSubProjects'] = $has_subprojects;

        // configure/build/test data that we care about.
        $build_measurements = [
            'configure warnings',
            'configure errors',
            'build warnings',
            'build errors',
            'failing tests',
        ];

        // sanitized versions of these measurements.
        $clean_measurements = [
            'configure warnings' => 'configure_warnings',
            'configure errors'   => 'configure_errors',
            'build warnings'     => 'build_warnings',
            'build errors'       => 'build_errors',
            'failing tests'      => 'failing_tests'];

        // for static analysis, we only care about errors & warnings.
        $static_measurements = ['errors', 'warnings'];

        // information on how to sort by the various build measurements
        $sort = [
            'configure warnings' => '-configure.warning',
            'configure errors'   => '-configure.error',
            'build warnings'     => '-compilation.warning',
            'build errors'       => '-compilation.error',
            'failing tests'      => '-test.fail'];

        // get the build groups that are included in this project's overview,
        // split up by type (currently only static analysis and general builds).
        $query = DB::select('
                     SELECT bg.id, bg.name, obg.type
                     FROM overview_components AS obg
                     LEFT JOIN buildgroup AS bg ON (obg.buildgroupid = bg.id)
                     WHERE obg.projectid = ?
                     ORDER BY obg.position
                 ', [$this->project->Id]);

        $build_groups = [];
        $static_groups = [];

        foreach ($query as $group_row) {
            if ($group_row->type === 'build') {
                $build_groups[] = [
                    'id' => $group_row->id,
                    'name' => $group_row->name,
                ];
            } elseif ($group_row->type === 'static') {
                $static_groups[] = [
                    'id' => $group_row->id,
                    'name' => $group_row->name,
                ];
            }
        }

        $has_subproject_groups = false;
        $subproject_groups = [];
        $coverage_categories = [];
        $coverage_build_group_names = [];
        if ($has_subprojects) {
            // Detect if the subprojects are split up into groups.
            $groups = $this->project->GetSubProjectGroups();
            if (count($groups) > 0) {
                $has_subproject_groups = true;
                foreach ($groups as $group) {
                    // Store subproject groups in an array keyed by id.
                    $subproject_groups[$group->GetId()] = $group;

                    // Also store the low, medium, satisfactory threshold values
                    // for this group.
                    $group_name = $group->GetName();
                    $threshold = $group->GetCoverageThreshold();
                    $coverage_category = [];
                    $coverage_category['name'] = $group_name;
                    $coverage_category['position'] = $group->GetPosition();
                    $coverage_category['low'] = 0.7 * $threshold;
                    $coverage_category['medium'] = $threshold;
                    $coverage_category['satisfactory'] = 100;
                    $coverage_categories[] = $coverage_category;
                }
                // Also save a 'Total' category to summarize across groups.
                $coverage_category = [];
                $coverage_category['name'] = 'Total';
                $coverage_category['position'] = 0;
                $threshold = intval($this->project->CoverageThreshold);
                $coverage_category['low'] = 0.7 * $threshold;
                $coverage_category['medium'] = $threshold;
                $coverage_category['satisfactory'] = 100;
                $coverage_categories[] = $coverage_category;
            }
        }

        $threshold = $this->project->CoverageThreshold;
        if (!$has_subproject_groups) {
            $coverage_category = [];
            $coverage_category['name']  = 'coverage';
            $coverage_category['position']  = 1;
            $coverage_category['low'] = 0.7 * $threshold;
            $coverage_category['medium'] = $threshold;
            $coverage_category['satisfactory'] = 100;
            $coverage_categories[] = $coverage_category;
        }

        foreach ($build_groups as $build_group) {
            $coverage_build_group_names[] = $build_group['name'];
        }
        $coverage_build_group_names[] = 'Aggregate';

        // Initialize our storage data structures.
        //
        // overview_data holds most of the information about our builds.  It is a
        // multi-dimensional array with the following structure:
        //
        //   overview_data[day][build group][measurement]
        //
        // Coverage and dynamic analysis are a bit more complicated, so we
        // store their results separately.  Here is how their data structures
        // are defined:
        //   coverage_data[day][build group][coverage group] = percent_coverage
        //   dynamic_analysis_data[day][group][checker] = num_defects
        $overview_data = [];
        $coverage_data = [];
        $dynamic_analysis_data = [];

        for ($i = 0; $i < $date_range; $i++) {
            $overview_data[$i] = [];
            foreach ($build_groups as $build_group) {
                $build_group_name = $build_group['name'];

                // overview
                $overview_data[$i][$build_group_name] = [];

                // dynamic analysis
                $dynamic_analysis_data[$i][$build_group_name] = [];
            }

            // coverage
            foreach ($coverage_build_group_names as $build_group_name) {
                foreach ($coverage_categories as $coverage_category) {
                    $category_name = $coverage_category['name'];
                    $coverage_data[$i][$build_group_name][$category_name] = [];
                    $coverage_array =
                &$coverage_data[$i][$build_group_name][$category_name];
                    $coverage_array['loctested'] = 0;
                    $coverage_array['locuntested'] = 0;
                    $coverage_array['percent'] = 0;
                }
            }

            // static analysis
            foreach ($static_groups as $static_group) {
                $static_group_name = $static_group['name'];
                $overview_data[$i][$static_group_name] = [];
            }
        }

        // Get the beginning and end of our relevant date rate.
        $beginning_timestamp = $currentstarttime - (($date_range - 1) * 3600 * 24);
        $end_timestamp = $currentstarttime + 3600 * 24;
        $start_date = gmdate(FMT_DATETIME, $beginning_timestamp);
        $end_date = gmdate(FMT_DATETIME, $end_timestamp);

        // Perform a query to get info about all of our builds that fall within this
        // time range.
        $builds_array = DB::select('
                            SELECT
                                b.id,
                                b.type,
                                b.name,
                                b.builderrors AS build_errors,
                                b.buildwarnings AS build_warnings,
                                b.testfailed AS failing_tests,
                                b.configureerrors AS configure_errors,
                                b.configurewarnings AS configure_warnings,
                                b.starttime,
                                cs.loctested AS loctested,
                                cs.locuntested AS locuntested,
                                das.checker AS checker,
                                das.numdefects AS numdefects,
                                b2g.groupid AS groupid
                            FROM build AS b
                            LEFT JOIN build2group AS b2g ON (b2g.buildid=b.id)
                            LEFT JOIN coveragesummary AS cs ON (cs.buildid=b.id)
                            LEFT JOIN dynamicanalysissummary AS das ON (das.buildid=b.id)
                            WHERE
                                b.projectid = ?
                                AND b.starttime BETWEEN ? AND ?
                                AND b.parentid IN (-1, 0)
                        ', [$this->project->Id, $start_date, $end_date]);

        // If we have multiple coverage builds in a single day we will also
        // show the aggregate.
        $aggregate_tracker = [];
        $show_aggregate = false;

        // Keep track of the different types of dynamic analysis that are being
        // performed on our build groups of interest.
        $dynamic_analysis_types = [];

        // TODO: (williamjallen) Much of this can be done in SQL for efficiency and better readability
        foreach ($builds_array as $build_row) {
            // get what day this build is for.
            $day = self::get_day_index($build_row->starttime, $beginning_timestamp, $date_range);

            $static_name = self::get_static_group_name($build_row->groupid, $static_groups);
            // Special handling for static builds, as we don't need to record as
            // much data about them.
            if ($static_name) {
                foreach ($static_measurements as $measurement) {
                    if (!array_key_exists($measurement, $overview_data[$day][$static_name])) {
                        $overview_data[$day][$static_name][$measurement] = intval($build_row->{"build_$measurement"});
                    } else {
                        $overview_data[$day][$static_name][$measurement] += $build_row->{"build_$measurement"};
                    }
                    // Don't let our measurements be thrown off by CDash's tendency
                    // to store -1s in the database.
                    $overview_data[$day][$static_name][$measurement] = max(0, $overview_data[$day][$static_name][$measurement]);
                }
                continue;
            }

            if ($build_row->name === 'Aggregate Coverage') {
                $group_name = 'Aggregate';
            } else {
                $group_name = self::get_build_group_name($build_row->groupid, $build_groups);
            }

            // Skip this build if it's not from a group that is represented by
            // the overview dashboard.
            if (!$group_name) {
                continue;
            }

            if ($group_name !== 'Aggregate') {
                // From here on out, we're dealing with "build" (not static) groups.
                foreach ($build_measurements as $measurement) {
                    $clean_measurement = $clean_measurements[$measurement];
                    if (!array_key_exists($measurement, $overview_data[$day][$group_name])) {
                        $overview_data[$day][$group_name][$measurement] = intval($build_row->$clean_measurement);
                    } else {
                        $overview_data[$day][$group_name][$measurement] += $build_row->$clean_measurement;
                    }
                    // Don't let our measurements be thrown off by CDash's tendency
                    // to store -1s in the database.
                    $overview_data[$day][$group_name][$measurement] = max(0, $overview_data[$day][$group_name][$measurement]);
                }
            }

            // Check if coverage was performed for this build.
            if ((int) $build_row->loctested + (int) $build_row->locuntested > 0) {
                // Check for multiple nightly coverage builds in a single day.
                if ($group_name !== 'Aggregate' && $build_row->type === 'Nightly') {
                    if (array_key_exists($day, $aggregate_tracker)) {
                        $show_aggregate = true;
                    } else {
                        $aggregate_tracker[$day] = true;
                    }
                }

                if ($has_subproject_groups) {
                    // Add this coverage to the Total group.
                    $coverage_data[$day][$group_name]['Total']['loctested'] += $build_row->loctested;
                    $coverage_data[$day][$group_name]['Total']['locuntested'] += $build_row->locuntested;

                    // We need to query the children of this build to split up
                    // coverage into subproject groups.
                    $child_builds_array = DB::select('
                                              SELECT
                                                  b.id,
                                                  cs.loctested AS loctested,
                                                  cs.locuntested AS locuntested,
                                                  sp.id AS subprojectid,
                                                  sp.groupid AS subprojectgroupid
                                              FROM build AS b
                                              LEFT JOIN coveragesummary AS cs ON (cs.buildid=b.id)
                                              LEFT JOIN subproject2build AS sp2b ON (sp2b.buildid = b.id)
                                              LEFT JOIN subproject as sp ON (sp2b.subprojectid = sp.id)
                                              WHERE b.parentid=?
                                          ', [intval($build_row->id)]);
                    foreach ($child_builds_array as $child_build_row) {
                        $loctested = intval($child_build_row->loctested);
                        $locuntested = intval($child_build_row->locuntested);
                        if ($loctested + $locuntested === 0) {
                            continue;
                        }

                        $subproject_group_id = $child_build_row->subprojectgroupid;
                        if (is_null($subproject_group_id)) {
                            continue;
                        }

                        // Record coverage for this subproject group.
                        $subproject_group_name = $subproject_groups[$subproject_group_id]->GetName();
                        $coverage_data[$day][$group_name][$subproject_group_name]['loctested'] += $loctested;
                        $coverage_data[$day][$group_name][$subproject_group_name]['locuntested'] += $locuntested;
                    }
                } else {
                    $coverage_data[$day][$group_name]['coverage']['loctested'] += $build_row->loctested;
                    $coverage_data[$day][$group_name]['coverage']['locuntested'] += $build_row->locuntested;
                }
            }

            // Check if this build performed dynamic analysis.
            if (!empty($build_row->checker)) {
                // Add this checker to our list if this is the first time we've
                // encountered it.
                $checker = $build_row->checker;
                if (!in_array($checker, $dynamic_analysis_types)) {
                    $dynamic_analysis_types[] = $checker;
                }

                // Record the number of defects for this day / checker / build group.
                $dynamic_analysis_array = &$dynamic_analysis_data[$day][$group_name];
                if (!array_key_exists($checker, $dynamic_analysis_array)) {
                    $dynamic_analysis_array[$checker] = intval($build_row->numdefects);
                } else {
                    $dynamic_analysis_array[$checker] += intval($build_row->numdefects);
                }
            }
        }

        if (!$show_aggregate) {
            // Remove the aggregate from our coverage data.
            $key = array_search('Aggregate', $coverage_build_group_names);
            if ($key !== false) {
                unset($coverage_build_group_names[$key]);
            }
            for ($day = 0; $day < $date_range; $day++) {
                if (array_key_exists('Aggregate', $coverage_data[$day])) {
                    unset($coverage_data[$day]['Aggregate']);
                }
            }
        }

        // Compute coverage percentages here.
        for ($i = 0; $i < $date_range; $i++) {
            foreach ($coverage_data[$i] as &$build_group_data) {
                foreach ($build_group_data as &$coverage_array) {
                    $total_loc = (int) $coverage_array['loctested'] + (int) $coverage_array['locuntested'];
                    if ($total_loc === 0) {
                        continue;
                    }
                    $coverage_array['percent'] = round(($coverage_array['loctested'] / $total_loc) * 100, 2);
                }
            }
        }

        // Now that the data has been collected we can generate the XML.
        // Start with the general build groups.
        $groups = [];
        foreach ($build_groups as $build_group) {
            $groups[] = ['name' => $build_group['name']];
        }
        $response['groups'] = $groups;

        $measurements_response = [];
        foreach ($build_measurements as $measurement) {
            $clean_measurement = $clean_measurements[$measurement];
            $measurement_response = [];
            $measurement_response['name'] = $measurement;
            $measurement_response['name_clean'] = $clean_measurement;
            $measurement_response['sort'] = $sort[$measurement];

            $groups_response = [];
            foreach ($build_groups as $build_group) {
                $group_response = [];
                $group_response['name'] = $build_group['name'];
                $group_response['name_clean'] = self::sanitize_string($build_group['name']);
                $value = self::get_current_value($build_group['name'], $measurement, $date_range, $overview_data);
                $group_response['value'] = $value;

                $chart_data = self::get_chart_data($build_group['name'], $measurement, $date_range, $overview_data, $beginning_timestamp);
                $group_response['chart'] = $chart_data;
                $groups_response[] = $group_response;
            }
            $measurement_response['groups'] = $groups_response;
            $measurements_response[] = $measurement_response;
        }
        $response['measurements'] = $measurements_response;

        // coverage
        $coverages_response = [];
        $coverage_buildgroups = [];

        foreach ($coverage_categories as $coverage_category) {
            $category_name = $coverage_category['name'];
            $coverage_category_response = [];
            $coverage_category_response['name_clean'] = self::sanitize_string($category_name);
            $coverage_category_response['name'] = $category_name;
            $coverage_category_response['position'] = $coverage_category['position'];
            $coverage_category_response['groups'] = [];

            foreach ($coverage_build_group_names as $build_group_name) {
                // Skip groups that don't have any coverage.
                $found = false;
                for ($i = 0; $i < $date_range; $i++) {
                    $cov = &$coverage_data[$i][$build_group_name][$category_name];
                    $loc_total = $cov['loctested'] + $cov['locuntested'];
                    if ($loc_total > 0) {
                        $found = true;
                        break;
                    }
                }
                if (!$found) {
                    continue;
                }

                $coverage_response = [];

                $coverage_response['name'] = $build_group_name;
                if (!in_array($build_group_name, $coverage_buildgroups)) {
                    $coverage_buildgroups[] = $build_group_name;
                }
                $coverage_response['name_clean'] = self::sanitize_string($build_group_name);
                $coverage_response['low'] = $coverage_category['low'];
                $coverage_response['medium'] = $coverage_category['medium'];
                $coverage_response['satisfactory'] = $coverage_category['satisfactory'];

                [$current_value, $previous_value] =
                    self::get_recent_coverage_values($build_group_name, $category_name, $date_range, $coverage_data);
                $coverage_response['current'] = $current_value;
                $coverage_response['previous'] = $previous_value;

                $chart_data = self::get_coverage_chart_data($build_group_name, $category_name, $date_range, $coverage_data, $beginning_timestamp);
                $coverage_response['chart'] = $chart_data;
                $coverage_category_response['groups'][] = $coverage_response;
            }

            if (!empty($coverage_category_response['groups'])) {
                $coverages_response[] = $coverage_category_response;
            }
        }

        $response['coverages'] = $coverages_response;
        $response['coverage_buildgroups'] = $coverage_buildgroups;

        // dynamic analysis
        $dynamic_analyses_response = [];
        foreach ($dynamic_analysis_types as $checker) {
            $DA_response = [];
            $DA_response['name_clean'] = self::sanitize_string($checker);
            $DA_response['name'] = $checker;

            $groups_response = [];
            foreach ($build_groups as $build_group) {
                // Skip groups that don't have any data for this tool.
                $found = false;
                for ($i = 0; $i < $date_range; $i++) {
                    if (array_key_exists($checker,
                        $dynamic_analysis_data[$i][$build_group['name']])) {
                        $found = true;
                        break;
                    }
                }
                if (!$found) {
                    continue;
                }

                $group_response = [];
                $group_response['name'] = $build_group['name'];
                $group_response['name_clean'] = self::sanitize_string($build_group['name']);

                $chart_data = self::get_DA_chart_data($build_group['name'], $checker, $date_range, $dynamic_analysis_data, $beginning_timestamp);
                $group_response['chart'] = $chart_data;

                $value = self::get_current_DA_value($build_group['name'], $checker, $date_range, $dynamic_analysis_data);
                $group_response['value'] = $value;
                $groups_response[] = $group_response;
            }
            $DA_response['groups'] = $groups_response;
            $dynamic_analyses_response[] = $DA_response;
        }
        $response['dynamicanalyses'] = $dynamic_analyses_response;

        // static analysis
        $static_analyses_response = [];
        foreach ($static_groups as $static_group) {
            // Skip this group if no data was found for it.
            $found = false;
            for ($i = 0; $i < $date_range; $i++) {
                $static_array = &$overview_data[$i][$static_group['name']];
                foreach ($static_measurements as $measurement) {
                    if (array_key_exists($measurement, $static_array)) {
                        $found = true;
                        break;
                    }
                }
                if ($found) {
                    break;
                }
            }
            if (!$found) {
                continue;
            }

            $SA_response = [];
            $SA_response['group_name'] = $static_group['name'];
            $SA_response['group_name_clean'] = self::sanitize_string($static_group['name']);
            $measurements_response = [];
            foreach ($static_measurements as $measurement) {
                $measurement_response = [];
                $measurement_response['name'] = $measurement;
                $measurement_response['name_clean'] = self::sanitize_string($measurement);
                $measurement_response['sort'] = $sort["build $measurement"];
                $value = self::get_current_value($static_group['name'], $measurement, $date_range, $overview_data);
                $measurement_response['value'] = $value;

                $chart_data = self::get_chart_data($static_group['name'], $measurement, $date_range, $overview_data, $beginning_timestamp);
                $measurement_response['chart'] = $chart_data;
                $measurements_response[] = $measurement_response;
            }
            $SA_response['measurements'] = $measurements_response;
            $static_analyses_response[] = $SA_response;
        }
        $response['staticanalyses'] = $static_analyses_response;

        $pageTimer->end($response);

        return response()->json(cast_data_for_JSON($response));
    }

    /**
     * Replace all non-word characters with underscores.
     */
    private static function sanitize_string($input_string): string
    {
        return preg_replace('/\W/', '_', $input_string);
    }

    /**
     * Check if a given groupid belongs to one of our general overview groups.
     */
    private static function get_build_group_name($id, $build_groups): string|false
    {
        foreach ($build_groups as $build_group) {
            if ($build_group['id'] == $id) {
                return $build_group['name'];
            }
        }
        return false;
    }

    /**
     * Check if a given groupid belongs to one of our static analysis groups.
     */
    private static function get_static_group_name($id, $static_groups): string|false
    {
        foreach ($static_groups as $static_group) {
            if ($static_group['id'] == $id) {
                return $static_group['name'];
            }
        }
        return false;
    }

    /**
     * Convert a MySQL datetime into the number of days since the beginning of our time range.
     */
    private static function get_day_index($datetime, $beginning_timestamp, $date_range = 1)
    {
        $timestamp = strtotime($datetime) - $beginning_timestamp;
        $day = (int) ($timestamp / (3600 * 24));

        // Just to be safe, clamp the return value of this function to
        // (0, date_range - 1)
        if ($day < 0) {
            $day = 0;
        } elseif ($day > $date_range - 1) {
            $day = $date_range - 1;
        }

        return $day;
    }

    /**
     * Get most recent value for a given group & measurement.
     */
    private static function get_current_value($group_name, $measurement, $date_range, $overview_data)
    {
        for ($i = $date_range - 1; $i > -1; $i--) {
            if (array_key_exists($measurement, $overview_data[$i][$group_name])) {
                return $overview_data[$i][$group_name][$measurement];
            }
        }
        return false;
    }

    /**
     * Get most recent dynamic analysis value for a given group & checker.
     */
    private static function get_current_DA_value($group_name, $checker, $date_range, $dynamic_analysis_data)
    {
        for ($i = $date_range - 1; $i > -1; $i--) {
            if (array_key_exists($checker, $dynamic_analysis_data[$i][$group_name])) {
                return $dynamic_analysis_data[$i][$group_name][$checker];
            }
        }
        return 'N/A';
    }

    /**
     * Get a Javascript-compatible date representing the $ith date of our time range.
     */
    private static function get_date_from_index($i, $beginning_timestamp): string
    {
        $chart_beginning_timestamp = $beginning_timestamp + ($i * 3600 * 24);
        $chart_end_timestamp = $beginning_timestamp + (($i + 1) * 3600 * 24);
        // to be passed on to javascript chart renderers
        return gmdate('M d Y H:i:s', ($chart_end_timestamp + $chart_beginning_timestamp) / 2.0);
    }

    /**
     * Get line chart data for configure/build/test metrics.
     */
    private static function get_chart_data($group_name, $measurement, $date_range, $overview_data, $beginning_timestamp): string
    {
        $chart_data = [];

        for ($i = 0; $i < $date_range; $i++) {
            if (!array_key_exists($measurement, $overview_data[$i][$group_name])) {
                continue;
            }
            $chart_date = self::get_date_from_index($i, $beginning_timestamp);
            $chart_data[] = [$chart_date, $overview_data[$i][$group_name][$measurement]];
        }

        // JSON encode the chart data to make it easier to use on the client side.
        return json_encode($chart_data);
    }

    /**
     * Get line chart data for coverage
     */
    private static function get_coverage_chart_data($build_group_name, $coverage_category, $date_range, $coverage_data, $beginning_timestamp): string
    {
        $chart_data = [];

        for ($i = 0; $i < $date_range; $i++) {
            $coverage_array =
            &$coverage_data[$i][$build_group_name][$coverage_category];
            $total_loc = (int) $coverage_array['loctested'] + (int) $coverage_array['locuntested'];
            if ($total_loc === 0) {
                continue;
            }

            $chart_date = self::get_date_from_index($i, $beginning_timestamp);
            $chart_data[] = [$chart_date, $coverage_array['percent']];
        }
        return json_encode($chart_data);
    }

    /**
     * Get the current & previous coverage percentage value.
     * These are used by the bullet chart.
     */
    private static function get_recent_coverage_values($build_group_name, $coverage_category, $date_range, $coverage_data): array
    {
        $current_value_found = false;
        $current_value = 0;

        for ($i = $date_range - 1; $i > -1; $i--) {
            $coverage_array =
            &$coverage_data[$i][$build_group_name][$coverage_category];
            $total_loc = (int) $coverage_array['loctested'] + (int) $coverage_array['locuntested'];
            if ($total_loc === 0) {
                continue;
            }
            if (!$current_value_found) {
                $current_value = $coverage_array['percent'];
                $current_value_found = true;
            } else {
                $previous_value = $coverage_array['percent'];
                return [$current_value, $previous_value];
            }
        }

        // Reaching this line implies that we only found a single day's worth
        // of coverage for these groups.  In this case, we make previous & current
        // hold the same value.  We do this because nvd3's bullet chart implementation
        // does not support leaving the "marker" off of the chart.
        return [$current_value, $current_value];
    }

    /**
     * Get line chart data for dynamic analysis
     */
    private static function get_DA_chart_data($group_name, $checker, $date_range, $dynamic_analysis_data, $beginning_timestamp): string
    {
        $chart_data = [];

        for ($i = 0; $i < $date_range; $i++) {
            $dynamic_analysis_array = &$dynamic_analysis_data[$i][$group_name];
            if (!array_key_exists($checker, $dynamic_analysis_array)) {
                continue;
            }

            $chart_date = self::get_date_from_index($i, $beginning_timestamp);
            $chart_data[] = [$chart_date, $dynamic_analysis_data[$i][$group_name][$checker]];
        }
        return json_encode($chart_data);
    }

    public function manageOverview(): Response
    {
        return response()->angular_view('manageOverview');
    }

    public function apiManageOverview(): JsonResponse
    {
        $pageTimer = new PageTimer();
        $response = begin_JSON_response();
        $response['menutitle'] = 'CDash';
        $response['menusubtitle'] = 'Overview';
        $response['hidenav'] = 1;

        // Make sure we have an authenticated user.
        if (!Auth::check()) {
            $response['requirelogin'] = 1;
            return json_encode($response);
        }

        // Make sure a project was specified.
        $projectid = $_GET['projectid'] ?? null;
        if ($projectid === null) {
            $rest_json = file_get_contents('php://input');
            $_POST = json_decode($rest_json, true);
            $projectid = $_POST['projectid'];
        }
        if (!is_numeric($projectid)) {
            $response['error'] = "Please specify a project";
            return json_encode($response);
        }
        $projectid = (int) $projectid;

        $Project = new Project();
        $Project->Id = $projectid;

        if (!can_administrate_project($Project->Id)) {
            $response['error'] = "You don't have the permissions to access this page";
            return json_encode($response);
        }
        // Make sure the user has admin rights to this project.
        get_dashboard_JSON($Project->GetName(), null, $response);

        $db = Database::getInstance();

        // Check if we are saving an overview layout.
        if (isset($_POST['saveLayout'])) {
            $inputRows = json_decode($_POST['saveLayout'], true);
            if (!is_null($inputRows)) {
                // Remove any old overview layout from this project.
                DB::delete('DELETE FROM overview_components WHERE projectid=?', [intval($projectid)]);

                // Construct a query to insert the new layout.
                $query = 'INSERT INTO overview_components (projectid, buildgroupid, position, type) VALUES ';
                $params = [];
                foreach ($inputRows as $inputRow) {
                    $query .= '(?, ?, ?, ?),';
                    $params[] = intval($projectid);
                    $params[] = intval($inputRow['id']);
                    $params[] = intval($inputRow['position']);
                    $params[] = $inputRow['type'];
                }

                $query = rtrim($query, ',');
                $db->executePrepared($query, $params);
                add_last_sql_error('manageOverview::saveLayout::INSERT', $projectid);
            }

            // Since this is called by AJAX we don't need to generate the JSON
            // used to render this page.
            return;
        }

        // Otherwise generate the JSON used to render this page.
        // Get the groups that are already included in the overview.
        $query = $db->executePrepared('
                     SELECT
                         bg.id,
                         bg.name,
                         obg.type
                     FROM overview_components AS obg
                     LEFT JOIN buildgroup AS bg ON (obg.buildgroupid = bg.id)
                     WHERE obg.projectid = ?
                     ORDER BY obg.position
                 ', [intval($projectid)]);

        add_last_sql_error('manageOverview::overviewgroups', $projectid);

        $build_response = [];
        $static_response = [];
        foreach ($query as $overviewgroup_row) {
            $group_response = [];
            $group_response['id'] = intval($overviewgroup_row['id']);
            $group_response['name'] = $overviewgroup_row['name'];
            $type = $overviewgroup_row['type'];
            switch ($type) {
                case 'build':
                    $build_response[] = $group_response;
                    break;
                case 'static':
                    $static_response[] = $group_response;
                    break;
                default:
                    add_log("Unrecognized overview group type: '$type'",
                        __FILE__ . ':' . __LINE__ . ' - ' . __FUNCTION__,
                        LOG_WARNING);
                    break;
            }
        }
        $response['buildcolumns'] = $build_response;
        $response['staticrows'] = $static_response;

        // Get the buildgroups that aren't part of the overview yet.
        $buildgroup_rows = $db->executePrepared('
                               SELECT
                                   bg.id,
                                   bg.name
                               FROM buildgroup AS bg
                               LEFT JOIN overview_components AS oc ON (bg.id = oc.buildgroupid)
                               WHERE
                                   bg.projectid=?
                                   AND oc.buildgroupid IS NULL
                           ', [intval($projectid)]);
        add_last_sql_error('manageOverview::buildgroups', $projectid);

        $availablegroups_response = [];
        foreach ($buildgroup_rows as $buildgroup_row) {
            $buildgroup_response = [];
            $buildgroup_response['id'] = intval($buildgroup_row['id']);
            $buildgroup_response['name'] = $buildgroup_row['name'];
            $availablegroups_response[] = $buildgroup_response;
        }
        $response['availablegroups'] = $availablegroups_response;

        $pageTimer->end($response);
        echo json_encode(cast_data_for_JSON($response));
    }
}
