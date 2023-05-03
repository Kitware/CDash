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
use CDash\Model\Banner;
use CDash\Model\Build;
use CDash\Model\BuildInformation;
use CDash\Model\BuildGroup;
use CDash\Model\Project;
use CDash\Model\SubProject;

@set_time_limit(0);

@$projectname = $_GET['project'];
$projectname = htmlspecialchars(pdo_real_escape_string($projectname));
$projectid = get_project_id($projectname);
$Project = new Project();
$Project->Id = $projectid;
$Project->Fill();

// Generate the main dashboard JSON response.
require_once 'include/pdo.php';

$PDO = get_link_identifier()->getPdo();
$response = array();

$projectid = $Project->Id;

$db = Database::getInstance();
$controller = new IndexController($db, $Project);

$project_array = $db->executePreparedSingleRow('SELECT * FROM project WHERE id=?', [$projectid]);
if (!empty($project_array)) {
    $projectname = $project_array['name'];

    if (isset($project_array['testingdataurl']) && $project_array['testingdataurl'] != '') {
        $testingdataurl = make_cdash_url(htmlentities($project_array['testingdataurl']));
    }
} else {
    $response['error'] =
        "This project doesn't exist. Maybe the URL you are trying to access is wrong.";
    echo json_encode($response);
    return;
}

if (!can_access_project($project_array['id'])) {
    return;
}

$response = begin_JSON_response();
$response['title'] = "$projectname";
$response['showcalendar'] = 1;

$Banner = new Banner;
$Banner->SetProjectId(0);
$text = $Banner->GetText();
$banners = array();
if ($text !== false) {
    $banners[] = $text;
}

$Banner->SetProjectId($projectid);
$text = $Banner->GetText();
if ($text !== false) {
    $banners[] = $text;
}
$response['banners'] = $banners;

// If parentid is set we need to lookup the date for this build
// because it is not specified as a query string parameter.
if (isset($_GET['parentid'])) {
    $parentid = pdo_real_escape_numeric($_GET['parentid']);
    $parent_build = new Build();
    $parent_build->Id = $parentid;
    $parent_build->FillFromId($parent_build->Id);
    $controller->SetDate($parent_build->GetDate());

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
    $stmt = $PDO->prepare(
        'SELECT COUNT(buildid) FROM build2note WHERE buildid = ?');
    pdo_execute($stmt, [$parentid]);
    if ($stmt->fetchColumn() > 0) {
        $response['parenthasnotes'] = true;
    } else {
        $response['parenthasnotes'] = false;
    }

    // Check if the parent build has any uploaded files.
    $stmt = $PDO->prepare(
        'SELECT COUNT(buildid) FROM build2uploadfile WHERE buildid = ?');
    pdo_execute($stmt, [$parentid]);
    $response['uploadfilecount'] = $stmt->fetchColumn();
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
get_dashboard_JSON($projectname, $date, $response);
$response['displaylabels'] = $project_array['displaylabels'];
$response['showtesttime'] = $Project->ShowTestTime;

$page_id = 'index.php';

// Begin menu definition
$response['menu'] = array();
$beginning_UTCDate = $controller->getBeginDate();
$end_UTCDate = $controller->getEndDate();
if ($Project->GetNumberOfSubProjects($end_UTCDate) > 0) {
    $response['menu']['subprojects'] = 1;
}

$projectname_encoded = urlencode($projectname);

// Check if a SubProject parameter was specified.
$subproject_name = @$_GET['subproject'];
if ($subproject_name) {
    $SubProject = new SubProject();
    $subproject_name = htmlspecialchars(pdo_real_escape_string($subproject_name));
    $SubProject->SetName($subproject_name);
    $SubProject->SetProjectId($projectid);
    $subprojectid = $SubProject->GetId();

    if ($subprojectid) {
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
                $dependency_response['name'] = $DependProject->GetName();
                $dependency_response['name_encoded'] = urlencode($DependProject->GetName());
                $dependency_response['nbuilderror'] = $DependProject->GetNumberOfErrorBuilds($beginning_UTCDate, $end_UTCDate);
                $dependency_response['nbuildwarning'] = $DependProject->GetNumberOfWarningBuilds($beginning_UTCDate, $end_UTCDate);
                $dependency_response['nbuildpass'] = $DependProject->GetNumberOfPassingBuilds($beginning_UTCDate, $end_UTCDate);
                $dependency_response['nconfigureerror'] = $DependProject->GetNumberOfErrorConfigures($beginning_UTCDate, $end_UTCDate);
                $dependency_response['nconfigurewarning'] = $DependProject->GetNumberOfWarningConfigures($beginning_UTCDate, $end_UTCDate);
                $dependency_response['nconfigurepass'] = $DependProject->GetNumberOfPassingConfigures($beginning_UTCDate, $end_UTCDate);
                $dependency_response['ntestpass'] = $DependProject->GetNumberOfPassingTests($beginning_UTCDate, $end_UTCDate);
                $dependency_response['ntestfail'] = $DependProject->GetNumberOfFailingTests($beginning_UTCDate, $end_UTCDate);
                $dependency_response['ntestnotrun'] = $DependProject->GetNumberOfNotRunTests($beginning_UTCDate, $end_UTCDate);
                if (strlen($DependProject->GetLastSubmission()) == 0) {
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
        add_log("SubProject '$subproject_name' does not exist",
            __FILE__ . ':' . __LINE__ . ' - ' . __FUNCTION__,
            LOG_WARNING);
    }
}

// Check is a buildgroup parameter was specified.
if (isset($_GET['buildgroup'])) {
    $buildgroup_name = pdo_real_escape_string($_GET['buildgroup']);
    $controller->filterOnBuildGroup($buildgroup_name);
}

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

if (isset($testingdataurl)) {
    $response['testingdataurl'] = $testingdataurl;
}

// Get info about our buildgroups.
$buildgroups = BuildGroup::GetBuildGroups($projectid, $beginning_UTCDate);
foreach ($buildgroups as $buildgroup) {
    $controller->beginResponseForBuildgroup($buildgroup);
}
if (empty($buildgroups)) {
    $response['banners'][] = 'No builds found';
}

// Filters:
//
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
if (isset($_GET['parentid']) && $_GET['parentid'] > 0 &&
    $Project->GetNumberOfSubProjects($end_UTCDate) > 0
) {
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
        $coverageThreshold = $project_array['coveragethreshold'];
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

foreach ($build_rows as $build_array) {
    $build_response = $controller->generateBuildResponseFromRow($build_array);
    if ($build_response === false) {
        continue;
    }

    // Coverage
    //
    // Determine if this is a parent build with no actual coverage of its own.
    $linkToChildCoverage = false;
    if ($build_response['numchildren'] > 0) {
        $countChildrenResult = $db->executePreparedSingleRow('
                                   SELECT count(fileid) AS nfiles
                                   FROM coverage
                                   WHERE buildid=?
                               ', [intval($build_response['id'])]);
        if (intval($countChildrenResult['nfiles']) === 0) {
            $linkToChildCoverage = true;
        }
    }

    $coverageIsGrouped = false;

    $loctested = intval($build_array['loctested']);
    $locuntested = intval($build_array['locuntested']);
    if ($loctested + $locuntested > 0) {
        $coverage_response = array();
        $coverage_response['buildid'] = $build_array['id'];
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
        $coverage_response['locuntested'] = intval($locuntested);
        $coverage_response['loctested'] = intval($loctested);

        // Compute the diff
        if (!empty($build_array['loctesteddiff'])) {
            $loctesteddiff = $build_array['loctesteddiff'];
            $locuntesteddiff = $build_array['locuntesteddiff'];
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
        //
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
        $coverageThreshold = $project_array['coveragethreshold'];
        $response['thresholdgreen'] = $coverageThreshold;
        $response['thresholdyellow'] = $coverageThreshold * 0.7;
    }

    // Dynamic Analysis
    //
    if (!empty($build_array['checker'])) {
        // Determine if this is a parent build with no dynamic analysis
        // of its own.
        $linkToChildren = false;
        if ($build_response['numchildren'] > 0) {
            $countChildrenResult = $db->executePreparedSingleRow('
                                       SELECT count(id) AS num
                                       FROM dynamicanalysis
                                       WHERE buildid=?
                                   ', [intval($build_array['id'])]);
            if (intval($countChildrenResult['num']) === 0) {
                $linkToChildren = true;
            }
        }

        $DA_response = array();
        $DA_response['site'] = $build_array['sitename'];
        $DA_response['buildname'] = $build_array['name'];
        $DA_response['buildid'] = $build_array['id'];
        $DA_response['checker'] = $build_array['checker'];
        $DA_response['defectcount'] = $build_array['numdefects'];
        $starttimestamp = strtotime($build_array['starttime'] . ' UTC');
        $DA_response['datefull'] = $starttimestamp;
        if ($linkToChildren) {
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
        $expected_builds =
            $controller->addExpectedBuilds($i, $currentstarttime);
        if (is_array($expected_builds)) {
            $num_expected_builds = count($expected_builds);
            $controller->buildgroupsResponse[$i]['builds'] = array_merge(
                $controller->buildgroupsResponse[$i]['builds'], $expected_builds);
        }
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
    $response['all_buildgroups'][] =
        array('id' => $group['id'], 'name' => $group['name']);
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
if ($response['childview'] == 1) {
    // Report number of children.
    if (!empty($controller->buildgroupsResponse)) {
        $numchildren = count($controller->buildgroupsResponse[0]['builds']);
    } else {
        $row = $db->executePreparedSingleRow('
                   SELECT count(id) AS numchildren
                   FROM build
                   WHERE parentid=?
               ', [intval($parentid)]);
        $numchildren = intval($row['numchildren']);
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
if (!empty($coverage_groups)) {
    $response['coveragegroups'] = array();
    foreach ($coverage_groups as $groupid => $group) {
        $loctested = $group['loctested'];
        $locuntested = $group['locuntested'];
        if ($loctested == 0 && $locuntested == 0) {
            continue;
        }
        $percentage = round($loctested / ($loctested + $locuntested) * 100, 2);
        $group['percentage'] = $percentage;
        $group['id'] = $groupid;

        $response['coveragegroups'][] = $group;
    }
}

// We support an additional advanced column called 'Proc Time'.
// This is only shown if this project is setup to display
// an extra test measurement called 'Processors'.
$stmt = $PDO->prepare(
    "SELECT id FROM measurement
        WHERE projectid = ? and name = 'Processors'");
pdo_execute($stmt, [$projectid]);
if ($stmt->fetchColumn() !== false) {
    $response['showProcTime'] = true;
} else {
    $response['showProcTime'] = false;
}

$response['buildgroups'] = $controller->buildgroupsResponse;
$response['updatetype'] = $controller->updateType;
$response['enableTestTiming'] = $project_array['showtesttime'];

if (!empty($controller->siteResponse)) {
    $response = array_merge($response, $controller->siteResponse);
}

$controller->recordGenerationTime($response);
echo json_encode(cast_data_for_JSON($response));
