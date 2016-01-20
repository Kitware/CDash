<?php
/*=========================================================================

  Program:   CDash - Cross-Platform Dashboard System
  Module:    $Id$
  Language:  PHP
  Date:      $Date$
  Version:   $Revision$

  Copyright (c) 2002 Kitware, Inc.  All rights reserved.
  See Copyright.txt or http://www.cmake.org/HTML/Copyright.html for details.

  This software is distributed WITHOUT ANY WARRANTY; without even
  the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR
  PURPOSE.  See the above copyright notices for more information.

  =========================================================================*/


include(dirname(dirname(dirname(__DIR__)))."/config/config.php");
require_once("include/pdo.php");
include("include/common.php");
include('include/version.php');
require_once("models/project.php");
require_once("models/buildfailure.php");
require_once("include/filterdataFunctions.php");
require_once("include/index_functions.php");

set_time_limit(0);

// Check if we can connect to the database.
$db = pdo_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN", "$CDASH_DB_PASS");
if (!$db ||
        pdo_select_db("$CDASH_DB_NAME", $db) === false ||
        pdo_query("SELECT id FROM ".qid("user")." LIMIT 1", $db) === false) {
    if ($CDASH_PRODUCTION_MODE) {
        $response = array();
        $response['error'] = "CDash cannot connect to the database.";
        echo json_encode($response);
        return;
    } else {
        // redirect to the install.php script
        header('Location: install.php');
    }
    return;
}

@$projectname = $_GET["project"];
$projectname = htmlspecialchars(pdo_real_escape_string($projectname));
$projectid = get_project_id($projectname);
$Project = new Project();
$Project->Id = $projectid;
$Project->Fill();

@$date = $_GET["date"];
if ($date != null) {
    $date = htmlspecialchars(pdo_real_escape_string($date));
}

echo_main_dashboard_JSON($Project, $date);


// Generate the main dashboard JSON response.
function echo_main_dashboard_JSON($project_instance, $date)
{
    $start = microtime_float();
    $noforcelogin = 1;
    include_once(dirname(dirname(dirname(__DIR__)))."/config/config.php");
    require_once("include/pdo.php");
    include('public/login.php');
    include_once("models/banner.php");
    include_once("models/build.php");
    include_once("models/subproject.php");

    $response = begin_JSON_response();
    $response['title'] = "CDash : Compare Coverage";

    $db = pdo_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN", "$CDASH_DB_PASS");
    if (!$db) {
        $response['error'] = "Error connecting to CDash database server";
        echo json_encode($response);
        return;
    }
    if (!pdo_select_db("$CDASH_DB_NAME", $db)) {
        $response['error'] = "Error selecting CDash database";
        echo json_encode($response);
        return;
    }

    $projectid = $project_instance->Id;

    $project = pdo_query("SELECT * FROM project WHERE id='$projectid'");
    if (pdo_num_rows($project)>0) {
        $project_array = pdo_fetch_array($project);
        $projectname = $project_array["name"];
    } else {
        $response['error'] =
            "This project doesn't exist. Maybe the URL you are trying to access is wrong.";
        echo json_encode($response);
        return;
    }

    if (!checkUserPolicy(@$_SESSION['cdash']['loginid'], $project_array["id"], 1)) {
        $response['requirelogin'] = 1;
        echo json_encode($response);
        return;
    }

    list($previousdate, $currentstarttime, $nextdate) = get_dates($date, $project_array["nightlytime"]);

    $response['date'] = $date;
    $response['projectid'] = $projectid;
    $response['projectname'] = $projectname;

    $page_id = 'compareCoverage.php';

    // Filters:
    //
    $filterdata = get_filterdata_from_request($page_id);
    $filter_sql = $filterdata['sql'];
    
    $response['filter_sql'] = $filter_sql;
    
    $limit_sql = '';
    if ($filterdata['limit'] > 0) {
        $limit_sql = ' LIMIT '.$filterdata['limit'];
    }
    unset($filterdata['xml']);
    $response['filterdata'] = $filterdata;
    $response['filterurl'] = @$_GET['filterstring'];


    // Menu definition
    $beginning_timestamp = $currentstarttime;
    $end_timestamp = $currentstarttime+3600*24;
    $beginning_UTCDate = gmdate(FMT_DATETIME, $beginning_timestamp);
    $end_UTCDate = gmdate(FMT_DATETIME, $end_timestamp);

    $menu = array();

    // Menu
    if ($date == '') {
    $back = "index.php?project=".urlencode($project_array['name']);
    } else {
        $back = "index.php?project=".urlencode($project_array['name'])."&date=".$date;
    }
    $menu['back'] = $back;


    $limit_param = "&limit=".$filterdata['limit'];

    $menu['previous'] =
      $page_id."?project=".urlencode($project_array['name'])."&date=".$previousdate;

    $today = date(FMT_DATE, time());
    $menu['current'] =
      $page_id."?project=".urlencode($project_array['name'])."&date=".$today;

    if (has_next_date($date, $currentstarttime)) {
        $menu['next'] =
          $page_id."?project=".urlencode($project_array['name'])."&date=".$nextdate;
    } else {
        $menu['nonext'] = "1";
    }

    $response['menu'] = $menu;

    // User
    if (isset($_SESSION['cdash'])) {
        $user_response = array();
        $userid = $_SESSION['cdash']['loginid'];
        $user2project = pdo_query(
                "SELECT role FROM user2project
                WHERE userid='$userid' AND projectid='$projectid'");
        $user2project_array = pdo_fetch_array($user2project);
        $user = pdo_query(
                "SELECT admin FROM ".qid("user")."  WHERE id='$userid'");
        $user_array = pdo_fetch_array($user);
        $user_response['id'] = $userid;
        $isadmin = 0;
        if ($user2project_array["role"] > 1 || $user_array["admin"]) {
            $isadmin=1;
        }
        $user_response['admin'] = $isadmin;
        $user_response['projectrole'] = $user2project_array['role'];
        $response['user'] = $user_response;
    }

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
        $buildgroup_response['id'] = $buildgroup_array['id'];
        $buildgroup_response['name'] = $buildgroup_array['name'];
        $buildgroups_response[] = $buildgroup_response;
    }

    // Check if we should be excluding some SubProjects from our
    // build results.
    $include_subprojects = false;
    $exclude_subprojects = false;
    $included_subprojects = array();
    $excluded_subprojects = array();
    $selected_subprojects = "";
    $num_selected_subprojects = 0;
    foreach ($filterdata['filters'] as $filter) {
        if ($filter['field'] == 'subprojects') {
            if ($filter['compare'] == 92) {
                $excluded_subprojects[] = $filter['value'];
            } elseif ($filter['compare'] == 93) {
                $included_subprojects[] = $filter['value'];
            }
        }
    }
    // Include takes precedence over exclude.
    if (!empty($included_subprojects)) {
        $num_selected_subprojects = count($included_subprojects);
        $selected_subprojects = implode("','", $included_subprojects);
        $selected_subprojects = "('".$selected_subprojects."')";
        $include_subprojects = true;
    } elseif (!empty($excluded_subprojects)) {
        $num_selected_subprojects = count($excluded_subprojects);
        $selected_subprojects = implode("','", $excluded_subprojects);
        $selected_subprojects = "('".$selected_subprojects."')";
        $exclude_subprojects = true;
    }

    $parentid = null;
    $build_data = get_build_data($projectid, $beginning_UTCDate, $end_UTCDate,
                                 $filterdata, $parentid, $filter_sql, $limit_sql);

    // Generate the JSON response from the rows of builds.
    $response['coverages'] = array();
    foreach ($build_data as $build_array) {
        $groupid = $build_array["groupid"];

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

        $buildid = $build_array["id"];
        $siteid = $build_array["siteid"];

        // Figure out how many labels to report for this build.
        if (!array_key_exists('numlabels', $build_array) ||
                $build_array['numlabels'] == 0) {
            $num_labels = 0;
        } else {
            $num_labels = $build_array['numlabels'];
        }

        $label_query =
            "SELECT l.text FROM label AS l
            INNER JOIN label2build AS l2b ON (l.id=l2b.labelid)
            INNER JOIN build AS b ON (l2b.buildid=b.id)
            WHERE b.id=" . qnum($buildid);

        if ($num_selected_subprojects > 0) {
            // Special handling for whitelisting/blacklisting SubProjects.
            if ($include_subprojects) {
                $num_labels = 0;
            }
            $labels_result = pdo_query($label_query);
            while ($label_row = pdo_fetch_array($labels_result)) {
                // Whitelist case
                if ($include_subprojects &&
                        in_array($label_row['text'], $included_subprojects)) {
                    $num_labels++;
                }
                // Blacklist case
                if ($exclude_subprojects &&
                        in_array($label_row['text'], $excluded_subprojects)) {
                    $num_labels--;
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
            $build_label = "(none)";
        } elseif ($num_labels == 1) {
            // If exactly one label for this build, look it up here.
            $label_result = pdo_single_row_query($label_query);
            $build_label = $label_result['text'];
        } else {
            // More than one label, just report the number.
            $build_label = "($num_labels labels)";
        }

        // Coverage
        //
        $coverages = pdo_query("SELECT * FROM coveragesummary WHERE buildid='$buildid'");
        while ($coverage_array = pdo_fetch_array($coverages)) {
            $coverage_response = array();
            $coverage_response['buildid'] = $build_array["id"];

            $percent = round(
                    compute_percentcoverage($coverage_array['loctested'],
                        $coverage_array['locuntested']), 2);

            $coverage_response['percentage'] = $percent;
            $coverage_response['locuntested'] = intval($coverage_array["locuntested"]);
            $coverage_response['loctested'] = intval($coverage_array["loctested"]);

            // Compute the diff
            $coveragediff = pdo_query("SELECT * FROM coveragesummarydiff WHERE buildid='$buildid'");
            if (pdo_num_rows($coveragediff) >0) {
                $coveragediff_array = pdo_fetch_array($coveragediff);
                $loctesteddiff = $coveragediff_array['loctested'];
                $locuntesteddiff = $coveragediff_array['locuntested'];
                @$previouspercent =
                    round(($coverage_array["loctested"] - $loctesteddiff) /
                            ($coverage_array["loctested"] - $loctesteddiff +
                             $coverage_array["locuntested"] - $locuntesteddiff)
                            * 100, 2);
                $percentdiff = round($percent - $previouspercent, 2);
                $coverage_response['percentagediff'] = $percentdiff;
                $coverage_response['locuntesteddiff'] = $locuntesteddiff;
                $coverage_response['loctesteddiff'] = $loctesteddiff;
            }



            // Are there labels for this build?
            //
            $coverage_response['label'] = $build_label;

            $coverage_response['site'] = $build_array["sitename"];
            $coverage_response['buildname'] = $build_array["name"];
            $response['coverages'][] = $coverage_response;

        }  // end coverage

        $coverageThreshold = $project_array['coveragethreshold'];
        $response['thresholdgreen'] = $coverageThreshold;
        $response['thresholdyellow'] = $coverageThreshold * 0.7;
    } // end looping through builds


    // Summarize coverage by subproject groups.
    // This happens when we have subprojects and we're looking at the children
    // of a specific build.
    $coverage_groups = array();
    if ($project_instance->GetNumberOfSubProjects($end_UTCDate) > 0) {
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
            $coverage_groups[$groupId]['buildname'] = '';
            $coverage_groups[$groupId]['coverages'] = array();
        }
    }

    $response['numberbuilds'] = count($response['coverages']) + 1;
    $response['buildnames'] = array();

    foreach( $response['coverages'] as $coverage)
    {
      $response['buildnames'][] = 'build'.$coverage['buildid'];
    }
    $response['buildnames'][] = 'aggregate';

    // First create an empty coveragegroups structure
    $coverage_labels = array();
    $coveragegroups = array();
    if ($project_instance->GetNumberOfSubProjects($end_UTCDate) > 0) {
        $groups = $project_instance->GetSubProjectGroups();
        foreach ($groups as $group) {
            // Keep track of coverage info on a per-group basis.
            $groupId = $group->GetId();

            $coveragegroups[$groupId] = array();
            $coverageThreshold = $group->GetCoverageThreshold();
            $coveragegroups[$groupId]['thresholdgreen'] = $coverageThreshold;
            $coveragegroups[$groupId]['thresholdyellow'] = $coverageThreshold * 0.7;
            $coveragegroups[$groupId]['coverages'] = array();

            foreach( $response['buildnames'] as $buildname)
            {
              $coveragegroups[$groupId][$buildname] = -1;
            }
            $coveragegroups[$groupId]['label'] = $group->GetName();;
            $coveragegroups[$groupId]['aggregate'] = -1;
            $coverage_labels[$groupId] = array();
        }
    }

    $_coveragegroups = array();
    foreach( $response['coverages'] as $coverage)
    {
      $buildid = $coverage['buildid'];
      // Get the coverage data for each build
      $groups = get_coverage_groups($projectid, $beginning_UTCDate, $end_UTCDate, 
                                    $filterdata, $num_selected_subprojects,
                                    $buildid, $filter_sql, $limit_sql,
                                    $buildgroups_response, $coverage_groups);

      // Make an entry in coverage groups for each possible subproject
      foreach ($groups as $group) {
        $groupId = $group['id'];
        foreach ($group['coverages'] as $coverage) {
          $coverage_label = $coverage['label'];
          if (!in_array($coverage_label, $coverage_labels[$groupId])) {
            $coverage_labels[$groupId][] = $coverage_label;
            $a = array();
            $a['label'] = $coverage_label;
            foreach($response['buildnames'] as $buildname)
            {
              $a[$buildname] = -1;
            }
            $coveragegroups[$groupId]['coverages'][] = $a;
          }
        }
      }
      $_coveragegroups[] = $groups;
    }

    // Populate coveragegroups
    foreach ($_coveragegroups as $_coveragegroup) { 
      foreach ($_coveragegroup as $group) { 
        $parentid = $group['parentid'];
        $groupId = $group['id'];

        $coveragegroups[$groupId]['build'.$parentid] = $group['percentage'];
        $coveragegroups[$groupId]['aggregate'] = max($coveragegroups[$groupId]['aggregate'], $group['percentage']);
        $coveragegroups[$groupId]['label'] = $group['label'];

        foreach ($group['coverages'] as $coverage) {
          $coverage_label = $coverage['label'];

          foreach ($coveragegroups[$groupId]['coverages'] as $key => $__coverage) {
            if ($__coverage['label'] == $coverage_label)
            {
              $coveragegroups[$groupId]['coverages'][$key]['build'.$parentid] = $coverage['percentage'];
              $coveragegroups[$groupId]['coverages'][$key]['build'.$parentid.'id'] = $coverage['buildid'];
              if (array_key_exists('percentagediff', $coverage)) {
                $percentagediff = $coverage['percentagediff'];
              }
              else {
                $percentagediff = null;
              }
              $coveragegroups[$groupId]['coverages'][$key]['build'.$parentid.'percentagediff'] = $percentagediff;
              $coveragegroups[$groupId]['coverages'][$key]['aggregate'] = max($coveragegroups[$groupId]['coverages'][$key]['aggregate'], $coverage['percentage']);
              break;
            }
          }
        }
      }
    }

    // At this point it is safe to remove any empty $coveragegroups from our response.
    function is_coveragegroup_nonempty($group)
    {
      return !empty($group['coverages']);
    }
    $coveragegroups_response =
        array_filter($coveragegroups, "is_coveragegroup_nonempty");

    // Report coveragegroups as a list, not an associative array.
    $coveragegroups_response = array_values($coveragegroups_response);

    $response['coveragegroups'] = $coveragegroups_response;


    $end = microtime_float();
    $response['generationtime'] = round($end-$start, 3);

    echo json_encode(cast_data_for_JSON($response));
} // end echo_main_dashboard_JSON


function get_coverage_groups($projectid, $beginning_UTCDate, $end_UTCDate, 
                            $filterdata, $num_selected_subprojects,
                            $parentid, $filter_sql, $limit_sql, 
                            $buildgroups_response, $coverage_groups)
{

   $build_data = get_build_data($projectid, $beginning_UTCDate, $end_UTCDate,
                                $filterdata, $parentid, $filter_sql, $limit_sql);

    // Generate the JSON response from the rows of builds.

    foreach ($build_data as $build_array) {
        $groupid = $build_array["groupid"];

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

        $buildid = $build_array["id"];
        $siteid = $build_array["siteid"];

        // Figure out how many labels to report for this build.
        if (!array_key_exists('numlabels', $build_array) ||
                $build_array['numlabels'] == 0) {
            $num_labels = 0;
        } else {
            $num_labels = $build_array['numlabels'];
        }

        $label_query =
            "SELECT l.text FROM label AS l
            INNER JOIN label2build AS l2b ON (l.id=l2b.labelid)
            INNER JOIN build AS b ON (l2b.buildid=b.id)
            WHERE b.id=" . qnum($buildid);

        if ($num_selected_subprojects > 0) {
            // Special handling for whitelisting/blacklisting SubProjects.
            if ($include_subprojects) {
                $num_labels = 0;
            }
            $labels_result = pdo_query($label_query);
            while ($label_row = pdo_fetch_array($labels_result)) {
                // Whitelist case
                if ($include_subprojects &&
                        in_array($label_row['text'], $included_subprojects)) {
                    $num_labels++;
                }
                // Blacklist case
                if ($exclude_subprojects &&
                        in_array($label_row['text'], $excluded_subprojects)) {
                    $num_labels--;
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
            $build_label = "(none)";
        } elseif ($num_labels == 1) {
            // If exactly one label for this build, look it up here.
            $label_result = pdo_single_row_query($label_query);
            $build_label = $label_result['text'];
        } else {
            // More than one label, just report the number.
            $build_label = "($num_labels labels)";
        }

        // Coverage
        //
        $coverageIsGrouped = false;
        $coverages = pdo_query("SELECT * FROM coveragesummary WHERE buildid='$buildid'");
        while ($coverage_array = pdo_fetch_array($coverages)) {
            $coverage_response = array();
            $coverage_response['buildid'] = $build_array["id"];


            $percent = round(
                    compute_percentcoverage($coverage_array['loctested'],
                        $coverage_array['locuntested']), 2);

            if ($build_array['subprojectgroup']) {
                $groupId = $build_array['subprojectgroup'];
                if (array_key_exists($groupId, $coverage_groups)) {
                    $coverageIsGrouped = true;
                    $coverageThreshold = $coverage_groups[$groupId]['thresholdgreen'];
                    $coverage_groups[$groupId]['loctested'] +=
                        $coverage_array['loctested'];
                    $coverage_groups[$groupId]['locuntested'] +=
                        $coverage_array['locuntested'];
                }
            }

            $coverage_response['percentage'] = $percent;
            $coverage_response['locuntested'] = intval($coverage_array["locuntested"]);
            $coverage_response['loctested'] = intval($coverage_array["loctested"]);

            // Compute the diff
            $coveragediff = pdo_query("SELECT * FROM coveragesummarydiff WHERE buildid='$buildid'");
            if (pdo_num_rows($coveragediff) >0) {
                $coveragediff_array = pdo_fetch_array($coveragediff);
                $loctesteddiff = $coveragediff_array['loctested'];
                $locuntesteddiff = $coveragediff_array['locuntested'];
                @$previouspercent =
                    round(($coverage_array["loctested"] - $loctesteddiff) /
                            ($coverage_array["loctested"] - $loctesteddiff +
                             $coverage_array["locuntested"] - $locuntesteddiff)
                            * 100, 2);
                $percentdiff = round($percent - $previouspercent, 2);
                $coverage_response['percentagediff'] = $percentdiff;
                $coverage_response['locuntesteddiff'] = $locuntesteddiff;
                $coverage_response['loctesteddiff'] = $loctesteddiff;
            }

            // Are there labels for this build?
            //
            $coverage_response['label'] = $build_label;


            if ($coverageIsGrouped) {
              $coverage_groups[$groupId]['coverages'][] = $coverage_response;
            }
        }  // end coverage
    } // end looping through builds


    // Generate coverage by group here.
    if (!empty($coverage_groups)) {
        $response_coveragegroups = array();
        foreach ($coverage_groups as $groupid => $group) {
            $loctested = $group['loctested'];
            $locuntested = $group['locuntested'];
            if ($loctested == 0 && $locuntested == 0) {
                continue;
            }
            $percentage = round($loctested / ($loctested + $locuntested) * 100, 2);
            $group['percentage'] = $percentage;
            $group['id'] = $groupid;
            $group['parentid'] = $parentid;

            $response_coveragegroups[] = $group;
        }
        return $response_coveragegroups;
    }

    return [];
}

function get_build_data($projectid, $beginning_UTCDate, $end_UTCDate, $filterdata,
                          $parentid, $filter_sql, $limit_sql)
{
    // Use this as the default date clause, but if $filterdata has a date clause,
    // then cancel this one out:
    //
    $date_clause = "AND b.starttime<'$end_UTCDate' AND b.starttime>='$beginning_UTCDate' ";

    if ($filterdata['hasdateclause']) {
        $date_clause = '';
    }

    $parent_clause = "";
    if (isset($parentid)) {
        // If we have a parentid, then we should only show children of that build.
        // Date becomes irrelevant in this case.
        $parent_clause ="AND (b.parentid = " . qnum($parentid) . ") ";
        $date_clause = "";
    } else {
        // Only show builds that are not children.
        $parent_clause ="AND (b.parentid = -1 OR b.parentid = 0) ";
    }

    $sql =  "SELECT b.id,b.siteid,b.parentid,b.done,
        bu.status AS updatestatus,
        i.osname AS osname,
        bu.starttime AS updatestarttime,
        bu.endtime AS updateendtime,
        bu.nfiles AS countupdatefiles,
        bu.warnings AS countupdatewarnings,
        b.configureduration,
        be_diff.difference_positive AS countbuilderrordiffp,
        be_diff.difference_negative AS countbuilderrordiffn,
        bw_diff.difference_positive AS countbuildwarningdiffp,
        bw_diff.difference_negative AS countbuildwarningdiffn,
        ce_diff.difference AS countconfigurewarningdiff,
        btt.time AS testduration,
        tnotrun_diff.difference_positive AS counttestsnotrundiffp,
        tnotrun_diff.difference_negative AS counttestsnotrundiffn,
        tfailed_diff.difference_positive AS counttestsfaileddiffp,
        tfailed_diff.difference_negative AS counttestsfaileddiffn,
        tpassed_diff.difference_positive AS counttestspasseddiffp,
        tpassed_diff.difference_negative AS counttestspasseddiffn,
        tstatusfailed_diff.difference_positive AS countteststimestatusfaileddiffp,
        tstatusfailed_diff.difference_negative AS countteststimestatusfaileddiffn,
        (SELECT count(buildid) FROM build2note WHERE buildid=b.id)  AS countnotes,
        (SELECT count(buildid) FROM buildnote WHERE buildid=b.id) AS countbuildnotes,
            s.name AS sitename,
        s.outoforder AS siteoutoforder,
        b.stamp,b.name,b.type,b.generator,b.starttime,b.endtime,b.submittime,
        b.configureerrors AS countconfigureerrors,
        b.configurewarnings AS countconfigurewarnings,
        b.builderrors AS countbuilderrors,
        b.buildwarnings AS countbuildwarnings,
        b.testnotrun AS counttestsnotrun,
        b.testfailed AS counttestsfailed,
        b.testpassed AS counttestspassed,
        b.testtimestatusfailed AS countteststimestatusfailed,
        sp.id AS subprojectid,
        sp.groupid AS subprojectgroup,
        g.name AS groupname,gp.position,g.id AS groupid,
        (SELECT count(buildid) FROM label2build WHERE buildid=b.id) AS numlabels,
        (SELECT count(buildid) FROM errorlog WHERE buildid=b.id) AS nerrorlog,
        (SELECT count(buildid) FROM build2uploadfile WHERE buildid=b.id) AS builduploadfiles
            FROM build AS b
            LEFT JOIN build2group AS b2g ON (b2g.buildid=b.id)
            LEFT JOIN buildgroup AS g ON (g.id=b2g.groupid)
            LEFT JOIN buildgroupposition AS gp ON (gp.buildgroupid=g.id)
            LEFT JOIN site AS s ON (s.id=b.siteid)
            LEFT JOIN build2update AS b2u ON (b2u.buildid=b.id)
            LEFT JOIN buildupdate AS bu ON (b2u.updateid=bu.id)
            LEFT JOIN buildinformation AS i ON (i.buildid=b.id)
            LEFT JOIN builderrordiff AS be_diff ON (be_diff.buildid=b.id AND be_diff.type=0)
            LEFT JOIN builderrordiff AS bw_diff ON (bw_diff.buildid=b.id AND bw_diff.type=1)
            LEFT JOIN configureerrordiff AS ce_diff ON (ce_diff.buildid=b.id AND ce_diff.type=1)
            LEFT JOIN buildtesttime AS btt ON (btt.buildid=b.id)
            LEFT JOIN testdiff AS tnotrun_diff ON (tnotrun_diff.buildid=b.id AND tnotrun_diff.type=0)
            LEFT JOIN testdiff AS tfailed_diff ON (tfailed_diff.buildid=b.id AND tfailed_diff.type=1)
            LEFT JOIN testdiff AS tpassed_diff ON (tpassed_diff.buildid=b.id AND tpassed_diff.type=2)
            LEFT JOIN testdiff AS tstatusfailed_diff ON (tstatusfailed_diff.buildid=b.id AND tstatusfailed_diff.type=3)
            LEFT JOIN subproject2build AS sp2b ON (sp2b.buildid = b.id)
            LEFT JOIN subproject as sp ON (sp2b.subprojectid = sp.id)
            WHERE b.projectid='$projectid' AND g.type='Daily'
            $parent_clause $date_clause
            ".$filter_sql." ".$limit_sql;

    // We shouldn't get any builds for group that have been deleted (otherwise something is wrong)
    $builds = pdo_query($sql);

    // Log any errors
    $pdo_error = pdo_error();
    if (strlen($pdo_error)>0) {
        add_log("SQL error: ".$pdo_error, "Index.php", LOG_ERR);
    }

    // Gather up results from this query.
    $build_data = array();
    while ($build_row = pdo_fetch_array($builds)) {
        $build_data[] = $build_row;
    }

    return $build_data;
}





