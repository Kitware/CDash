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

// Redirect to the previous version of api/index.php if it seems like
// that's what the user wants.
if (isset($_GET['method'])) {
    require __DIR__ . '/index_old.php';
    exit(0);
}

include dirname(dirname(dirname(__DIR__))) . '/config/config.php';
require_once 'include/pdo.php';
require_once 'include/api_common.php';
include 'include/version.php';
require_once 'models/project.php';
require_once 'models/buildfailure.php';
require_once 'include/filterdataFunctions.php';
require_once 'include/index_functions.php';

@set_time_limit(0);

// Check if we can connect to the database.
$db = pdo_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN", "$CDASH_DB_PASS");
if (!$db ||
    pdo_select_db("$CDASH_DB_NAME", $db) === false ||
    pdo_query('SELECT id FROM ' . qid('user') . ' LIMIT 1', $db) === false
) {
    if ($CDASH_PRODUCTION_MODE) {
        $response = array();
        $response['error'] = 'CDash cannot connect to the database.';
        echo json_encode($response);
        return;
    } else {
        // redirect to the install.php script
        $response = array();
        $response['redirect'] = get_server_URI() . '/install.php';
        echo json_encode($response);
        return;
    }
    return;
}

@$projectname = $_GET['project'];
$projectname = htmlspecialchars(pdo_real_escape_string($projectname));
$projectid = get_project_id($projectname);
$Project = new Project();
$Project->Id = $projectid;
$Project->Fill();

@$date = $_GET['date'];
if ($date != null) {
    $date = htmlspecialchars(pdo_real_escape_string($date));
}

echo_main_dashboard_JSON($Project, $date);

// Generate the main dashboard JSON response.
function echo_main_dashboard_JSON($project_instance, $date)
{
    global $CDASH_DB_HOST, $CDASH_DB_LOGIN, $CDASH_DB_NAME, $CDASH_DB_PASS,
           $CDASH_DB_TYPE, $CDASH_ENABLE_FEED, $CDASH_USE_LOCAL_DIRECTORY;

    $start = microtime_float();
    require_once 'include/pdo.php';
    require_once 'models/banner.php';
    require_once 'models/build.php';
    require_once 'models/subproject.php';

    $PDO = get_link_identifier()->getPdo();
    $response = array();

    $db = pdo_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN", "$CDASH_DB_PASS");
    if (!$db) {
        $response['error'] = 'Error connecting to CDash database server';
        echo json_encode($response);
        return;
    }
    if (!pdo_select_db("$CDASH_DB_NAME", $db)) {
        $response['error'] = 'Error selecting CDash database';
        echo json_encode($response);
        return;
    }

    $projectid = $project_instance->Id;

    $project = pdo_query("SELECT * FROM project WHERE id='$projectid'");
    if (pdo_num_rows($project) > 0) {
        $project_array = pdo_fetch_array($project);
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
    $response['title'] = "CDash - $projectname";
    $response['feed'] = $CDASH_ENABLE_FEED;
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
    $site_response = array();

    // If parentid is set we need to lookup the date for this build
    // because it is not specified as a query string parameter.
    if (isset($_GET['parentid'])) {
        $parentid = pdo_real_escape_numeric($_GET['parentid']);
        $parent_build = new Build();
        $parent_build->Id = $parentid;
        $date = $parent_build->GetDate();
        $response['parentid'] = $parentid;

        // Check if the parent build has any notes.
        $stmt = $PDO->prepare(
            'SELECT COUNT(buildid) FROM build2note WHERE buildid = ?');
        pdo_execute($stmt, [$parentid]);
        if ($stmt->fetchColumn() > 0) {
            $response['parenthasnotes'] = true;
        } else {
            $response['parenthasnotes'] = false;
        }
    } else {
        $response['parentid'] = -1;
    }

    list($previousdate, $currentstarttime, $nextdate) = get_dates($date, $project_array['nightlytime']);

    // Main dashboard section
    get_dashboard_JSON($projectname, $date, $response);
    $response['displaylabels'] = $project_array['displaylabels'];

    $page_id = 'index.php';
    $response['childview'] = 0;

    if ($CDASH_USE_LOCAL_DIRECTORY && file_exists('local/models/proProject.php')) {
        include_once 'local/models/proProject.php';
        $pro = new proProject;
        $pro->ProjectId = $projectid;
        $response['proedition'] = $pro->GetEdition(1);
    }

    if ($currentstarttime > time() && !isset($_GET['parentid'])) {
        $response['error'] = 'CDash cannot predict the future (yet)';
        echo json_encode($response);
        return;
    }

    // Menu definition
    $response['menu'] = array();
    $beginning_timestamp = $currentstarttime;
    $end_timestamp = $currentstarttime + 3600 * 24;
    $beginning_UTCDate = gmdate(FMT_DATETIME, $beginning_timestamp);
    $end_UTCDate = gmdate(FMT_DATETIME, $end_timestamp);
    if ($project_instance->GetNumberOfSubProjects($end_UTCDate) > 0) {
        $response['menu']['subprojects'] = 1;
    }

    if (isset($_GET['parentid'])) {
        $page_id = 'indexchildren.php';
        $response['childview'] = 1;

        // When a parentid is specified, we should link to the next build,
        // not the next day.
        $previous_buildid = $parent_build->GetPreviousBuildId();
        $current_buildid = $parent_build->GetCurrentBuildId();
        $next_buildid = $parent_build->GetNextBuildId();

        $base_url = 'index.php?project=' . urlencode($projectname);
        if ($previous_buildid > 0) {
            $response['menu']['previous'] = "$base_url&parentid=$previous_buildid";
        } else {
            $response['menu']['noprevious'] = '1';
        }

        $response['menu']['current'] = "$base_url&parentid=$current_buildid";

        if ($next_buildid > 0) {
            $response['menu']['next'] = "$base_url&parentid=$next_buildid";
        } else {
            $response['menu']['nonext'] = '1';
        }
    } elseif (!has_next_date($date, $currentstarttime)) {
        $response['menu']['nonext'] = 1;
    }

    // Check if a SubProject parameter was specified.
    $subproject_name = @$_GET['subproject'];
    $subprojectid = false;
    if ($subproject_name) {
        $SubProject = new SubProject();
        $subproject_name = htmlspecialchars(pdo_real_escape_string($subproject_name));
        $SubProject->SetName($subproject_name);
        $SubProject->SetProjectId($projectid);
        $subprojectid = $SubProject->GetId();

        if ($subprojectid) {
            // Add an extra URL argument for the menu
            $response['extraurl'] = '&subproject=' . urlencode($subproject_name);
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

    if (isset($testingdataurl)) {
        $response['testingdataurl'] = $testingdataurl;
    }

    // updates
    $updates_response = array();

    $gmdate = gmdate(FMT_DATE, $currentstarttime);
    $updates_response['url'] = 'viewChanges.php?project=' . urlencode($projectname) . '&amp;date=' . $gmdate;

    $dailyupdate = pdo_query("SELECT count(ds.dailyupdateid),count(distinct ds.author)
            FROM dailyupdate AS d LEFT JOIN dailyupdatefile AS ds ON (ds.dailyupdateid = d.id)
            WHERE d.date='$gmdate' and d.projectid='$projectid' GROUP BY ds.dailyupdateid");

    if (pdo_num_rows($dailyupdate) > 0) {
        $dailupdate_array = pdo_fetch_array($dailyupdate);
        $updates_response['nchanges'] = $dailupdate_array[0];
        $updates_response['nauthors'] = $dailupdate_array[1];
    } else {
        $updates_response['nchanges'] = -1;
    }
    $updates_response['timestamp'] = date('l, F d Y - H:i T', $currentstarttime);
    $response['updates'] = $updates_response;

    // This array is used to track if expected builds are found or not.
    $received_builds = array();

    // Get info about our buildgroups.
    $buildgroups_response = array();
    $buildgroup_result = pdo_query(
        "SELECT bg.id, bg.name, bgp.position FROM buildgroup AS bg
            LEFT JOIN buildgroupposition AS bgp ON (bgp.buildgroupid=bg.id)
            WHERE bg.projectid=$projectid AND bg.starttime < '$beginning_UTCDate' AND
            (bg.endtime > '$beginning_UTCDate' OR
             bg.endtime='1980-01-01 00:00:00')");
    while ($buildgroup_array = pdo_fetch_array($buildgroup_result)) {
        $buildgroup_response = array();
        $groupname = $buildgroup_array['name'];

        $buildgroup_response['id'] = $buildgroup_array['id'];
        $buildgroup_response['name'] = $groupname;
        $buildgroup_response['linkname'] = str_replace(' ', '_', $groupname);
        $buildgroup_response['position'] = $buildgroup_array['position'];

        $buildgroup_response['numupdatedfiles'] = 0;
        $buildgroup_response['numupdateerror'] = 0;
        $buildgroup_response['numupdatewarning'] = 0;
        $buildgroup_response['updateduration'] = 0;
        $buildgroup_response['configureduration'] = 0;
        $buildgroup_response['numconfigureerror'] = 0;
        $buildgroup_response['numconfigurewarning'] = 0;
        $buildgroup_response['numbuilderror'] = 0;
        $buildgroup_response['numbuildwarning'] = 0;
        $buildgroup_response['numtestnotrun'] = 0;
        $buildgroup_response['numtestfail'] = 0;
        $buildgroup_response['numtestpass'] = 0;
        $buildgroup_response['testduration'] = 0;
        $buildgroup_response['hasupdatedata'] = false;
        $buildgroup_response['hasconfiguredata'] = false;
        $buildgroup_response['hascompilationdata'] = false;
        $buildgroup_response['hastestdata'] = false;
        $buildgroup_response['hasnormalbuilds'] = false;
        $buildgroup_response['hasparentbuilds'] = false;

        $buildgroup_response['builds'] = array();
        $received_builds[$groupname] = array();

        $buildgroups_response[] = $buildgroup_response;
    }

    // Filters:
    //
    $filterdata = get_filterdata_from_request($page_id);
    $filter_sql = $filterdata['sql'];
    $limit_sql = '';
    if ($filterdata['limit'] > 0) {
        $limit_sql = ' LIMIT ' . $filterdata['limit'];
    }
    unset($filterdata['xml']);
    $response['filterdata'] = $filterdata;
    $response['filterurl'] = get_filterurl();

    // Check if we should be excluding some SubProjects from our
    // build results.
    $include_subprojects = false;
    $exclude_subprojects = false;
    $included_subprojects = array();
    $excluded_subprojects = array();
    $selected_subprojects = '';
    $num_selected_subprojects = 0;
    $filter_on_labels = false;
    $share_label_filters = false;
    foreach ($filterdata['filters'] as $filter) {
        if ($filter['field'] == 'subprojects') {
            if ($filter['compare'] == 92) {
                $excluded_subprojects[] = $filter['value'];
            } elseif ($filter['compare'] == 93) {
                $included_subprojects[] = $filter['value'];
            }
        } elseif ($filter['field'] == 'label') {
            $filter_on_labels = true;
        }
    }
    if ($filter_on_labels && $project_instance->ShareLabelFilters) {
        $share_label_filters = true;
        $response['sharelabelfilters'] = true;
        $label_ids_array = get_label_ids_from_filterdata($filterdata);
        $label_ids = '(' . implode(', ', $label_ids_array) . ')';
    }

    // Include takes precedence over exclude.
    if (!empty($included_subprojects)) {
        $num_selected_subprojects = count($included_subprojects);
        $selected_subprojects = implode("','", $included_subprojects);
        $selected_subprojects = "('" . $selected_subprojects . "')";
        $include_subprojects = true;
    } elseif (!empty($excluded_subprojects)) {
        $num_selected_subprojects = count($excluded_subprojects);
        $selected_subprojects = implode("','", $excluded_subprojects);
        $selected_subprojects = "('" . $selected_subprojects . "')";
        $exclude_subprojects = true;
    }

    // add a request for the subproject
    $subprojectsql = '';
    if ($subproject_name && is_numeric($subprojectid)) {
        $subprojectsql = ' AND sp2b.subprojectid=' . $subprojectid;
    }

    // Use this as the default date clause, but if $filterdata has a date clause,
    // then cancel this one out:
    //
    $date_clause = "AND b.starttime<'$end_UTCDate' AND b.starttime>='$beginning_UTCDate' ";

    if ($filterdata['hasdateclause']) {
        $date_clause = '';
    }

    $parent_clause = '';
    if (isset($_GET['parentid'])) {
        // If we have a parentid, then we should only show children of that build.
        // Date becomes irrelevant in this case.
        $parent_clause = 'AND (b.parentid = ' . qnum($_GET['parentid']) . ') ';
        $date_clause = '';
    } elseif (empty($subprojectsql)) {
        // Only show builds that are not children.
        $parent_clause = 'AND (b.parentid = -1 OR b.parentid = 0) ';
    }

    $build_rows = array();

    // If the user is logged in we display if the build has some changes for him
    $userupdatesql = '';
    if (isset($_SESSION['cdash']) && array_key_exists('loginid', $_SESSION['cdash'])) {
        $userupdatesql = "(SELECT count(updatefile.updateid) FROM updatefile,build2update,user2project,
            user2repository
                WHERE build2update.buildid=b.id
                AND build2update.updateid=updatefile.updateid
                AND user2project.projectid=b.projectid
                AND user2project.userid='" . $_SESSION['cdash']['loginid'] . "'
                AND user2repository.userid=user2project.userid
                AND (user2repository.projectid=0 OR user2repository.projectid=b.projectid)
                AND user2repository.credential=updatefile.author) AS userupdates,";
    }

    $sql = get_index_query();
    $sql .= "WHERE b.projectid='$projectid' AND g.type='Daily'
        $parent_clause $date_clause $subprojectsql $filter_sql $limit_sql";

    // We shouldn't get any builds for group that have been deleted (otherwise something is wrong)
    $builds = pdo_query($sql);

    // Log any errors
    $pdo_error = pdo_error();
    if (strlen($pdo_error) > 0) {
        add_log('SQL error: ' . $pdo_error, 'Index.php', LOG_ERR);
    }

    // Gather up results from this query.
    $build_data = array();
    while ($build_row = pdo_fetch_array($builds)) {
        $build_data[] = $build_row;
    }
    $dynamic_builds = array();
    if (empty($filter_sql)) {
        $dynamic_builds = get_dynamic_builds($projectid, $end_UTCDate);
        $build_data = array_merge($build_data, $dynamic_builds);
    }

    // Check if we need to summarize coverage by subproject groups.
    // This happens when we have subprojects and we're looking at the children
    // of a specific build.
    $coverage_groups = array();
    if (isset($_GET['parentid']) && $_GET['parentid'] > 0 &&
        $project_instance->GetNumberOfSubProjects($end_UTCDate) > 0
    ) {
        $groups = $project_instance->GetSubProjectGroups();
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
    $build_rows = array();
    foreach ($build_data as $build_row) {
        // Fields that come from the initial query:
        //  id
        //  sitename
        //  stamp
        //  name
        //  siteid
        //  type
        //  generator
        //  starttime
        //  endtime
        //  submittime
        //  groupname
        //  position
        //  groupid
        //  countupdatefiles
        //  updatestatus
        //  countupdatewarnings
        //  revision
        //  countbuildwarnings
        //  countbuilderrors
        //  countbuilderrordiff
        //  countbuildwarningdiff
        //  configureduration
        //  countconfigureerrors
        //  countconfigurewarnings
        //  countconfigurewarningdiff
        //  counttestsnotrun
        //  counttestsnotrundiff
        //  counttestsfailed
        //  counttestsfaileddiff
        //  counttestspassed
        //  counttestspasseddiff
        //  countteststimestatusfailed
        //  countteststimestatusfaileddiff
        //  testduration
        //
        // Fields that we add within this loop:
        //  maxstarttime
        //  buildids (array of buildids for summary rows)
        //  countbuildnotes (added by users)
        //  labels
        //  updateduration
        //  countupdateerrors
        //  test
        //

        $buildid = $build_row['id'];
        $groupid = $build_row['groupid'];
        $siteid = $build_row['siteid'];
        $parentid = $build_row['parentid'];

        $build_row['buildids'][] = $buildid;
        $build_row['maxstarttime'] = $build_row['starttime'];

        // Updates
        if (!empty($build_row['updatestarttime'])) {
            $build_row['updateduration'] = round((strtotime($build_row['updateendtime']) - strtotime($build_row['updatestarttime'])) / 60, 1);
        } else {
            $build_row['updateduration'] = 0;
        }

        if (strlen($build_row['updatestatus']) > 0 &&
            $build_row['updatestatus'] != '0'
        ) {
            $build_row['countupdateerrors'] = 1;
        } else {
            $build_row['countupdateerrors'] = 0;
        }

        // Error/Warnings differences
        if (empty($build_row['countbuilderrordiffp'])) {
            $build_row['countbuilderrordiffp'] = 0;
        }
        if (empty($build_row['countbuilderrordiffn'])) {
            $build_row['countbuilderrordiffn'] = 0;
        }

        if (empty($build_row['countbuildwarningdiffp'])) {
            $build_row['countbuildwarningdiffp'] = 0;
        }
        if (empty($build_row['countbuildwarningdiffn'])) {
            $build_row['countbuildwarningdiffn'] = 0;
        }

        $build_row['hasconfigure'] = 0;
        if ($build_row['countconfigureerrors'] != -1 ||
                $build_row['countconfigurewarnings'] != -1) {
            $build_row['hasconfigure'] = 1;
        }

        if ($build_row['countconfigureerrors'] < 0) {
            $build_row['countconfigureerrors'] = 0;
        }
        if ($build_row['countconfigurewarnings'] < 0) {
            $build_row['countconfigurewarnings'] = 0;
        }

        if (empty($build_row['countconfigurewarningdiff'])) {
            $build_row['countconfigurewarningdiff'] = 0;
        }

        $build_row['hastest'] = 0;
        if ($build_row['counttestsfailed'] != -1) {
            $build_row['hastest'] = 1;
        }

        if (empty($build_row['testduration'])) {
            $time_array = pdo_fetch_array(pdo_query("SELECT SUM(time) FROM build2test WHERE buildid='$buildid'"));
            $build_row['testduration'] = round($time_array[0], 1);
        } else {
            $build_row['testduration'] = round($build_row['testduration'], 1);
        }

        $build_rows[] = $build_row;
    }

    // Generate the JSON response from the rows of builds.
    $response['coverages'] = array();
    $response['dynamicanalyses'] = array();
    $num_nightly_coverages_builds = 0;
    $show_aggregate = false;
    $response['comparecoverage'] = 0;
    // We maintain a list of distinct build start times when viewing the children
    // of a specified parent build.  We do this because our view differs slightly
    // if the subprojects were built one at a time vs. all at once.
    $build_start_times = [];
    foreach ($build_rows as $build_array) {
        $groupid = $build_array['groupid'];

        // Find the buildgroup array for this build.
        $i = -1;
        for ($j = 0; $j < count($buildgroups_response); $j++) {
            if ($buildgroups_response[$j]['id'] == $groupid) {
                $i = $j;
                break;
            }
        }
        if ($i == -1) {
            add_log("BuildGroup '$groupid' not found for build #" . $build_array['id'],
                __FILE__ . ':' . __LINE__ . ' - ' . __FUNCTION__,
                LOG_WARNING);
            continue;
        }

        $groupname = $buildgroups_response[$i]['name'];

        $build_response = array();

        $received_builds[$groupname][] =
            $build_array['sitename'] . '_' . $build_array['name'];

        $buildid = $build_array['id'];
        $siteid = $build_array['siteid'];

        $countChildrenResult = pdo_single_row_query(
            'SELECT count(id) AS numchildren
                FROM build WHERE parentid=' . qnum($buildid));
        $numchildren = $countChildrenResult['numchildren'];
        $build_response['numchildren'] = $numchildren;
        $child_builds_hyperlink = '';

        $selected_configure_errors = 0;
        $selected_configure_warnings = 0;
        $selected_configure_duration = 0;
        $selected_build_errors = 0;
        $selected_build_warnings = 0;
        $selected_build_duration = 0;
        $selected_tests_not_run = 0;
        $selected_tests_failed = 0;
        $selected_tests_passed = 0;
        $selected_test_duration = 0;

        if ($numchildren > 0) {
            $child_builds_hyperlink =
                get_child_builds_hyperlink($build_array['id'], $filterdata);
            $build_response['multiplebuildshyperlink'] = $child_builds_hyperlink;
            $buildgroups_response[$i]['hasparentbuilds'] = true;

            // Compute selected (excluded or included) SubProject results.
            if ($selected_subprojects) {
                $select_query = "
                    SELECT configureerrors, configurewarnings, configureduration,
                           builderrors, buildwarnings, buildduration,
                           b.starttime, b.endtime, testnotrun, testfailed, testpassed,
                           btt.time AS testduration, sb.name
                    FROM build AS b
                    INNER JOIN subproject2build AS sb2b ON (b.id = sb2b.buildid)
                    INNER JOIN subproject AS sb ON (sb2b.subprojectid = sb.id)
                    LEFT JOIN buildtesttime AS btt ON (b.id=btt.buildid)
                    WHERE b.parentid=$buildid
                    AND sb.name IN $selected_subprojects";
                $select_results = pdo_query($select_query);
                while ($select_array = pdo_fetch_array($select_results)) {
                    $selected_configure_errors +=
                        max(0, $select_array['configureerrors']);
                    $selected_configure_warnings +=
                        max(0, $select_array['configurewarnings']);
                    $selected_configure_duration +=
                        max(0, $select_array['configureduration']);
                    $selected_build_errors +=
                        max(0, $select_array['builderrors']);
                    $selected_build_warnings +=
                        max(0, $select_array['buildwarnings']);
                    $selected_build_duration +=
                        max(0, $select_array['buildduration']);
                    $selected_tests_not_run +=
                        max(0, $select_array['testnotrun']);
                    $selected_tests_failed +=
                        max(0, $select_array['testfailed']);
                    $selected_tests_passed +=
                        max(0, $select_array['testpassed']);
                    $selected_test_duration +=
                        max(0, $select_array['testduration']);
                }
            }
        } else {
            $buildgroups_response[$i]['hasnormalbuilds'] = true;
        }

        if (strtolower($build_array['type']) == 'continuous') {
            $buildgroups_response[$i]['sorttype'] = 'time';
        }

        // Attempt to determine the platform based on the OSName and the buildname
        $buildplatform = '';
        if (strtolower(substr($build_array['osname'], 0, 7)) == 'windows') {
            $buildplatform = 'windows';
        } elseif (strtolower(substr($build_array['osname'], 0, 8)) == 'mac os x') {
            $buildplatform = 'mac';
        } elseif (strtolower(substr($build_array['osname'], 0, 5)) == 'linux'
            || strtolower(substr($build_array['osname'], 0, 3)) == 'aix'
        ) {
            $buildplatform = 'linux';
        } elseif (strtolower(substr($build_array['osname'], 0, 7)) == 'freebsd') {
            $buildplatform = 'freebsd';
        } elseif (strtolower(substr($build_array['osname'], 0, 3)) == 'gnu') {
            $buildplatform = 'gnu';
        }

        // Add link based on changeid if appropriate.
        $changelink = null;
        $changeicon = null;
        if ($build_array['changeid'] &&
                $project_instance->CvsViewerType === 'github') {
            $changelink = $project_instance->CvsUrl . '/pull/' .
                $build_array['changeid'];
            $changeicon = 'img/Octocat.png';
        }

        if (isset($_GET['parentid'])) {
            if (empty($site_response)) {
                $site_response['site'] = $build_array['sitename'];
                $site_response['siteoutoforder'] = $build_array['siteoutoforder'];
                $site_response['siteid'] = $siteid;
                $site_response['buildname'] = $build_array['name'];
                $site_response['buildplatform'] = $buildplatform;
                $site_response['generator'] = $build_array['generator'];
                if (!is_null($changelink)) {
                    $site_response['changelink'] = $changelink;
                    $site_response['changeicon'] = $changeicon;
                }
            }
        } else {
            $build_response['site'] = $build_array['sitename'];
            $build_response['siteoutoforder'] = $build_array['siteoutoforder'];
            $build_response['siteid'] = $siteid;
            $build_response['buildname'] = $build_array['name'];
            $build_response['buildplatform'] = $buildplatform;
            if (!is_null($changelink)) {
                $build_response['changelink'] = $changelink;
                $build_response['changeicon'] = $changeicon;
            }
        }

        if (isset($build_array['userupdates'])) {
            $build_response['userupdates'] = $build_array['userupdates'];
        }
        $build_response['id'] = $build_array['id'];
        $build_response['done'] = $build_array['done'];
        $build_response['uploadfilecount'] = $build_array['builduploadfiles'];

        $build_response['buildnotes'] = $build_array['countbuildnotes'];
        $build_response['notes'] = $build_array['countnotes'];

        // Figure out how many labels to report for this build.
        if (!array_key_exists('numlabels', $build_array) ||
            $build_array['numlabels'] == 0
        ) {
            $num_labels = 0;
        } else {
            $num_labels = $build_array['numlabels'];
        }

        $label_query =
            'SELECT l.text FROM label AS l
            INNER JOIN label2build AS l2b ON (l.id=l2b.labelid)
            INNER JOIN build AS b ON (l2b.buildid=b.id)
            WHERE b.id=' . qnum($buildid);

        $build_labels = array();
        if ($num_selected_subprojects > 0) {
            // Special handling for whitelisting/blacklisting SubProjects.
            if ($include_subprojects) {
                $num_labels = 0;
            }
            $labels_result = pdo_query($label_query);
            while ($label_row = pdo_fetch_array($labels_result)) {
                // Whitelist case
                if ($include_subprojects &&
                    in_array($label_row['text'], $included_subprojects)
                ) {
                    $num_labels++;
                    $build_labels[] = $label_row['text'];
                }
                // Blacklist case
                if ($exclude_subprojects) {
                    if (in_array($label_row['text'], $excluded_subprojects)) {
                        $num_labels--;
                    } else {
                        $build_labels[] = $label_row['text'];
                    }
                }
            }

            if ($num_labels === 0) {
                // Skip this build entirely if none of its SubProjects
                // survived filtering.
                continue;
            }
        }

        // Assign a label to this build based on how many labels it has.
        if ($num_labels == 0) {
            $build_label = '(none)';
        } elseif ($num_labels == 1) {
            // Exactly one label for this build
            if (!empty($build_labels)) {
                // If we're whitelisting or blacklisting we've already figured
                // out what this label is.
                $build_label = $build_labels[0];
            } else {
                // Otherwise we look it up here.
                $label_result = pdo_single_row_query($label_query);
                $build_label = $label_result['text'];
            }
        } else {
            // More than one label, just report the number.
            $build_label = "($num_labels labels)";
        }
        $build_response['label'] = $build_label;

        // Report subproject position for this build (if any).
        if ($build_array['subprojectposition']) {
            $build_response['position'] = $build_array['subprojectposition'];
        } else {
            $build_response['position'] = 0;
        }

        // Update our list of distinct start times for child builds.
        if ($response['childview'] == 1 &&
                !in_array($build_array['starttime'], $build_start_times)) {
            $build_start_times[] = $build_array['starttime'];
        }

        // Calculate this build's total duration.
        $duration = strtotime($build_array['endtime']) -
            strtotime($build_array['starttime']);
        $build_response['time'] = time_difference($duration, true);
        $build_response['timefull'] = $duration;

        $update_response = array();

        $countupdatefiles = $build_array['countupdatefiles'];
        $buildgroups_response[$i]['numupdatedfiles'] += $countupdatefiles;

        $build_response['hasupdate'] = false;
        if (!empty($build_array['updatestarttime'])) {
            $build_response['hasupdate'] = true;

            // Record what type of update to report for this project.
            if (!array_key_exists('updatetype', $response) ||
                empty($response['updatetype'])) {
                if (!empty($build_array['revision'])) {
                    $response['updatetype'] = 'Revision';
                } else {
                    $response['updatetype'] = 'Files';
                }
            }
            if ($response['updatetype'] === 'Revision') {
                $revision = $build_array['revision'];
                // Trim revision to six characters.
                $revision = substr($revision, 0, 6);
                // Note that this field is still called 'files' so as not to
                // break our previously released API.
                $update_response['files'] = $revision;
            } else {
                $update_response['files'] = $countupdatefiles;
            }

            if ($build_array['countupdateerrors'] > 0) {
                $update_response['errors'] = 1;
                $buildgroups_response[$i]['numupdateerror'] += 1;
            } else {
                $update_response['errors'] = 0;

                if ($build_array['countupdatewarnings'] > 0) {
                    $update_response['warning'] = 1;
                    $buildgroups_response[$i]['numupdatewarning'] += 1;
                }
            }

            $duration = $build_array['updateduration'];
            $update_response['time'] = time_difference($duration * 60.0, true);
            $update_response['timefull'] = $duration;
            $buildgroups_response[$i]['updateduration'] += $duration;
            $buildgroups_response[$i]['hasupdatedata'] = true;
            $build_response['update'] = $update_response;
        }

        $compilation_response = array();

        if ($build_array['countbuilderrors'] >= 0) {
            if ($include_subprojects) {
                $nerrors = $selected_build_errors;
                $nwarnings = $selected_build_warnings;
                $buildduration = $selected_build_duration;
            } else {
                $nerrors =
                    $build_array['countbuilderrors'] - $selected_build_errors;
                $nwarnings = $build_array['countbuildwarnings'] -
                    $selected_build_warnings;
                $buildduration = $build_array['buildduration'] -
                    $selected_build_duration;
            }
            $compilation_response['error'] = $nerrors;
            $buildgroups_response[$i]['numbuilderror'] += $nerrors;

            $compilation_response['warning'] = $nwarnings;
            $buildgroups_response[$i]['numbuildwarning'] += $nwarnings;

            $compilation_response['time'] = time_difference($buildduration, true);
            $compilation_response['timefull'] = $buildduration;

            if (!$include_subprojects && !$exclude_subprojects) {
                // Don't show diff when filtering by SubProject.
                $compilation_response['nerrordiffp'] =
                    $build_array['countbuilderrordiffp'];
                $compilation_response['nerrordiffn'] =
                    $build_array['countbuilderrordiffn'];
                $compilation_response['nwarningdiffp'] =
                    $build_array['countbuildwarningdiffp'];
                $compilation_response['nwarningdiffn'] =
                    $build_array['countbuildwarningdiffn'];
            }
        }
        $build_response['hascompilation'] = false;
        if (!empty($compilation_response)) {
            $build_response['hascompilation'] = true;
            $build_response['compilation'] = $compilation_response;
            $buildgroups_response[$i]['hascompilationdata'] = true;
        }

        $build_response['hasconfigure'] = false;
        if ($build_array['hasconfigure'] != 0) {
            $build_response['hasconfigure'] = true;
            $configure_response = array();

            if ($include_subprojects) {
                $nconfigureerrors = $selected_configure_errors;
                $nconfigurewarnings = $selected_configure_warnings;
                $configureduration = $selected_configure_duration;
            } else {
                $nconfigureerrors = $build_array['countconfigureerrors'] -
                    $selected_configure_errors;
                $nconfigurewarnings = $build_array['countconfigurewarnings'] -
                    $selected_configure_warnings;
                $configureduration = $build_array['configureduration'] -
                    $selected_configure_duration;
            }
            $configure_response['error'] = $nconfigureerrors;
            $buildgroups_response[$i]['numconfigureerror'] += $nconfigureerrors;

            $configure_response['warning'] = $nconfigurewarnings;
            $buildgroups_response[$i]['numconfigurewarning'] += $nconfigurewarnings;

            if (!$include_subprojects && !$exclude_subprojects) {
                $configure_response['warningdiff'] =
                    $build_array['countconfigurewarningdiff'];
            }

            $configure_response['time'] =
                time_difference($configureduration, true);
            $configure_response['timefull'] = $configureduration;

            $build_response['configure'] = $configure_response;
            $buildgroups_response[$i]['hasconfiguredata'] = true;
            $buildgroups_response[$i]['configureduration'] += $configureduration;
        }

        $build_response['hastest'] = false;
        if ($build_array['hastest'] != 0) {
            $build_response['hastest'] = true;
            $buildgroups_response[$i]['hastestdata'] = true;
            $test_response = array();

            if ($include_subprojects) {
                $nnotrun = $selected_tests_not_run;
                $nfail = $selected_tests_failed;
                $npass = $selected_tests_passed;
                $testduration = $selected_test_duration;
            } else {
                $nnotrun = $build_array['counttestsnotrun'] -
                    $selected_tests_not_run;
                $nfail = $build_array['counttestsfailed'] -
                    $selected_tests_failed;
                $npass = $build_array['counttestspassed'] -
                    $selected_tests_passed;
                $testduration = $build_array['testduration'] -
                    $selected_test_duration;
            }

            if (!$include_subprojects && !$exclude_subprojects) {
                $test_response['nnotrundiffp'] =
                    $build_array['counttestsnotrundiffp'];
                $test_response['nnotrundiffn'] =
                    $build_array['counttestsnotrundiffn'];

                $test_response['nfaildiffp'] =
                    $build_array['counttestsfaileddiffp'];
                $test_response['nfaildiffn'] =
                    $build_array['counttestsfaileddiffn'];

                $test_response['npassdiffp'] =
                    $build_array['counttestspasseddiffp'];
                $test_response['npassdiffn'] =
                    $build_array['counttestspasseddiffn'];
            }

            if ($project_array['showtesttime'] == 1) {
                $test_response['timestatus'] = $build_array['countteststimestatusfailed'];
                $test_response['ntimediffp'] =
                    $build_array['countteststimestatusfaileddiffp'];
                $test_response['ntimediffn'] =
                    $build_array['countteststimestatusfaileddiffn'];
            }

            if ($share_label_filters) {
                $label_query_base =
                    "SELECT b2t.status, b2t.newstatus
                    FROM build2test AS b2t
                    INNER JOIN label2test AS l2t ON
                    (l2t.testid=b2t.testid AND l2t.buildid=b2t.buildid)
                    WHERE b2t.buildid = '$buildid' AND
                    l2t.labelid IN $label_ids";
                $label_filter_query = $label_query_base . $limit_sql;
                $labels_result = pdo_query($label_filter_query);

                $nnotrun = 0;
                $nfail = 0;
                $npass = 0;
                $test_response['nfaildiffp'] = 0;
                $test_response['nfaildiffn'] = 0;
                $test_response['npassdiffp'] = 0;
                $test_response['npassdiffn'] = 0;
                $test_response['nnotrundiffp'] = 0;
                $test_response['nnotrundiffn'] = 0;
                while ($label_row = pdo_fetch_array($labels_result)) {
                    switch ($label_row['status']) {
                        case 'passed':
                            $npass++;
                            if ($label_row['newstatus'] == 1) {
                                $test_response['npassdiffp']++;
                            }
                            break;
                        case 'failed':
                            $nfail++;
                            if ($label_row['newstatus'] == 1) {
                                $test_response['nfaildiffp']++;
                            }
                            break;
                        case 'notrun':
                            $nnotrun++;
                            if ($label_row['newstatus'] == 1) {
                                $test_response['nnotrundiffp']++;
                            }
                            break;
                    }
                }
            }

            $test_response['notrun'] = $nnotrun;
            $test_response['fail'] = $nfail;
            $test_response['pass'] = $npass;

            $buildgroups_response[$i]['numtestnotrun'] += $nnotrun;
            $buildgroups_response[$i]['numtestfail'] += $nfail;
            $buildgroups_response[$i]['numtestpass'] += $npass;

            $test_response['time'] = time_difference($testduration, true);
            $test_response['timefull'] = $testduration;
            $buildgroups_response[$i]['testduration'] += $testduration;

            $build_response['test'] = $test_response;
        }

        $starttimestamp = strtotime($build_array['starttime'] . ' UTC');
        $submittimestamp = strtotime($build_array['submittime'] . ' UTC');
        // Use the default timezone.
        $build_response['builddatefull'] = $starttimestamp;

        // If the data is more than 24h old then we switch from an elapsed to a normal representation
        if (time() - $starttimestamp < 86400) {
            $build_response['builddate'] = date(FMT_DATETIMEDISPLAY, $starttimestamp);
            $build_response['builddateelapsed'] = time_difference(time() - $starttimestamp, false, 'ago');
        } else {
            $build_response['builddateelapsed'] = date(FMT_DATETIMEDISPLAY, $starttimestamp);
            $build_response['builddate'] = time_difference(time() - $starttimestamp, false, 'ago');
        }
        $build_response['submitdate'] = date(FMT_DATETIMEDISPLAY, $submittimestamp);

        // Generate a string summarizing this build's timing.
        $timesummary = $build_response['builddate'];
        if ($build_response['hasupdate'] &&
                array_key_exists('time', $build_response['update'])) {
            $timesummary .= ', Update time: ' .
                $build_response['update']['time'];
        }
        if ($build_response['hasconfigure'] &&
                array_key_exists('time', $build_response['configure'])) {
            $timesummary .= ', Configure time: ' .
                $build_response['configure']['time'];
        }
        if ($build_response['hascompilation'] &&
                array_key_exists('time', $build_response['compilation'])) {
            $timesummary .= ', Build time: ' .
                $build_response['compilation']['time'];
        }
        if ($build_response['hastest'] &&
                array_key_exists('time', $build_response['test'])) {
            $timesummary .= ', Test time: ' .
                $build_response['test']['time'];
        }

        $timesummary .= ', Total time: ' . $build_response['time'];

        $build_response['timesummary'] = $timesummary;

        if ($include_subprojects || $exclude_subprojects) {
            // Check if this build should be filtered out now that its
            // numbers have been updated by the SubProject include/exclude
            // filter.
            if (!build_survives_filter($build_response, $filterdata)) {
                continue;
            }
        }

        if ($build_array['name'] != 'Aggregate Coverage') {
            $buildgroups_response[$i]['builds'][] = $build_response;
        }

        // Coverage
        //

        // Determine if this is a parent build with no actual coverage of its own.
        $linkToChildCoverage = false;
        if ($numchildren > 0) {
            $countChildrenResult = pdo_single_row_query(
                'SELECT count(fileid) AS nfiles FROM coverage
                    WHERE buildid=' . qnum($buildid));
            if ($countChildrenResult['nfiles'] == 0) {
                $linkToChildCoverage = true;
            }
        }

        $coverageIsGrouped = false;

        $loctested = $build_array['loctested'];
        $locuntested = $build_array['locuntested'];
        if ($loctested + $locuntested > 0) {
            $coverage_response = array();
            $coverage_response['buildid'] = $build_array['id'];
            if ($linkToChildCoverage) {
                $coverage_response['childlink'] = "$child_builds_hyperlink##Coverage";
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

            $percent = round(
                compute_percentcoverage($loctested,
                    $locuntested), 2);

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
            $coverage_response['label'] = $build_label;

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
            if ($numchildren > 0) {
                $countChildrenResult = pdo_single_row_query(
                        'SELECT count(id) AS num FROM dynamicanalysis
                        WHERE buildid=' . qnum($build_array['id']));
                if ($countChildrenResult['num'] == 0) {
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
                $DA_response['childlink'] = "$child_builds_hyperlink##DynamicAnalysis";
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
            $DA_response['label'] = $build_label;

            $response['dynamicanalyses'][] = $DA_response;
        }
    }

    // Put some finishing touches on our buildgroups now that we're done
    // iterating over all the builds.
    $addExpected =
        empty($filter_sql) && (pdo_num_rows($builds) + count($dynamic_builds) > 0);
    for ($i = 0; $i < count($buildgroups_response); $i++) {
        $buildgroups_response[$i]['testduration'] = time_difference(
            $buildgroups_response[$i]['testduration'], true);

        $num_expected_builds = 0;
        if (!$filter_sql) {
            $groupname = $buildgroups_response[$i]['name'];
            $expected_builds =
                add_expected_builds($buildgroups_response[$i]['id'], $currentstarttime,
                    $received_builds[$groupname]);
            if (is_array($expected_builds)) {
                $num_expected_builds = count($expected_builds);
                $buildgroups_response[$i]['builds'] = array_merge(
                    $buildgroups_response[$i]['builds'], $expected_builds);
            }
        }
        // Show how many builds this group has.
        $num_builds = count($buildgroups_response[$i]['builds']);
        $num_builds_label = '';
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
        $buildgroups_response[$i]['numbuildslabel'] = $num_builds_label;
    }

    // Create a separate "all buildgroups" section of our response.
    // This is used to allow project admins to move builds between groups.
    $response['all_buildgroups'] = array();
    foreach ($buildgroups_response as $group) {
        $response['all_buildgroups'][] =
            array('id' => $group['id'], 'name' => $group['name']);
    }

    // At this point it is safe to remove any empty buildgroups from our response.
    function is_buildgroup_nonempty($group)
    {
        return !empty($group['builds']);
    }

    $buildgroups_response =
        array_filter($buildgroups_response, 'is_buildgroup_nonempty');

    // Report buildgroups as a list, not an associative array.
    // Otherwise any missing buildgroups will cause our view to
    // not honor the order specified by the project admins.
    $buildgroups_response = array_values($buildgroups_response);

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
        if (!empty($buildgroups_response)) {
            $numchildren = count($buildgroups_response[0]['builds']);
        } else {
            $row = pdo_single_row_query(
                    'SELECT count(id) AS numchildren
                    FROM build WHERE parentid=' . qnum($parentid));
            $numchildren = $row['numchildren'];
        }
        $response['numchildren'] = $numchildren;

        // If all our children share the same start time, then this was an "all at once" subproject build.
        // In that case, tell our view to display the "Order" column instead of the "Start Time" column.
        if (count($build_start_times) === 1) {
            $response['showorder'] = true;
            $response['showstarttime'] = false;
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

    $response['buildgroups'] = $buildgroups_response;
    $response['enableTestTiming'] = $project_array['showtesttime'];

    $end = microtime_float();
    $response['generationtime'] = round($end - $start, 3);
    if (!empty($site_response)) {
        $response = array_merge($response, $site_response);
    }

    echo json_encode(cast_data_for_JSON($response));
}

// Get a link to a page showing the children of a given parent build.
function get_child_builds_hyperlink($parentid, $filterdata)
{
    $baseurl = $_SERVER['REQUEST_URI'];

    // Strip /api/v#/ off of our URL to get the human-viewable version.
    $baseurl = preg_replace('#/api/v[0-9]+#', '', $baseurl);

    // Trim off any filter parameters.  Previously we did this step with a simple
    // strpos check, but since the change to AngularJS query parameters are no
    // longer guaranteed to appear in any particular order.
    $accepted_parameters = array('project', 'parentid', 'subproject');

    $parsed_url = parse_url($baseurl);
    $query = $parsed_url['query'];

    parse_str($query, $params);
    $query_modified = false;
    foreach ($params as $key => $val) {
        if (!in_array($key, $accepted_parameters)) {
            unset($params[$key]);
            $query_modified = true;
        }
    }
    if ($query_modified) {
        $trimmed_query = http_build_query($params);
        $baseurl = str_replace($query, '', $baseurl);
        $baseurl .= $trimmed_query;
    }

    // Preserve any filters the user had specified.
    $existing_filter_params = '';
    $n = 0;
    $count = count($filterdata['filters']);
    $num_includes = 0;
    for ($i = 0; $i < $count; $i++) {
        $filter = $filterdata['filters'][$i];

        if ($filter['field'] == 'subprojects') {
            // If we're filtering subprojects at the parent-level
            // convert that to the appropriate filter for the child-level.
            $n++;
            $compare = 0;
            if ($filter['compare'] == 92) {
                $compare = 62;
            } elseif ($filter['compare'] == 93) {
                $num_includes++;
                $compare = 61;
            }
            $existing_filter_params .=
                '&field' . $n . '=' . 'subproject' .
                '&compare' . $n . '=' . $compare .
                '&value' . $n . '=' . htmlspecialchars($filter['value']);
        } elseif ($filter['field'] != 'buildname' &&
            $filter['field'] != 'site' &&
            $filter['field'] != 'stamp' &&
            $filter['compare'] != 0 &&
            $filter['compare'] != 20 &&
            $filter['compare'] != 40 &&
            $filter['compare'] != 60 &&
            $filter['compare'] != 80
        ) {
            $n++;

            $existing_filter_params .=
                '&field' . $n . '=' . $filter['field'] .
                '&compare' . $n . '=' . $filter['compare'] .
                '&value' . $n . '=' . htmlspecialchars($filter['value']);
        }
    }
    if ($n > 0) {
        $existing_filter_params =
            "&filtercount=$count&showfilters=1$existing_filter_params";

        // Multiple subproject includes need to be combined with 'or' (not 'and')
        // at the child level.
        if ($num_includes > 1) {
            $existing_filter_params .= '&filtercombine=or';
        } elseif (!empty($filterdata['filtercombine'])) {
            $existing_filter_params .=
                '&filtercombine=' . $filterdata['filtercombine'];
        }
    }

    // Construct & return our URL.
    $url = "$baseurl&parentid=$parentid";
    $url .= $existing_filter_params;
    return $url;
}

// Find expected builds that haven't submitted yet.
function add_expected_builds($groupid, $currentstarttime, $received_builds)
{
    include dirname(dirname(dirname(__DIR__))) . '/config/config.php';

    if (isset($_GET['parentid'])) {
        // Don't add expected builds when viewing a single subproject result.
        return;
    }

    $currentUTCTime = gmdate(FMT_DATETIME, $currentstarttime + 3600 * 24);
    $response = array();
    $build2grouprule = pdo_query(
        "SELECT g.siteid, g.buildname, g.buildtype, s.name, s.outoforder
            FROM build2grouprule AS g, site AS s
            WHERE g.expected='1' AND g.groupid='$groupid' AND s.id=g.siteid AND
            g.starttime<'$currentUTCTime' AND
            (g.endtime>'$currentUTCTime' OR g.endtime='1980-01-01 00:00:00')");
    while ($build2grouprule_array = pdo_fetch_array($build2grouprule)) {
        $key = $build2grouprule_array['name'] . '_' . $build2grouprule_array['buildname'];
        if (array_search($key, $received_builds) === false) {
            // add only if not found

            $site = $build2grouprule_array['name'];
            $siteid = $build2grouprule_array['siteid'];
            $siteoutoforder = $build2grouprule_array['outoforder'];
            $buildtype = $build2grouprule_array['buildtype'];
            $buildname = $build2grouprule_array['buildname'];
            $build_response = array();
            $build_response['site'] = $site;
            $build_response['siteoutoforder'] = $siteoutoforder;
            $build_response['siteid'] = $siteid;
            $build_response['id'] = false;
            $build_response['buildname'] = $buildname;
            $build_response['buildtype'] = $buildtype;
            $build_response['buildgroupid'] = $groupid;
            $build_response['expectedandmissing'] = 1;
            $build_response['hasupdate'] = false;
            $build_response['hasconfigure'] = false;
            $build_response['hascompilation'] = false;
            $build_response['hastest'] = false;

            // Compute historical average to get approximate expected time.
            // PostgreSQL doesn't have the necessary functions for this.
            if ($CDASH_DB_TYPE == 'pgsql') {
                $query = pdo_query(
                    "SELECT submittime FROM build,build2group
                        WHERE build2group.buildid=build.id AND siteid='$siteid' AND
                        name='$buildname' AND type='$buildtype' AND
                        build2group.groupid='$groupid'
                        ORDER BY id DESC LIMIT 5");
                $time = 0;
                while ($query_array = pdo_fetch_array($query)) {
                    $time += strtotime(date('H:i:s', strtotime($query_array['submittime'])));
                }
                if (pdo_num_rows($query) > 0) {
                    $time /= pdo_num_rows($query);
                }
                $nextExpected = strtotime(date('H:i:s', $time) . ' UTC');
            } else {
                $query = pdo_query(
                    "SELECT AVG(TIME_TO_SEC(TIME(submittime)))
                        FROM
                        (SELECT submittime FROM build,build2group
                         WHERE build2group.buildid=build.id AND siteid='$siteid' AND
                         name='$buildname' AND type='$buildtype' AND
                         build2group.groupid='$groupid'
                         ORDER BY id DESC LIMIT 5)
                        AS t");
                $query_array = pdo_fetch_array($query);
                $time = $query_array[0];
                $hours = floor($time / 3600);
                $time = ($time % 3600);
                $minutes = floor($time / 60);
                $seconds = ($time % 60);
                $nextExpected = strtotime($hours . ':' . $minutes . ':' . $seconds . ' UTC');
            }

            $divname = $build2grouprule_array['siteid'] . '_' . $build2grouprule_array['buildname'];
            $divname = str_replace('+', '_', $divname);
            $divname = str_replace('.', '_', $divname);
            $divname = str_replace(':', '_', $divname);
            $divname = str_replace(' ', '_', $divname);

            $build_response['expecteddivname'] = $divname;
            $build_response['submitdate'] = 'No Submission';
            $build_response['expectedstarttime'] = date(FMT_TIME, $nextExpected);
            $response[] = $build_response;
        }
    }
    return $response;
}
