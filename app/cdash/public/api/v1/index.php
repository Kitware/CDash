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

namespace CDash\Api\v1\Index;

require_once 'include/pdo.php';
require_once 'include/api_common.php';
require_once 'include/filterdataFunctions.php';

use CDash\Controller\Api\Index as IndexController;
use CDash\Database;
use App\Models\Banner;
use CDash\Model\Build;
use CDash\Model\BuildInformation;
use CDash\Model\BuildGroup;
use CDash\Model\Project;
use CDash\Model\SubProject;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

@set_time_limit(0);

@$projectname = $_GET['project'];
$projectname = htmlspecialchars(pdo_real_escape_string($projectname));
$Project = new Project();
$Project->Id = get_project_id($projectname);
$Project->Fill();

// Generate the main dashboard JSON response.

$controller = new IndexController(Database::getInstance(), $Project);

if (!can_access_project($Project->Id)) {
    return;
}

$response = begin_JSON_response();
$response['title'] = $Project->Name;
$response['showcalendar'] = 1;

$banners = [];
$global_banner = Banner::find(0);
if ($global_banner !== null && strlen($global_banner->text) > 0) {
    $banners[] = $global_banner->text;
}
$project_banner = Banner::find($Project->Id);
if ($project_banner !== null && strlen($project_banner->text) > 0) {
    $banners[] = $project_banner->text;
}
$response['banners'] = $banners;

// If parentid is set we need to lookup the date for this build
// because it is not specified as a query string parameter.
$parent_build = null;
$parentid = -1;
if (isset($_GET['parentid'])) {
    $parentid = (int) $_GET['parentid'];
    $parent_build = new Build();
    $parent_build->Id = $parentid;
    $parent_build->FillFromId($parent_build->Id);
    $controller->setDate($parent_build->GetDate());

    $response['parentid'] = $parentid;
    $controller->setParentId($parentid);

    $response['stamp'] = $parent_build->GetStamp();
    $response['starttime'] = $parent_build->StartTime;
    $response['type'] = $parent_build->Type;

    // Include data about this build from the buildinformation table.

    $buildinfo = new BuildInformation();
    $buildinfo->BuildId = $parentid;
    $buildinfo->Fill();
    $response['osname'] = $buildinfo->OSName;
    $response['osplatform'] = $buildinfo->OSPlatform;
    $response['osrelease'] = $buildinfo->OSRelease;
    $response['osversion'] = $buildinfo->OSVersion;
    $response['compilername'] = $buildinfo->CompilerName;
    $response['compilerversion'] = $buildinfo->CompilerVersion;

    // Check if the parent build has any notes.
    $stmt = DB::select('SELECT COUNT(buildid) AS c FROM build2note WHERE buildid = ?', [$parentid])[0];
    $response['parenthasnotes'] = $stmt->c > 0;

    // Check if the parent build has any uploaded files.
    $stmt = DB::select('SELECT COUNT(buildid) AS c FROM build2uploadfile WHERE buildid = ?', [$parentid])[0];
    $response['uploadfilecount'] = $stmt->c;
} else {
    $controller->determineDateRange($response);
    $response['parentid'] = -1;
}

if (isset($_GET['date']) && !isset($_GET['parentid']) && $controller->getCurrentStartTime() > time()) {
    $response['error'] = 'CDash cannot predict the future (yet)';
    echo json_encode($response);
    return;
}

// Main dashboard section
$currentstarttime = $controller->getCurrentStartTime();
$date = $controller->getDate();
get_dashboard_JSON($Project->Name, $date, $response);
$response['displaylabels'] = $Project->DisplayLabels;
$response['showtesttime'] = $Project->ShowTestTime;

$page_id = 'index.php';

// Begin menu definition
$response['menu'] = array();
$beginning_UTCDate = $controller->getBeginDate();
$end_UTCDate = $controller->getEndDate();
if ($Project->GetNumberOfSubProjects($end_UTCDate) > 0) {
    $response['menu']['subprojects'] = 1;
}

// Check if a SubProject parameter was specified.
if (isset($_GET['subproject'])) {
    $SubProject = new SubProject();
    $subproject_name = htmlspecialchars(pdo_real_escape_string($_GET['subproject']));
    $SubProject->SetName($subproject_name);
    $SubProject->SetProjectId($Project->Id);
    $subprojectid = $SubProject->GetId();

    if ($subprojectid !== 0) {
        $controller->setSubProjectId($subprojectid);
        $response['subprojectname'] = $subproject_name;

        $subproject_response = array();
        $subproject_response['name'] = $SubProject->GetName();

        $dependencies = $SubProject->GetDependencies();
        if ($dependencies) {
            $dependencies_response = array();
            foreach ($dependencies as $dependency) {
                $dependency_response = array();
                $DependProject = new SubProject();
                $DependProject->SetId($dependency);
                $result = $DependProject->CommonBuildQuery($beginning_UTCDate, $end_UTCDate, false);
                $dependency_response['name'] = $DependProject->GetName();
                $dependency_response['name_encoded'] = urlencode($DependProject->GetName());
                $dependency_response['nbuilderror'] = (int) $result['nbuilderrors'];
                $dependency_response['nbuildwarning'] = (int) $result['nbuildwarnings'];
                $dependency_response['nbuildpass'] = (int) $result['npassingbuilds'];
                $dependency_response['nconfigureerror'] = (int) $result['nconfigureerrors'];
                $dependency_response['nconfigurewarning'] = (int) $result['nconfigurewarnings'];
                $dependency_response['nconfigurepass'] = (int) $result['npassingconfigures'];
                $dependency_response['ntestpass'] = (int) $result['ntestspassed'];
                $dependency_response['ntestfail'] = (int) $result['ntestsfailed'];
                $dependency_response['ntestnotrun'] = (int) $result['ntestsnotrun'];
                if (strlen($DependProject->GetLastSubmission()) === 0) {
                    $dependency_response['lastsubmission'] = 'NA';
                } else {
                    $dependency_response['lastsubmission'] = $DependProject->GetLastSubmission();
                }
                $dependencies_response[] = $dependency_response;
            }
            $subproject_response['dependencies'] = $dependencies_response;
        }
        $response['subproject'] = $subproject_response;
    } else {
        Log::warning("SubProject '$subproject_name' does not exist");
    }
}

// Check is a buildgroup parameter was specified.
if (isset($_GET['buildgroup'])) {
    $buildgroup_name = pdo_real_escape_string($_GET['buildgroup']);
    $controller->filterOnBuildGroup($buildgroup_name);
}

$projectname_encoded = urlencode($Project->Name);

// Setup previous/current/next links.
if (isset($_GET['parentid'])) {
    // We are viewing the children of a single build.
    $page_id = 'indexchildren.php';
    $controller->childView = true;

    // When a parentid is specified, we should link to the next build,
    // not the next day.
    $previous_buildid = $parent_build->GetPreviousBuildId();
    $current_buildid = $parent_build->GetCurrentBuildId();
    $next_buildid = $parent_build->GetNextBuildId();

    $base_url = "index.php?project={$projectname_encoded}";
    if ($previous_buildid > 0) {
        $response['menu']['previous'] = "$base_url&parentid=$previous_buildid";
    } else {
        $response['menu']['previous'] = false;
    }

    $response['menu']['current'] = "$base_url&parentid=$current_buildid";

    if ($next_buildid > 0) {
        $response['menu']['next'] = "$base_url&parentid=$next_buildid";
    } else {
        $response['menu']['next'] = false;
    }
} else {
    // We are viewing builds from a given date.
    if (!has_next_date($date, $currentstarttime)) {
        $response['menu']['next'] = false;
    }
    if (isset($_GET['buildgroup'])) {
        $page_id = 'viewBuildGroup.php';
        $buildgroup = $_GET['buildgroup'];
        $base_url = "viewBuildGroup.php?project={$projectname_encoded}&buildgroup=$buildgroup";
    } else {
        $base_url = "index.php?project={$projectname_encoded}";
    }
    $response['menu']['current'] = "$base_url";
    $controller->determineNextPrevious($response, $base_url);
}
$response['childview'] = $controller->childView ? 1 : 0;

if (isset($Project->TestingDataUrl) && $Project->TestingDataUrl !== '') {
    $response['testingdataurl'] = make_cdash_url(htmlentities($Project->TestingDataUrl));
}

// Get info about our buildgroups.
$buildgroups = BuildGroup::GetBuildGroups($Project->Id, $beginning_UTCDate);
foreach ($buildgroups as $buildgroup) {
    $controller->beginResponseForBuildGroup($buildgroup);
}
if (count($buildgroups) === 0) {
    $response['banners'][] = 'No builds found';
}

// Filters:
$filterdata = get_filterdata_from_request($page_id);
unset($filterdata['xml']);
$controller->setFilterData($filterdata);
$filter_sql = $controller->getFilterSQL();
$response['filterdata'] = $controller->getFilterData();
$response['filterurl'] = get_filterurl();

$controller->checkForSubProjectFilters();
$response['sharelabelfilters'] = $controller->shareLabelFilters;
$response['testfilters'] = $controller->subProjectTestFilters;

$build_data = $controller->getDailyBuilds();
$build_data = array_merge($build_data, $controller->getDynamicBuilds());

// Check if we need to summarize coverage by subproject groups.
// This happens when we have subprojects and we're looking at the children
// of a specific build.
$coverage_groups = array();
$groupId = -1;
if (isset($_GET['parentid']) && (int) $_GET['parentid'] > 0 && $Project->GetNumberOfSubProjects($end_UTCDate) > 0) {
    $groups = $Project->GetSubProjectGroups();
    foreach ($groups as $group) {
        // Keep track of coverage info on a per-group basis.
        $groupId = $group->GetId();

        $coverage_groups[$groupId] = array();
        $coverageThreshold = $group->GetCoverageThreshold();
        $coverage_groups[$groupId]['thresholdgreen'] = $coverageThreshold;
        $coverage_groups[$groupId]['thresholdyellow'] = $coverageThreshold * 0.7;
        $coverage_groups[$groupId]['label'] = $group->GetName();
        $coverage_groups[$groupId]['loctested'] = 0;
        $coverage_groups[$groupId]['locuntested'] = 0;
        $coverage_groups[$groupId]['position'] = $group->GetPosition();
        $coverage_groups[$groupId]['coverages'] = array();
    }
    if (count($groups) > 1) {
        // Add a Total group too.
        $coverage_groups[0] = array();
        $coverageThreshold = (int) $Project->CoverageThreshold;
        $coverage_groups[0]['thresholdgreen'] = $coverageThreshold;
        $coverage_groups[0]['thresholdyellow'] = $coverageThreshold * 0.7;
        $coverage_groups[0]['label'] = 'Total';
        $coverage_groups[0]['loctested'] = 0;
        $coverage_groups[0]['locuntested'] = 0;
        $coverage_groups[0]['position'] = 0;
    }
}

// Fetch all the rows of builds into a php array.
// Compute additional fields for each row that we'll need to generate the xml.
//
$build_rows = [];
foreach ($build_data as $build_row) {
    $build_rows[] = $controller->populateBuildRow($build_row);
}

// Generate the JSON response from the rows of builds.
$response['coverages'] = array();
$response['dynamicanalyses'] = array();
$num_nightly_coverages_builds = 0;
$show_aggregate = false;
$response['comparecoverage'] = 0;

$build_responses = [];
$builds_with_child_coverage = [];
$builds_with_child_DA = [];
foreach ($build_rows as $build_array) {
    $build_response = $controller->generateBuildResponseFromRow($build_array);
    if ($build_response === false) {
        continue;
    }
    $build_responses[(int) $build_response['id']] = $build_response;
    if ((int) $build_response['numchildren'] > 0) {
        $builds_with_child_coverage[] = (int) $build_response['id'];
    }
    if (!empty($build_array['checker']) && (int) $build_response['numchildren'] > 0) {
        $builds_with_child_DA[] = (int) $build_response['id'];
    }
}

// COVERAGE
// A list of buildids which should link to a child coverage
$child_coverage_result = [];
if (count($builds_with_child_coverage) > 0) {
    $builds_with_child_coverage_prepared_array = Database::getInstance()->createPreparedArray(count($builds_with_child_coverage));
    $child_coverage_result = DB::select("
                                 SELECT DISTINCT buildid
                                 FROM coverage
                                 WHERE buildid IN $builds_with_child_coverage_prepared_array
                                 GROUP BY buildid
                                 HAVING COUNT(fileid) = 0
                             ", $builds_with_child_coverage);
}
$linkToChildCoverageArray = [];
foreach ($child_coverage_result as $row) {
    $linkToChildCoverageArray[] = $row->buildid;
}

// DYNAMIC ANALYSIS
$child_DA_result = [];
if (count($builds_with_child_DA) > 0) {
    $builds_with_child_DA_prepared_array = Database::getInstance()->createPreparedArray(count($builds_with_child_DA));
    $child_DA_result = DB::select("
                           SELECT buildid
                           FROM dynamicanalysis
                           WHERE buildid in $builds_with_child_DA_prepared_array
                           GROUP BY buildid
                           HAVING COUNT(id) = 0
                       ", $builds_with_child_DA);
}
$linkToChildrenDAArray = [];
foreach ($child_DA_result as $row) {
    $linkToChildrenDAArray[] = $row->buildid;
}

foreach ($build_rows as $build_array) {
    if (!array_key_exists((int) $build_array['id'], $build_responses)) {
        continue;
    }
    $build_response = $build_responses[(int) $build_array['id']];

    // Coverage
    //
    // Determine if this is a parent build with no actual coverage of its own.
    $linkToChildCoverage = in_array((int) $build_array['id'], $linkToChildCoverageArray, true);

    $coverageIsGrouped = false;

    $loctested = (int) $build_array['loctested'];
    $locuntested = (int) $build_array['locuntested'];
    if ($loctested + $locuntested > 0) {
        $coverage_response = array();
        $coverage_response['buildid'] = (int) $build_array['id'];
        if ($linkToChildCoverage) {
            $coverage_response['childlink'] = $build_response['multiplebuildshyperlink'] . '##Coverage';
        }

        if ($build_array['type'] === 'Nightly' && $build_array['name'] !== 'Aggregate Coverage') {
            $num_nightly_coverages_builds++;
            if ($num_nightly_coverages_builds > 1) {
                $show_aggregate = true;
                if ($linkToChildCoverage) {
                    $response['comparecoverage'] = 1;
                }
            }
        }

        $percent = round(compute_percentcoverage($loctested, $locuntested), 2);

        if ($build_array['subprojectgroup']) {
            $groupId = $build_array['subprojectgroup'];
            if (array_key_exists($groupId, $coverage_groups)) {
                $coverageIsGrouped = true;
                $coverageThreshold =
                    $coverage_groups[$groupId]['thresholdgreen'];
                $coverage_groups[$groupId]['loctested'] += $loctested;
                $coverage_groups[$groupId]['locuntested'] += $locuntested;
                if (count($coverage_groups) > 1) {
                    // Add to Total.
                    $coverage_groups[0]['loctested'] += $loctested;
                    $coverage_groups[0]['locuntested'] += $locuntested;
                }
            }
        }

        $coverage_response['percentage'] = $percent;
        $coverage_response['locuntested'] = $locuntested;
        $coverage_response['loctested'] = $loctested;

        // Compute the diff
        if ((int) $build_array['loctesteddiff'] > 0) {
            $loctesteddiff = (int) $build_array['loctesteddiff'];
            $locuntesteddiff = (int) $build_array['locuntesteddiff'];
            @$previouspercent =
                round(($loctested - $loctesteddiff) /
                    ($loctested - $loctesteddiff +
                        $locuntested - $locuntesteddiff)
                    * 100, 2);
            $percentdiff = round($percent - $previouspercent, 2);
            $coverage_response['percentagediff'] = $percentdiff;
            $coverage_response['locuntesteddiff'] = $locuntesteddiff;
            $coverage_response['loctesteddiff'] = $loctesteddiff;
        }

        $starttimestamp = strtotime($build_array['starttime'] . ' UTC');
        $coverage_response['datefull'] = $starttimestamp;

        // If the data is more than 24h old then we switch from an elapsed to a normal representation
        if (time() - $starttimestamp < 86400) {
            $coverage_response['date'] = date(FMT_DATETIMEDISPLAY, $starttimestamp);
            $coverage_response['dateelapsed'] = time_difference(time() - $starttimestamp, false, 'ago');
        } else {
            $coverage_response['dateelapsed'] = date(FMT_DATETIMEDISPLAY, $starttimestamp);
            $coverage_response['date'] = time_difference(time() - $starttimestamp, false, 'ago');
        }

        // Are there labels for this build?
        $coverage_response['label'] = $build_response['label'];

        if ($coverageIsGrouped) {
            $coverage_groups[$groupId]['coverages'][] = $coverage_response;
        } else {
            $coverage_response['site'] = $build_array['sitename'];
            $coverage_response['buildname'] = $build_array['name'];
            $response['coverages'][] = $coverage_response;
        }
    }
    if (!$coverageIsGrouped) {
        $coverageThreshold = $Project->CoverageThreshold;
        $response['thresholdgreen'] = $coverageThreshold;
        $response['thresholdyellow'] = $coverageThreshold * 0.7;
    }

    // Dynamic Analysis
    if (!empty($build_array['checker'])) {
        // Determine if this is a parent build with no dynamic analysis
        // of its own.
        $linkToChildrenDA = in_array((int) $build_array['id'], $linkToChildrenDAArray, true);

        $DA_response = array();
        $DA_response['site'] = $build_array['sitename'];
        $DA_response['buildname'] = $build_array['name'];
        $DA_response['buildid'] = (int) $build_array['id'];
        $DA_response['checker'] = $build_array['checker'];
        $DA_response['defectcount'] = (int) $build_array['numdefects'];
        $starttimestamp = strtotime($build_array['starttime'] . ' UTC');
        $DA_response['datefull'] = $starttimestamp;
        if ($linkToChildrenDA) {
            $DA_response['childlink'] = $build_response['multiplebuildshyperlink'] . '##DynamicAnalysis';
        }

        // If the data is more than 24h old then we switch from an elapsed to a normal representation
        if (time() - $starttimestamp < 86400) {
            $DA_response['date'] = date(FMT_DATETIMEDISPLAY, $starttimestamp);
            $DA_response['dateelapsed'] =
                time_difference(time() - $starttimestamp, false, 'ago');
        } else {
            $DA_response['dateelapsed'] = date(FMT_DATETIMEDISPLAY, $starttimestamp);
            $DA_response['date'] =
                time_difference(time() - $starttimestamp, false, 'ago');
        }

        // Are there labels for this build?
        //
        $DA_response['label'] = $build_response['label'];

        $response['dynamicanalyses'][] = $DA_response;
    }
}

// Put some finishing touches on our buildgroups now that we're done
// iterating over all the builds.
for ($i = 0; $i < count($controller->buildgroupsResponse); $i++) {
    $controller->buildgroupsResponse[$i]['testduration'] = time_difference(
        $controller->buildgroupsResponse[$i]['testduration'], true);

    $num_expected_builds = 0;
    if (!$filter_sql) {
        $expected_builds = $controller->addExpectedBuilds($i, $currentstarttime);

        $num_expected_builds = count($expected_builds);
        $controller->buildgroupsResponse[$i]['builds'] = array_merge(
            $controller->buildgroupsResponse[$i]['builds'],
            $expected_builds
        );
    }
    // Show how many builds this group has.
    $num_builds = count($controller->buildgroupsResponse[$i]['builds']);
    if ($num_expected_builds > 0) {
        $num_actual_builds = $num_builds - $num_expected_builds;
        $num_builds_label = "$num_actual_builds of $num_builds builds";
    } else {
        if ($num_builds === 1) {
            $num_builds_label = '1 build';
        } else {
            $num_builds_label = "$num_builds builds";
        }
    }
    $controller->buildgroupsResponse[$i]['numbuildslabel'] = $num_builds_label;
}

// Create a separate "all buildgroups" section of our response.
// This is used to allow project admins to move builds between groups.
$response['all_buildgroups'] = array();
foreach ($controller->buildgroupsResponse as $group) {
    $response['all_buildgroups'][] = [
        'id' => $group['id'],
        'name' => $group['name']
    ];
}

$controller->buildgroupsResponse =
    array_filter($controller->buildgroupsResponse, function ($group) {
        return !empty($group['builds']);
    });

// Report buildgroups as a list, not an associative array.
// Otherwise any missing buildgroups will cause our view to
// not honor the order specified by the project admins.
$controller->buildgroupsResponse = array_values($controller->buildgroupsResponse);

// Remove Aggregate Coverage if it should not be displayed.
if (!$show_aggregate) {
    for ($i = 0; $i < count($response['coverages']); $i++) {
        if ($response['coverages'][$i]['buildname'] === 'Aggregate Coverage') {
            unset($response['coverages'][$i]);
        }
    }
    $response['coverages'] = array_values($response['coverages']);
}

$response['showorder'] = false;
$response['showstarttime'] = true;
if ($response['childview'] === 1) {
    // Report number of children.
    if (count($controller->buildgroupsResponse) > 0) {
        $numchildren = count($controller->buildgroupsResponse[0]['builds']);
    } else {
        $row = DB::select('
                   SELECT count(id) AS numchildren
                   FROM build
                   WHERE parentid=?
               ', [$parentid])[0];
        $numchildren = (int) $row->numchildren;
    }
    $response['numchildren'] = $numchildren;

    // If all our children share the same start time, then this was an
    // "all at once" subproject build.
    // In that case, tell our view to display the "Order" column instead of
    // the "Start Time" column.
    if (count($controller->buildStartTimes) === 1) {
        $response['showorder'] = true;
        $response['showstarttime'] = false;

        $controller->normalizeSubProjectOrder();

        // Update duration, configure duration, build duration, and
        // test duration do not vary among children builds in this case.
        // Find the single value (if any) for each and report it at the top
        // of the page.
        $buildgroup_response = $controller->buildgroupsResponse[0];
        $need_update = $buildgroup_response['hasupdatedata'];
        $need_configure = $buildgroup_response['hasconfiguredata'];
        $need_build = $buildgroup_response['hascompilationdata'];
        $need_test = $buildgroup_response['hastestdata'];
        $response['updateduration'] = false;
        $response['configureduration'] = false;
        $response['buildduration'] = false;
        $response['testduration'] = false;
        foreach ($buildgroup_response['builds'] as $build_response) {
            if ($build_response['hasupdate']) {
                $response['updateduration'] =
                    $build_response['update']['time'];
                $need_update = false;
            }
            if ($build_response['hasconfigure']) {
                $response['configureduration'] =
                    $build_response['configure']['time'];
                $need_configure = false;
            }
            if ($build_response['hascompilation']) {
                $response['buildduration'] =
                    $build_response['compilation']['time'];
                $need_build = false;
            }
            if ($build_response['hastest']) {
                $response['testduration'] =
                    $build_response['test']['time'];
                $need_test = false;
            }
            // Break out of the loop once we have all the data we need.
            if (!$need_update && !$need_configure && !$need_build &&
                !$need_test) {
                break;
            }
        }
    }
}

// Generate coverage by group here.
if (count($coverage_groups) > 0) {
    $response['coveragegroups'] = array();
    foreach ($coverage_groups as $groupid => $group) {
        $loctested = $group['loctested'];
        $locuntested = $group['locuntested'];
        if ($loctested === 0 && $locuntested === 0) {
            continue;
        }
        $group['percentage'] = round($loctested / ($loctested + $locuntested) * 100, 2);
        $group['id'] = (int) $groupid;

        $response['coveragegroups'][] = $group;
    }
}

// We support an additional advanced column called 'Proc Time'.
// This is only shown if this project is setup to display
// an extra test measurement called 'Processors'.
$stmt = DB::select("
            SELECT count(*) AS c
            FROM measurement
            WHERE
                projectid = ?
                AND name = 'Processors'
        ", [$Project->Id])[0];
$response['showProcTime'] = $stmt->c > 0;

$response['buildgroups'] = $controller->buildgroupsResponse;
$response['updatetype'] = $controller->updateType;
$response['enableTestTiming'] = $Project->ShowTestTime;

if (count($controller->siteResponse) > 0) {
    $response = array_merge($response, $controller->siteResponse);
}

$controller->recordGenerationTime($response);
echo json_encode(cast_data_for_JSON($response));
