<?php

namespace App\Http\Controllers;

use App\Services\ProjectService;
use App\Utils\PageTimer;
use CDash\Database;
use CDash\Model\Build;
use CDash\Model\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

final class CoverageController extends AbstractBuildController
{
    public function compareCoverage(): View|RedirectResponse
    {
        // If the project name is not set we display the table of projects.
        if (!isset($_GET['project'])) {
            return redirect('projects');
        }

        return $this->angular_view('compareCoverage', 'Compare Coverage');
    }

    public function apiCompareCoverage(): JsonResponse
    {
        $pageTimer = new PageTimer();

        $this->setProjectByName(htmlspecialchars($_GET['project'] ?? ''));

        $date = isset($_GET['date']) ? htmlspecialchars($_GET['date']) : null;

        [$previousdate, $currentstarttime, $nextdate] = get_dates($date, $this->project->NightlyTime);

        $response = begin_JSON_response();
        $response['title'] = $this->project->Name . ' - Compare Coverage';
        $response['showcalendar'] = 1;
        get_dashboard_JSON($this->project->Name, $date, $response);

        $page_id = 'compareCoverage.php';

        // Menu definition
        $beginning_timestamp = $currentstarttime;
        $end_timestamp = $currentstarttime + 3600 * 24;
        $beginning_UTCDate = gmdate(FMT_DATETIME, $beginning_timestamp);
        $end_UTCDate = gmdate(FMT_DATETIME, $end_timestamp);

        // Menu
        $menu = [];
        $projectname_encoded = urlencode($this->project->Name);
        if ($date == '') {
            $back = "index.php?project=$projectname_encoded";
        } else {
            $back = "index.php?project=$projectname_encoded&date=$date";
        }
        $menu['back'] = $back;
        $menu['previous'] = "$page_id?project=$projectname_encoded&date=$previousdate";

        $today = date(FMT_DATE, time());
        $menu['current'] = "$page_id?project=$projectname_encoded&date=$today";

        if (has_next_date($date, $currentstarttime)) {
            $menu['next'] = "$page_id?project=$projectname_encoded&date=$nextdate";
        } else {
            $menu['next'] = false;
        }
        $response['menu'] = $menu;

        // Filters
        $filterdata = get_filterdata_from_request();
        unset($filterdata['xml']);
        $response['filterdata'] = $filterdata;
        $filter_sql = $filterdata['sql'];
        $response['filterurl'] = get_filterurl();

        // Get the list of builds we're interested in.
        $build_data = self::apiCompareCoverage_get_build_data(null, (int) $this->project->Id, $beginning_UTCDate, $end_UTCDate);
        $response['builds'] = [];
        $aggregate_build = [];
        foreach ($build_data as $build_array) {
            $build = [
                'name' => $build_array['name'],
                'key' => 'build' . $build_array['id'],
                'id' => $build_array['id'],
            ];
            if ($build['name'] === 'Aggregate Coverage') {
                $aggregate_build = $build;
            } else {
                $response['builds'][] = $build;
            }
        } // end looping through builds
        // Add 'Aggregate' build last
        $response['builds'][] = $aggregate_build;

        $coverages = []; // For un-grouped subprojects
        $coveragegroups = [];  // For grouped subprojects

        // Are there any subproject groups?
        $subproject_groups = [];
        if (ProjectService::getNumberOfSubProjects((int) $this->project->Id, $end_UTCDate) > 0) {
            $subproject_groups = ProjectService::getSubProjectGroups((int) $this->project->Id);
        }
        foreach ($subproject_groups as $group) {
            // Keep track of coverage info on a per-group basis.
            $groupId = $group->GetId();

            $coveragegroups[$groupId] = [];
            $coverageThreshold = $group->GetCoverageThreshold();
            $coveragegroups[$groupId]['thresholdgreen'] = $coverageThreshold;
            $coveragegroups[$groupId]['thresholdyellow'] = $coverageThreshold * 0.7;

            $coveragegroups[$groupId]['coverages'] = [];

            foreach ($response['builds'] as $build) {
                $coveragegroups[$groupId][$build['key']] = -1;
            }
            $coveragegroups[$groupId]['label'] = $group->GetName();
            $coveragegroups[$groupId]['position'] = $group->GetPosition();
        }
        if (count($subproject_groups) > 1) {
            // Add group for Total coverage.
            $coveragegroups[0] = [];
            $coverageThreshold = $this->project->CoverageThreshold;
            $coveragegroups[0]['thresholdgreen'] = $coverageThreshold;
            $coveragegroups[0]['thresholdyellow'] = $coverageThreshold * 0.7;
            foreach ($response['builds'] as $build) {
                $coveragegroups[0][$build['key']] = -1;
            }
            $coveragegroups[0]['label'] = 'Total';
            $coveragegroups[0]['position'] = 0;
        }

        // First, get the coverage data for the aggregate build.
        $build_data = self::apiCompareCoverage_get_build_data((int) $aggregate_build['id'], (int) $this->project->Id, $beginning_UTCDate, $end_UTCDate, $filter_sql);

        $coverage_response = self::apiCompareCoverage_get_coverage($build_data, $subproject_groups);

        // And make an entry in coverages for each possible subproject.

        // Grouped subprojects
        if (array_key_exists('coveragegroups', $coverage_response)) {
            foreach ($coverage_response['coveragegroups'] as $group) {
                $coveragegroups[$group['id']][$aggregate_build['key']] = $group['percentage'];
                $coveragegroups[$group['id']]['label'] = $group['label'];
                if ($group['id'] === 0) {
                    // 'Total' group is just a summary, does not contain coverages.
                    continue;
                }
                foreach ($group['coverages'] as $coverage) {
                    $subproject = self::apiCompareCoverage_create_subproject($coverage, $response['builds']);
                    $coveragegroups[$group['id']]['coverages'][] =
                        self::apiCompareCoverage_populate_subproject($subproject, $aggregate_build['key'], $coverage);
                }
            }
        }

        // Un-grouped subprojects
        if (array_key_exists('coverages', $coverage_response)) {
            foreach ($coverage_response['coverages'] as $coverage) {
                $subproject = self::apiCompareCoverage_create_subproject($coverage, $response['builds']);
                $coverages[] = self::apiCompareCoverage_populate_subproject($subproject, $aggregate_build['key'], $coverage);
            }
        }

        // Then loop through the other builds and fill in the subproject information
        foreach ($response['builds'] as $build_response) {
            $buildid = $build_response['id'];
            if ($buildid == null || $buildid == $aggregate_build['id']) {
                continue;
            }

            $build_data = self::apiCompareCoverage_get_build_data((int) $buildid, (int) $this->project->Id, $beginning_UTCDate, $end_UTCDate, $filter_sql);

            // Get the coverage data for each build.
            $coverage_response = self::apiCompareCoverage_get_coverage($build_data, $subproject_groups);

            // Grouped subprojects
            if (array_key_exists('coveragegroups', $coverage_response)) {
                foreach ($coverage_response['coveragegroups'] as $group) {
                    $coveragegroups[$group['id']]['build' . $buildid] = $group['percentage'];
                    $coveragegroups[$group['id']]['label'] = $group['label'];
                    if ($group['id'] === 0) {
                        // 'Total' group is just a summary, does not contain coverages.
                        continue;
                    }
                    foreach ($group['coverages'] as $coverage) {
                        // Find this subproject in the response
                        foreach ($coveragegroups[$group['id']]['coverages'] as $key => $subproject_response) {
                            if ($subproject_response['label'] == $coverage['label']) {
                                $coveragegroups[$group['id']]['coverages'][$key] =
                                    self::apiCompareCoverage_populate_subproject($coveragegroups[$group['id']]['coverages'][$key], 'build' . $buildid, $coverage);
                                break;
                            }
                        }
                    }
                }
            }

            // Un-grouped subprojects
            if (array_key_exists('coverages', $coverage_response)) {
                foreach ($coverage_response['coverages'] as $coverage) {
                    // Find this subproject in the response
                    foreach ($coverages as $key => $subproject_response) {
                        if ($subproject_response['label'] == $coverage['label']) {
                            $coverages[$key] = self::apiCompareCoverage_populate_subproject($subproject_response, 'build' . $buildid, $coverage);
                            break;
                        }
                    }
                }
            }
        } // end loop through builds

        if (!empty($subproject_groups)) {
            // At this point it is safe to remove any empty $coveragegroups from our response.
            $coveragegroups_response = array_filter($coveragegroups, fn ($group) => $group['label'] === 'Total' || !empty($group['coverages']));

            // Report coveragegroups as a list, not an associative array.
            $coveragegroups_response = array_values($coveragegroups_response);

            $response['coveragegroups'] = $coveragegroups_response;
        } else {
            $coverageThreshold = $this->project->CoverageThreshold;
            $response['thresholdgreen'] = $coverageThreshold;
            $response['thresholdyellow'] = $coverageThreshold * 0.7;

            // Report coverages as a list, not an associative array.
            $response['coverages'] = array_values($coverages);
        }

        $pageTimer->end($response);
        return response()->json(cast_data_for_JSON($response));
    }

    /**
     * @param array<string,mixed> $coverage
     * @param array<string,mixed> $builds
     *
     * @return array<string,mixed>
     */
    private static function apiCompareCoverage_create_subproject(array $coverage, array $builds): array
    {
        $subproject = [];
        $subproject['label'] = $coverage['label'];
        // Create a placeholder for each build
        foreach ($builds as $build) {
            $subproject[$build['key']] = -1;
        }
        return $subproject;
    }

    /**
     * @param array<string,mixed> $subproject
     * @param array<string,mixed> $coverage
     *
     * @return array<string,mixed>
     */
    private static function apiCompareCoverage_populate_subproject(array $subproject, string $key, array $coverage): array
    {
        $subproject[$key] = $coverage['percentage'];
        $subproject[$key . 'id'] = $coverage['buildid'];
        if (array_key_exists('percentagediff', $coverage)) {
            $percentagediff = $coverage['percentagediff'];
        } else {
            $percentagediff = null;
        }
        $subproject[$key . 'percentagediff'] = $percentagediff;
        return $subproject;
    }

    /**
     * @param array<string,mixed> $build_array
     */
    private static function apiCompareCoverage_get_build_label(int $buildid, array $build_array): string
    {
        // Figure out how many labels to report for this build.
        if (!array_key_exists('numlabels', $build_array) || (int) $build_array['numlabels'] === 0) {
            $num_labels = 0;
        } else {
            $num_labels = $build_array['numlabels'];
        }

        // Assign a label to this build based on how many labels it has.
        if ($num_labels == 0) {
            $build_label = '(none)';
        } elseif ($num_labels == 1) {
            // If exactly one label for this build, look it up here.
            $db = Database::getInstance();
            $label_result = $db->executePreparedSingleRow('
                                SELECT l.text
                                FROM label AS l
                                INNER JOIN label2build AS l2b ON (l.id=l2b.labelid)
                                INNER JOIN build AS b ON (l2b.buildid=b.id)
                                WHERE b.id=?
                            ', [(int) $buildid]);
            $build_label = $label_result['text'];
        } else {
            // More than one label, just report the number.
            $build_label = "($num_labels labels)";
        }

        return $build_label;
    }

    /**
     * @param array<string,mixed> $build_data
     * @param array<string,mixed> $subproject_groups
     *
     * @return array<string,mixed>
     */
    private static function apiCompareCoverage_get_coverage(array $build_data, array $subproject_groups): array
    {
        $response = [];
        $response['coveragegroups'] = [];

        // Summarize coverage by subproject groups.
        // This happens when we have subprojects and we're looking at the children
        // of a specific build.
        $coverage_groups = [];
        foreach ($subproject_groups as $group) {
            // Keep track of coverage info on a per-group basis.
            $groupId = $group->GetId();

            $coverage_groups[$groupId] = [];
            $coverage_groups[$groupId]['label'] = $group->GetName();
            $coverage_groups[$groupId]['loctested'] = 0;
            $coverage_groups[$groupId]['locuntested'] = 0;
            $coverage_groups[$groupId]['coverages'] = [];
        }
        if (count($subproject_groups) > 1) {
            $coverage_groups[0] = [
                'label' => 'Total',
                'loctested' => 0,
                'locuntested' => 0,
            ];
        }

        // Generate the JSON response from the rows of builds.
        foreach ($build_data as $build_array) {
            $buildid = (int) $build_array['id'];
            $coverageIsGrouped = false;
            $coverage_response = [];
            $coverage_response['buildid'] = $build_array['id'];

            $percent = round(compute_percentcoverage($build_array['loctested'], $build_array['locuntested']), 2);

            if (!empty($build_array['subprojectgroup'])) {
                $groupId = $build_array['subprojectgroup'];
                if (array_key_exists($groupId, $coverage_groups)) {
                    $coverageIsGrouped = true;
                    $coverage_groups[$groupId]['loctested'] += (int) $build_array['loctested'];
                    $coverage_groups[$groupId]['locuntested'] += (int) $build_array['locuntested'];
                    if (count($subproject_groups) > 1) {
                        $coverage_groups[0]['loctested'] += (int) $build_array['loctested'];
                        $coverage_groups[0]['locuntested'] += (int) $build_array['locuntested'];
                    }
                }
            }

            $coverage_response['percentage'] = $percent;
            $coverage_response['locuntested'] = (int) $build_array['locuntested'];
            $coverage_response['loctested'] = (int) $build_array['loctested'];

            // Compute the diff
            if (null !== $build_array['loctesteddiff'] || null !== $build_array['locuntesteddiff']) {
                $loctesteddiff = (int) $build_array['loctesteddiff'];
                $locuntesteddiff = (int) $build_array['locuntesteddiff'];
                $previouspercent =
                    round(($coverage_response['loctested'] - $loctesteddiff) /
                        ($coverage_response['loctested'] - $loctesteddiff +
                            $coverage_response['locuntested'] - $locuntesteddiff)
                        * 100, 2);
                $percentdiff = round($percent - $previouspercent, 2);
                $coverage_response['percentagediff'] = $percentdiff;
            }

            $coverage_response['label'] = self::apiCompareCoverage_get_build_label($buildid, $build_array);

            if ($coverageIsGrouped) {
                $coverage_groups[$groupId]['coverages'][] = $coverage_response;
            } else {
                $response['coverages'][] = $coverage_response;
            }
        } // end looping through builds

        // Generate coverage by group here.
        foreach ($coverage_groups as $groupid => $group) {
            $loctested = $group['loctested'];
            $locuntested = $group['locuntested'];
            if ($loctested === 0 && $locuntested === 0) {
                continue;
            }
            $percentage = round($loctested / ($loctested + $locuntested) * 100, 2);
            $group['percentage'] = $percentage;
            $group['id'] = $groupid;

            $response['coveragegroups'][] = $group;
        }

        return $response;
    }

    /**
     * @return array<string,mixed>
     */
    private static function apiCompareCoverage_get_build_data(?int $parentid, int $projectid, string $beginning_UTCDate, string $end_UTCDate, string $filter_sql = ''): array
    {
        $query_params = [];
        if ($parentid !== null) {
            // If we have a parentid, then we should only show children of that build.
            // Date becomes irrelevant in this case.
            $parent_clause = 'AND b.parentid = ?';
            $date_clause = '';
            $query_params[] = $parentid;
        } else {
            // Only show builds that are not children.
            $parent_clause = 'AND (b.parentid = -1 OR b.parentid = 0)';
            $date_clause = 'AND b.starttime < ? AND b.starttime >= ?';
            $query_params[] = $end_UTCDate;
            $query_params[] = $beginning_UTCDate;
        }
        $builds = Database::getInstance()->executePrepared("
                      SELECT
                          b.id,
                          b.parentid,
                          b.name,
                          sp.groupid AS subprojectgroup,
                          (SELECT count(buildid) FROM label2build WHERE buildid=b.id) AS numlabels,
                          cs.loctested,
                          cs.locuntested,
                          cs.loctesteddiff,
                          cs.locuntesteddiff
                      FROM build AS b
                      INNER JOIN build2group AS b2g ON (b2g.buildid=b.id)
                      INNER JOIN buildgroup AS g ON (g.id=b2g.groupid)
                      INNER JOIN coveragesummary AS cs ON (cs.buildid = b.id)
                      LEFT JOIN subproject AS sp ON (b.subprojectid = sp.id)
                      WHERE
                          b.projectid=?
                          AND g.type='Daily'
                          AND b.type='Nightly'
                          $parent_clause
                          $date_clause
                          $filter_sql
                  ", array_merge([$projectid], $query_params));

        return $builds === false ? [] : $builds;
    }
}
