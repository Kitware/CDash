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

// Redirect to the previous version of api/index.php if it seems like
// that's what the user wants.
if (isset($_GET['method'])) {
    require __DIR__ . '/index_old.php';
    exit(0);
}

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
    include_once("models/subproject.php");

    $response = array();

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
        $svnurl = make_cdash_url(htmlentities($project_array["cvsurl"]));
        $homeurl = make_cdash_url(htmlentities($project_array["homeurl"]));
        $bugurl = make_cdash_url(htmlentities($project_array["bugtrackerurl"]));
        $googletracker = htmlentities($project_array["googletracker"]);
        $docurl = make_cdash_url(htmlentities($project_array["documentationurl"]));
        $projectpublic =  $project_array["public"];
        $projectname = $project_array["name"];

        if (isset($project_array['testingdataurl']) && $project_array['testingdataurl'] != '') {
            $testingdataurl = make_cdash_url(htmlentities($project_array['testingdataurl']));
        }
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

    $response = begin_JSON_response();
    $response['title'] = "CDash - $projectname";
    $response['feed'] = $CDASH_ENABLE_FEED;

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

    list($previousdate, $currentstarttime, $nextdate) = get_dates($date, $project_array["nightlytime"]);
    $logoid = getLogoID($projectid);

    // Main dashboard section
    $response['datetime'] = date("l, F d Y H:i:s T", time());
    $response['date'] = $date;
    $response['unixtimestamp'] = $currentstarttime;
    $response['vcs'] = $svnurl;
    $response['bugtracker'] = $bugurl;
    $response['googletracker'] = $googletracker;
    $response['documentation'] = $docurl;
    $response['logoid'] = $logoid;
    $response['projectid'] = $projectid;
    $response['projectname'] = $projectname;
    $response['projectname_encoded'] = urlencode($projectname);
    $response['previousdate'] = $previousdate;
    $response['public'] = $projectpublic;
    $response['displaylabels'] = $project_array["displaylabels"];
    $response['nextdate'] = $nextdate;

    if (empty($project_array["homeurl"])) {
        $response['home'] = "index.php?project=".urlencode($projectname);
    } else {
        $response['home'] = $homeurl;
    }

    $page_id = 'index.php';
    $response['childview'] = 0;

    if ($CDASH_USE_LOCAL_DIRECTORY && file_exists("local/models/proProject.php")) {
        include_once("local/models/proProject.php");
        $pro= new proProject;
        $pro->ProjectId=$projectid;
        $response['proedition'] = $pro->GetEdition(1);
    }

    if ($currentstarttime>time()) {
        $response['future'] = 1;
    } else {
        $response['future'] = 0;
    }

    // Menu definition
    $response['menu'] = array();
    $beginning_timestamp = $currentstarttime;
    $end_timestamp = $currentstarttime+3600*24;
    $beginning_UTCDate = gmdate(FMT_DATETIME, $beginning_timestamp);
    $end_UTCDate = gmdate(FMT_DATETIME, $end_timestamp);
    if ($project_instance->GetNumberOfSubProjects($end_UTCDate) > 0) {
        $response['menu']['subprojects'] = 1;
    }

    if (isset($_GET['parentid'])) {
        $parentid = pdo_real_escape_numeric($_GET['parentid']);
        $page_id = 'indexchildren.php';
        $response['childview'] = 1;

        // When a parentid is specified, we should link to the next build,
        // not the next day.
        include_once("models/build.php");
        $build = new Build();
        $build->Id = $parentid;
        $previous_buildid = $build->GetPreviousBuildId();
        $current_buildid = $build->GetCurrentBuildId();
        $next_buildid = $build->GetNextBuildId();

        $base_url = "index.php?project=".urlencode($projectname);
        if ($previous_buildid > 0) {
            $response['menu']['previous'] = "$base_url&parentid=$previous_buildid";
        } else {
            $response['menu']['noprevious'] = "1";
        }

        $response['menu']['current'] = "$base_url&parentid=$current_buildid";

        if ($next_buildid > 0) {
            $response['menu']['next'] = "$base_url&parentid=$next_buildid";
        } else {
            $response['menu']['nonext'] = "1";
        }
    } elseif (!has_next_date($date, $currentstarttime)) {
        $response['menu']['nonext'] = 1;
    }

    // Check if a SubProject parameter was specified.
    $subproject_name = @$_GET["subproject"];
    $subprojectid = false;
    if ($subproject_name) {
        $SubProject = new SubProject();
        $subproject_name = htmlspecialchars(pdo_real_escape_string($subproject_name));
        $SubProject->SetName($subproject_name);
        $SubProject->SetProjectId($projectid);
        $subprojectid = $SubProject->GetId();

        if ($subprojectid) {
            // Add an extra URL argument for the menu
            $response['extraurl'] = "&subproject=".urlencode($subproject_name);
            $response['subprojectname'] = $subproject_name;

            $subproject_response = array();
            $subproject_response['name'] =  $SubProject->GetName();

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
                        $dependency_response['lastsubmission'] = "NA";
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
    $updates_response['url'] = "viewChanges.php?project=".urlencode($projectname)."&amp;date=".$gmdate;

    $dailyupdate = pdo_query("SELECT count(ds.dailyupdateid),count(distinct ds.author)
            FROM dailyupdate AS d LEFT JOIN dailyupdatefile AS ds ON (ds.dailyupdateid = d.id)
            WHERE d.date='$gmdate' and d.projectid='$projectid' GROUP BY ds.dailyupdateid");

    if (pdo_num_rows($dailyupdate)>0) {
        $dailupdate_array = pdo_fetch_array($dailyupdate);
        $updates_response['nchanges'] = $dailupdate_array[0];
        $updates_response['nauthors'] = $dailupdate_array[1];
    } else {
        $updates_response['nchanges'] = -1;
    }
    $updates_response['timestamp'] = date("l, F d Y - H:i T", $currentstarttime);
    $response['updates'] = $updates_response;

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
        $buildgroup_response['linkname'] = str_replace(" ", "_", $groupname);
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
        $limit_sql = ' LIMIT '.$filterdata['limit'];
    }
    unset($filterdata['xml']);
    $response['filterdata'] = $filterdata;
    $response['filterurl'] = @$_GET['filterstring'];

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

    // add a request for the subproject
    $subprojectsql = "";
    if ($subproject_name && is_numeric($subprojectid)) {
        $subprojectsql = " AND sp2b.subprojectid=".$subprojectid;
    }

    // Use this as the default date clause, but if $filterdata has a date clause,
    // then cancel this one out:
    //
    $date_clause = "AND b.starttime<'$end_UTCDate' AND b.starttime>='$beginning_UTCDate' ";

    if ($filterdata['hasdateclause']) {
        $date_clause = '';
    }

    $parent_clause = "";
    if (isset($_GET["parentid"])) {
        // If we have a parentid, then we should only show children of that build.
        // Date becomes irrelevant in this case.
        $parent_clause ="AND (b.parentid = " . qnum($_GET["parentid"]) . ") ";
        $date_clause = "";
    } elseif (empty($subprojectsql)) {
        // Only show builds that are not children.
        $parent_clause ="AND (b.parentid = -1 OR b.parentid = 0) ";
    }

    $build_rows = array();

    // If the user is logged in we display if the build has some changes for him
    $userupdatesql = "";
    if (isset($_SESSION['cdash'])) {
        $userupdatesql = "(SELECT count(updatefile.updateid) FROM updatefile,build2update,user2project,
            user2repository
                WHERE build2update.buildid=b.id
                AND build2update.updateid=updatefile.updateid
                AND user2project.projectid=b.projectid
                AND user2project.userid='".$_SESSION['cdash']['loginid']."'
                AND user2repository.userid=user2project.userid
                AND (user2repository.projectid=0 OR user2repository.projectid=b.projectid)
                AND user2repository.credential=updatefile.author) AS userupdates,";
    }


    // Postgres differs from MySQL on how to aggregate results
    // into a single column.
    $label_sql = "";
    $groupby_sql = "";
    if ($CDASH_DB_TYPE != 'pgsql') {
        $label_sql = "GROUP_CONCAT(l.text SEPARATOR ', ') AS labels,";
        $groupby_sql = " GROUP BY b.id";
    }

    $sql =  "SELECT b.id,b.siteid,b.parentid,
        bu.status AS updatestatus,
        i.osname AS osname,
        bu.starttime AS updatestarttime,
        bu.endtime AS updateendtime,
        bu.nfiles AS countupdatefiles,
        bu.warnings AS countupdatewarnings,
        c.status AS configurestatus,
        c.starttime AS configurestarttime,
        c.endtime AS configureendtime,
        be_diff.difference_positive AS countbuilderrordiffp,
        be_diff.difference_negative AS countbuilderrordiffn,
        bw_diff.difference_positive AS countbuildwarningdiffp,
        bw_diff.difference_negative AS countbuildwarningdiffn,
        ce_diff.difference AS countconfigurewarningdiff,
        btt.time AS testsduration,
        tnotrun_diff.difference_positive AS counttestsnotrundiffp,
        tnotrun_diff.difference_negative AS counttestsnotrundiffn,
        tfailed_diff.difference_positive AS counttestsfaileddiffp,
        tfailed_diff.difference_negative AS counttestsfaileddiffn,
        tpassed_diff.difference_positive AS counttestspasseddiffp,
        tpassed_diff.difference_negative AS counttestspasseddiffn,
        tstatusfailed_diff.difference_positive AS countteststimestatusfaileddiffp,
        tstatusfailed_diff.difference_negative AS countteststimestatusfaileddiffn,
        (SELECT count(buildid) FROM build2note WHERE buildid=b.id)  AS countnotes,
        (SELECT count(buildid) FROM buildnote WHERE buildid=b.id) AS countbuildnotes,"
            .$userupdatesql."
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
        $label_sql
            (SELECT count(buildid) FROM errorlog WHERE buildid=b.id) AS nerrorlog,
        (SELECT count(buildid) FROM build2uploadfile WHERE buildid=b.id) AS builduploadfiles
            FROM build AS b
            LEFT JOIN build2group AS b2g ON (b2g.buildid=b.id)
            LEFT JOIN buildgroup AS g ON (g.id=b2g.groupid)
            LEFT JOIN buildgroupposition AS gp ON (gp.buildgroupid=g.id)
            LEFT JOIN site AS s ON (s.id=b.siteid)
            LEFT JOIN build2update AS b2u ON (b2u.buildid=b.id)
            LEFT JOIN buildupdate AS bu ON (b2u.updateid=bu.id)
            LEFT JOIN configure AS c ON (c.buildid=b.id)
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
            LEFT JOIN label2build AS l2b ON (l2b.buildid = b.id)
            LEFT JOIN label AS l ON (l.id = l2b.labelid)
            WHERE b.projectid='$projectid' AND g.type='Daily'
            $parent_clause $date_clause
            ".$subprojectsql." ".$filter_sql." ".$groupby_sql
            .$limit_sql;

    // We shouldn't get any builds for group that have been deleted (otherwise something is wrong)
    $builds = pdo_query($sql);
    echo pdo_error();

    // Gather up results from this query.
    $build_data = array();
    while ($build_row = pdo_fetch_array($builds)) {
        $build_data[] = $build_row;
    }
    $dynamic_builds = array();
    if (empty($filter_sql)) {
        $dynamic_builds = get_dynamic_builds($projectid);
        $build_data = array_merge($build_data, $dynamic_builds);
    }

    // Check if we need to summarize coverage by subproject groups.
    // This happens when we have subprojects and we're looking at the children
    // of a specific build.
    $coverage_groups = array();
    if (isset($_GET["parentid"]) && $_GET["parentid"] > 0 &&
            $project_instance->GetNumberOfSubProjects($end_UTCDate) > 0) {
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
            $coverage_groups[$groupId]['coverages'] = array();
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
        //  countbuildwarnings
        //  countbuilderrors
        //  countbuilderrordiff
        //  countbuildwarningdiff
        //  configurestatus
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
        //  testsduration
        //
        // Fields that we add within this loop:
        //  maxstarttime
        //  buildids (array of buildids for summary rows)
        //  countbuildnotes (added by users)
        //  labels
        //  updateduration
        //  countupdateerrors
        //  hasconfigurestatus
        //  configureduration
        //  test
        //

        $buildid = $build_row['id'];
        $groupid = $build_row['groupid'];
        $siteid = $build_row['siteid'];
        $parentid = $build_row['parentid'];

        $build_row['buildids'][] = $buildid;
        $build_row['maxstarttime'] = $build_row['starttime'];

        // Split out labels
        if (empty($build_row['labels'])) {
            $build_row['labels'] = array();
        } else {
            $build_row['labels'] = explode(",", $build_row['labels']);
        }

        // Updates
        if (!empty($build_row['updatestarttime'])) {
            $build_row['updateduration'] = round((strtotime($build_row['updateendtime'])-strtotime($build_row['updatestarttime']))/60, 1);
        } else {
            $build_row['updateduration'] = 0;
        }


        if (strlen($build_row["updatestatus"]) > 0 &&
                $build_row["updatestatus"]!="0") {
            $build_row['countupdateerrors'] = 1;
        } else {
            $build_row['countupdateerrors'] = 0;
        }

        $build_row['buildduration'] = round((strtotime($build_row['endtime'])-strtotime($build_row['starttime']))/60, 1);

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

        $hasconfiguredata = $build_row['countconfigureerrors'] != -1 ||
            $build_row['countconfigurewarnings'] != -1;

        if ($build_row['countconfigureerrors'] < 0) {
            $build_row['countconfigureerrors'] = 0;
        }
        if ($build_row['countconfigurewarnings'] < 0) {
            $build_row['countconfigurewarnings'] = 0;
        }

        $build_row['hasconfigurestatus'] = 0;
        $build_row['configureduration'] = 0;

        if (strlen($build_row['configurestatus'])>0) {
            $build_row['hasconfigurestatus'] = 1;
            $build_row['configureduration'] = round((strtotime($build_row["configureendtime"])-strtotime($build_row["configurestarttime"]))/60, 1);
        }

        if (empty($build_row['countconfigurewarningdiff'])) {
            $build_row['countconfigurewarningdiff'] = 0;
        }

        $build_row['hastest'] = 0;
        if ($build_row['counttestsfailed']!=-1) {
            $build_row['hastest'] = 1;
        }

        if (empty($build_row['testsduration'])) {
            $time_array = pdo_fetch_array(pdo_query("SELECT SUM(time) FROM build2test WHERE buildid='$buildid'"));
            $build_row['testsduration'] = round($time_array[0]/60, 1);
        } else {
            $build_row['testsduration'] = round($build_row['testsduration'], 1); //already in minutes
        }

        $build_rows[] = $build_row;
    }

    // Generate the JSON response from the rows of builds.
    $response['coverages'] = array();
    $response['dynamicanalyses'] = array();
    foreach ($build_rows as $build_array) {
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

        $build_response = array();

        $received_builds[$groupname][] =
            $build_array["sitename"]."_".$build_array["name"];

        $buildid = $build_array["id"];
        $siteid = $build_array["siteid"];

        $countChildrenResult = pdo_single_row_query(
                "SELECT count(id) AS numchildren
                FROM build WHERE parentid=".qnum($buildid));
        $numchildren = $countChildrenResult['numchildren'];
        $build_response['numchildren'] = $numchildren;
        $child_builds_hyperlink = "";

        $selected_configure_errors = 0;
        $selected_configure_warnings = 0;
        $selected_build_errors = 0;
        $selected_build_warnings = 0;
        $selected_tests_not_run = 0;
        $selected_tests_failed = 0;
        $selected_tests_passed = 0;

        if ($numchildren > 0) {
            $child_builds_hyperlink =
                get_child_builds_hyperlink($build_array["id"], $filterdata);
            $build_response['multiplebuildshyperlink'] = $child_builds_hyperlink;
            $buildgroups_response[$i]['hasparentbuilds'] = true;

            // Compute selected (excluded or included) SubProject results.
            if ($selected_subprojects) {
                $select_query = "
                    SELECT configureerrors, configurewarnings, builderrors,
                           buildwarnings, testnotrun, testfailed, testpassed,
                           sb.name
                    FROM build AS b
                    INNER JOIN subproject2build AS sb2b ON (b.id = sb2b.buildid)
                    INNER JOIN subproject AS sb ON (sb2b.subprojectid = sb.id)
                    WHERE b.parentid=$buildid
                    AND sb.name IN $selected_subprojects";
                $select_results = pdo_query($select_query);
                while ($select_array = pdo_fetch_array($select_results)) {
                    $selected_configure_errors +=
                        max(0, $select_array['configureerrors']);
                    $selected_configure_warnings +=
                        max(0, $select_array['configurewarnings']);
                    $selected_build_errors +=
                        max(0, $select_array['builderrors']);
                    $selected_build_warnings +=
                        max(0, $select_array['buildwarnings']);
                    $selected_tests_not_run +=
                        max(0, $select_array['testnotrun']);
                    $selected_tests_failed +=
                        max(0, $select_array['testfailed']);
                    $selected_tests_passed +=
                        max(0, $select_array['testpassed']);
                }
            }
        } else {
            $buildgroups_response[$i]['hasnormalbuilds'] = true;
        }

        if (strtolower($build_array["type"]) == 'continuous') {
            $buildgroups_response[$i]['sorttype'] = 'time';
        }

        // Attempt to determine the platform based on the OSName and the buildname
        $buildplatform = '';
        if (strtolower(substr($build_array["osname"], 0, 7)) == 'windows') {
            $buildplatform='windows';
        } elseif (strtolower(substr($build_array["osname"], 0, 8)) == 'mac os x') {
            $buildplatform='mac';
        } elseif (strtolower(substr($build_array["osname"], 0, 5)) == 'linux'
                || strtolower(substr($build_array["osname"], 0, 3)) == 'aix'
                ) {
            $buildplatform='linux';
        } elseif (strtolower(substr($build_array["osname"], 0, 7)) == 'freebsd') {
            $buildplatform='freebsd';
        } elseif (strtolower(substr($build_array["osname"], 0, 3)) == 'gnu') {
            $buildplatform='gnu';
        }

        if (isset($_GET["parentid"])) {
            if (empty($site_response)) {
                $site_response['site'] =  $build_array["sitename"];
                $site_response['siteoutoforder'] =  $build_array["siteoutoforder"];
                $site_response['siteid'] =  $siteid;
                $site_response['buildname'] =  $build_array["name"];
                $site_response['buildplatform'] = $buildplatform;
                $site_response['generator'] = $build_array["generator"];
            }
        } else {
            $build_response['site'] =  $build_array["sitename"];
            $build_response['siteoutoforder'] =  $build_array["siteoutoforder"];
            $build_response['siteid'] =  $siteid;
            $build_response['buildname'] =  $build_array["name"];
            $build_response['buildplatform'] = $buildplatform;
        }

        if (isset($build_array["userupdates"])) {
            $build_response['userupdates'] =  $build_array["userupdates"];
        }
        $build_response['id'] = $build_array["id"];
        $build_response['uploadfilecount'] = $build_array["builduploadfiles"];

        if ($build_array['countbuildnotes']>0) {
            $build_response['buildnote'] = 1;
        }

        if ($build_array['countnotes'] > 0) {
            $build_response['note'] = 1;
        }

        // Are there labels for this build?
        //
        $labels_array = $build_array['labels'];
        if (empty($labels_array)) {
            $build_response['label'] = "(none)";
        } else {
            if ($include_subprojects) {
                $num_labels = $num_selected_subprojects;
            } else {
                $num_labels = count($labels_array) - $num_selected_subprojects;
            }
            if ($num_labels == 1) {
                if ($include_subprojects) {
                    $build_response['label'] = $included_subprojects[0];
                } else {
                    $build_response['label'] = $labels_array[0];
                }
            } else {
                $build_response['label'] = "($num_labels labels)";
            }
        }

        $update_response = array();

        $countupdatefiles = $build_array['countupdatefiles'];
        $update_response['files'] =  $countupdatefiles;
        $buildgroups_response[$i]['numupdatedfiles'] += $countupdatefiles;

        if (!empty($build_array['updatestarttime'])) {
            $update_response['defined'] = 1;

            if ($build_array['countupdateerrors']>0) {
                $update_response['errors'] =  1;
                $buildgroups_response[$i]['numupdateerror'] += 1;
            } else {
                $update_response['errors'] =  0;

                if ($build_array['countupdatewarnings']>0) {
                    $update_response['warning'] =  1;
                    $buildgroups_response[$i]['numupdatewarning'] += 1;
                }
            }

            $duration = $build_array['updateduration'];
            $update_response['time'] =  time_difference($duration*60.0, true);
            $update_response['timefull'] = $duration;
            $buildgroups_response[$i]['updateduration'] += $duration;
            $buildgroups_response[$i]['hasupdatedata'] = true;
            $build_response['update'] = $update_response;
        } // end if we have an update

        $compilation_response = array();

        if ($build_array['countbuilderrors']>=0) {
            if ($include_subprojects) {
                $nerrors = $selected_build_errors;
            } else {
                $nerrors =
                    $build_array['countbuilderrors'] - $selected_build_errors;
            }
            $compilation_response['error'] =  $nerrors;
            $buildgroups_response[$i]['numbuilderror'] += $nerrors;

            if ($include_subprojects) {
                $nwarnings = $selected_build_warnings;
            } else {
                $nwarnings = $build_array['countbuildwarnings'] -
                    $selected_build_warnings;
            }
            $compilation_response['warning'] = $nwarnings;
            $buildgroups_response[$i]['numbuildwarning'] += $nwarnings;

            $duration = $build_array['buildduration'];
            $compilation_response['time'] = time_difference($duration*60.0, true);
            $compilation_response['timefull'] = $duration;

            $diff = $build_array['countbuilderrordiffp'];
            if ($diff!=0) {
                $compilation_response['nerrordiffp'] =  $diff;
            }
            $diff = $build_array['countbuilderrordiffn'];
            if ($diff!=0) {
                $compilation_response['nerrordiffn'] =  $diff;
            }

            $diff = $build_array['countbuildwarningdiffp'];
            if ($diff!=0) {
                $compilation_response['nwarningdiffp'] =  $diff;
            }
            $diff = $build_array['countbuildwarningdiffn'];
            if ($diff!=0) {
                $compilation_response['nwarningdiffn'] =  $diff;
            }
        }
        if (!empty($compilation_response)) {
            $build_response['compilation'] = $compilation_response;
            $buildgroups_response[$i]['hascompilationdata'] = true;
        }

        $configure_response = array();

        if ($include_subprojects) {
            $nconfigureerrors = $selected_configure_errors;
        } else {
            $nconfigureerrors = $build_array['countconfigureerrors'] -
                $selected_configure_errors;
        }
        $configure_response['error'] = $nconfigureerrors;
        $buildgroups_response[$i]['numconfigureerror'] += $nconfigureerrors;

        if ($include_subprojects) {
            $nconfigurewarnings = $selected_configure_warnings;
        } else {
            $nconfigurewarnings = $build_array['countconfigurewarnings'] -
                $selected_configure_warnings;
        }
        $configure_response['warning'] = $nconfigurewarnings;
        $buildgroups_response[$i]['numconfigurewarning'] += $nconfigurewarnings;

        $diff = $build_array['countconfigurewarningdiff'];
        if ($diff!=0) {
            $configure_response['warningdiff'] = $diff;
        }

        if ($build_array['hasconfigurestatus'] != 0) {
            $duration = $build_array['configureduration'];
            $configure_response['time'] = time_difference($duration*60.0, true);
            $configure_response['timefull'] = $duration;
            $buildgroups_response[$i]['configureduration'] += $duration;
            $hasconfiguredata = true;
        }
        if ($hasconfiguredata) {
            $build_response['configure'] = $configure_response;
            $buildgroups_response[$i]['hasconfiguredata'] = true;
        }

        if ($build_array['hastest'] != 0) {
            $buildgroups_response[$i]['hastestdata'] = true;
            $test_response = array();

            if ($include_subprojects) {
                $nnotrun = $selected_tests_not_run;
            } else {
                $nnotrun = $build_array['counttestsnotrun'] -
                    $selected_tests_not_run;
            }

            if ($build_array['counttestsnotrundiffp']!=0) {
                $test_response['nnotrundiffp'] = $build_array['counttestsnotrundiffp'];
            }
            if ($build_array['counttestsnotrundiffn']!=0) {
                $test_response['nnotrundiffn'] = $build_array['counttestsnotrundiffn'];
            }

            if ($include_subprojects) {
                $nfail = $selected_tests_failed;
            } else {
                $nfail = $build_array['counttestsfailed'] -
                    $selected_tests_failed;
            }

            if ($build_array['counttestsfaileddiffp']!=0) {
                $test_response['nfaildiffp'] = $build_array['counttestsfaileddiffp'];
            }
            if ($build_array['counttestsfaileddiffn']!=0) {
                $test_response['nfaildiffn'] = $build_array['counttestsfaileddiffn'];
            }

            if ($include_subprojects) {
                $npass = $selected_tests_passed;
            } else {
                $npass = $build_array['counttestspassed'] -
                    $selected_tests_passed;
            }

            if ($build_array['counttestspasseddiffp']!=0) {
                $test_response['npassdiffp'] = $build_array['counttestspasseddiffp'];
            }
            if ($build_array['counttestspasseddiffn']!=0) {
                $test_response['npassdiffn'] = $build_array['counttestspasseddiffn'];
            }

            if ($project_array["showtesttime"] == 1) {
                $test_response['timestatus'] = $build_array['countteststimestatusfailed'];

                if ($build_array['countteststimestatusfaileddiffp']!=0) {
                    $test_response['ntimediffp'] = $build_array['countteststimestatusfaileddiffp'];
                }
                if ($build_array['countteststimestatusfaileddiffn']!=0) {
                    $test_response['ntimediffn'] = $build_array['countteststimestatusfaileddiffn'];
                }
            }

            $test_response['notrun'] = $nnotrun;
            $test_response['fail'] = $nfail;
            $test_response['pass'] = $npass;

            $buildgroups_response[$i]['numtestnotrun'] += $nnotrun;
            $buildgroups_response[$i]['numtestfail'] += $nfail;
            $buildgroups_response[$i]['numtestpass'] += $npass;

            $duration = $build_array['testsduration'];
            $test_response['time'] = time_difference($duration*60.0, true);
            $test_response['timefull'] = $duration;
            $buildgroups_response[$i]['testduration'] += $duration;

            $build_response['test'] = $test_response;
        }


        $starttimestamp = strtotime($build_array["starttime"]." UTC");
        $submittimestamp = strtotime($build_array["submittime"]." UTC");
        // Use the default timezone.
        $build_response['builddatefull'] = $starttimestamp;

        // If the data is more than 24h old then we switch from an elapsed to a normal representation
        if (time()-$starttimestamp<86400) {
            $build_response['builddate'] = date(FMT_DATETIMEDISPLAY, $starttimestamp);
            $build_response['builddateelapsed'] = time_difference(time()-$starttimestamp, false, 'ago');
        } else {
            $build_response['builddateelapsed'] = date(FMT_DATETIMEDISPLAY, $starttimestamp);
            $build_response['builddate'] = time_difference(time()-$starttimestamp, false, 'ago');
        }
        $build_response['submitdate'] = date(FMT_DATETIMEDISPLAY, $submittimestamp);
        $build_response['nerrorlog'] = $build_array["nerrorlog"];

        $buildgroups_response[$i]['builds'][] = $build_response;

        // Coverage
        //

        // Determine if this is a parent build with no actual coverage of its own.
        $linkToChildCoverage = false;
        if ($numchildren > 0) {
            $countChildrenResult = pdo_single_row_query(
                    "SELECT count(fileid) AS nfiles FROM coverage
                    WHERE buildid=".qnum($buildid));
            if ($countChildrenResult['nfiles'] == 0) {
                $linkToChildCoverage = true;
            }
        }

        $coverageIsGrouped = false;
        $coverages = pdo_query("SELECT * FROM coveragesummary WHERE buildid='$buildid'");
        while ($coverage_array = pdo_fetch_array($coverages)) {
            $coverage_response = array();
            $coverage_response['buildid'] = $build_array["id"];
            if ($linkToChildCoverage) {
                $coverage_response['childlink'] = "$child_builds_hyperlink#Coverage";
            }

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

            $starttimestamp = strtotime($build_array["starttime"]." UTC");
            $coverage_response['datefull'] = $starttimestamp;

            // If the data is more than 24h old then we switch from an elapsed to a normal representation
            if (time()-$starttimestamp<86400) {
                $coverage_response['date'] = date(FMT_DATETIMEDISPLAY, $starttimestamp);
                $coverage_response['dateelapsed'] = time_difference(time()-$starttimestamp, false, 'ago');
            } else {
                $coverage_response['dateelapsed'] = date(FMT_DATETIMEDISPLAY, $starttimestamp);
                $coverage_response['date'] = time_difference(time()-$starttimestamp, false, 'ago');
            }

            // Are there labels for this build?
            //
            if (empty($labels_array)) {
                $coverage_response['label'] = "(none)";
            } else {
                $num_labels = count($labels_array);
                if ($num_labels == 1) {
                    $coverage_response['label'] = $labels_array[0];
                } else {
                    $coverage_response['label'] = "($num_labels labels)";
                }
            }

            if ($coverageIsGrouped) {
                $coverage_groups[$groupId]['coverages'][] = $coverage_response;
            } else {
                $coverage_response['site'] = $build_array["sitename"];
                $coverage_response['buildname'] = $build_array["name"];
                $response['coverages'][] = $coverage_response;
            }
        }  // end coverage
        if (!$coverageIsGrouped) {
            $coverageThreshold = $project_array['coveragethreshold'];
            $response['thresholdgreen'] = $coverageThreshold;
            $response['thresholdyellow'] = $coverageThreshold * 0.7;
        }

        // Dynamic Analysis
        //
        $dynanalysis = pdo_query("SELECT checker,status FROM dynamicanalysis WHERE buildid='$buildid' LIMIT 1");
        while ($dynanalysis_array = pdo_fetch_array($dynanalysis)) {
            $DA_response = array();
            $DA_response['site'] = $build_array["sitename"];
            $DA_response['buildname'] = $build_array["name"];
            $DA_response['buildid'] = $build_array["id"];

            $DA_response['checker'] = $dynanalysis_array["checker"];
            $DA_response['status'] = $dynanalysis_array["status"];
            $defect = pdo_query("SELECT sum(dd.value) FROM dynamicanalysisdefect AS dd,dynamicanalysis as d
                    WHERE d.buildid='$buildid' AND dd.dynamicanalysisid=d.id");
            $defectcount = pdo_fetch_array($defect);
            if (!isset($defectcount[0])) {
                $defectcounts = 0;
            } else {
                $defectcounts = $defectcount[0];
            }
            $DA_response['defectcount'] = $defectcounts;
            $starttimestamp = strtotime($build_array["starttime"]." UTC");
            $DA_response['datefull'] = $starttimestamp;

            // If the data is more than 24h old then we switch from an elapsed to a normal representation
            if (time()-$starttimestamp<86400) {
                $DA_response['date'] = date(FMT_DATETIMEDISPLAY, $starttimestamp);
                $DA_response['dateelapsed'] =
                    time_difference(time()-$starttimestamp, false, 'ago');
            } else {
                $DA_response['dateelapsed'] = date(FMT_DATETIMEDISPLAY, $starttimestamp);
                $DA_response['date'] =
                    time_difference(time()-$starttimestamp, false, 'ago');
            }

            // Are there labels for this build?
            //
            if (empty($labels_array)) {
                $DA_response['label'] = "(none)";
            } else {
                $num_labels = count($labels_array);
                if ($num_labels == 1) {
                    $DA_response['label'] = $labels_array[0];
                } else {
                    $DA_response['label'] = "($num_labels labels)";
                }
            }

            $response['dynamicanalyses'][] = $DA_response;
        }  // end dynamicanalysis
    } // end looping through builds

    // Put some finishing touches on our buildgroups now that we're done
    // iterating over all the builds.
    $addExpected =
        empty($filter_sql) && (pdo_num_rows($builds) + count($dynamic_builds) > 0);
    for ($i = 0; $i < count($buildgroups_response); $i++) {
        $buildgroups_response[$i]['testduration'] = time_difference(
                $buildgroups_response[$i]['testduration'] * 60.0, true);

        if (!$filter_sql) {
            $groupname = $buildgroups_response[$i]['name'];
            $expected_builds =
                add_expected_builds($buildgroups_response[$i]['id'], $currentstarttime,
                        $received_builds[$groupname]);
            if (is_array($expected_builds)) {
                $buildgroups_response[$i]['builds'] = array_merge(
                        $buildgroups_response[$i]['builds'], $expected_builds);
            }
        }
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
        array_filter($buildgroups_response, "is_buildgroup_nonempty");

    // Report buildgroups as a list, not an associative array.
    // Otherwise any missing buildgroups will cause our view to
    // not honor the order specified by the project admins.
    $buildgroups_response = array_values($buildgroups_response);

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
    $response['enableTestTiming'] = $project_array["showtesttime"];

    $end = microtime_float();
    $response['generationtime'] = round($end-$start, 3);
    if (!empty($site_response)) {
        $response = array_merge($response, $site_response);
    }

    echo json_encode(cast_data_for_JSON($response));
} // end echo_main_dashboard_JSON


// Get a link to a page showing the children of a given parent build.
function get_child_builds_hyperlink($parentid, $filterdata)
{
    $baseurl = $_SERVER['REQUEST_URI'];

    // Strip /api/v#/ off of our URL to get the human-viewable version.
    $baseurl = preg_replace("#/api/v[0-9]+#", "", $baseurl);

    // Trim off any filter parameters.  Previously we did this step with a simple
    // strpos check, but since the change to AngularJS query parameters are no
    // longer guaranteed to appear in any particular order.
    $accepted_parameters = array("project", "date", "parentid", "subproject");

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
    for ($i = 0; $i<$count; $i++) {
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
                $filter['compare'] != 80) {
            $n++;

            $existing_filter_params .=
                '&field' . $n . '=' . $filter['field'] .
                '&compare' . $n . '=' . $filter['compare'] .
                '&value' . $n . '=' . htmlspecialchars($filter['value']);
        }
    }
    if ($n > 0) {
        $existing_filter_params .= "&filtercount=$count";
        $existing_filter_params .= "&showfilters=1";

        // Multiple subproject includes need to be combined with 'or' (not 'and')
        // at the child level.
        if ($num_includes > 1) {
            $existing_filter_params .= '&filtercombine=or';
        } elseif (array_key_exists('filtercombine', $filterdata)) {
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
    include(dirname(dirname(dirname(__DIR__)))."/config/config.php");

    if (isset($_GET["parentid"])) {
        // Don't add expected builds when viewing a single subproject result.
        return;
    }

    $currentUTCTime =  gmdate(FMT_DATETIME, $currentstarttime+3600*24);
    $response = array();
    $build2grouprule = pdo_query(
            "SELECT g.siteid, g.buildname, g.buildtype, s.name, s.outoforder
            FROM build2grouprule AS g, site AS s
            WHERE g.expected='1' AND g.groupid='$groupid' AND s.id=g.siteid AND
            g.starttime<'$currentUTCTime' AND
            (g.endtime>'$currentUTCTime' OR g.endtime='1980-01-01 00:00:00')");
    while ($build2grouprule_array = pdo_fetch_array($build2grouprule)) {
        $key = $build2grouprule_array["name"]."_".$build2grouprule_array["buildname"];
        if (array_search($key, $received_builds) === false) {
            // add only if not found

            $site = $build2grouprule_array["name"];
            $siteid = $build2grouprule_array["siteid"];
            $siteoutoforder = $build2grouprule_array["outoforder"];
            $buildtype = $build2grouprule_array["buildtype"];
            $buildname = $build2grouprule_array["buildname"];
            $build_response = array();
            $build_response['site'] = $site;
            $build_response['siteoutoforder'] = $siteoutoforder;
            $build_response['siteid'] = $siteid;
            $build_response['buildname'] = $buildname;
            $build_response['buildtype'] = $buildtype;
            $build_response['buildgroupid'] = $groupid;
            $build_response['expectedandmissing'] = 1;

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
                    $time += strtotime(date("H:i:s", strtotime($query_array['submittime'])));
                }
                if (pdo_num_rows($query)>0) {
                    $time /= pdo_num_rows($query);
                }
                $nextExpected = strtotime(date("H:i:s", $time)." UTC");
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
                $hours = floor($time/3600);
                $time = ($time%3600);
                $minutes = floor($time/60);
                $seconds = ($time%60);
                $nextExpected = strtotime($hours.":".$minutes.":".$seconds." UTC");
            }

            $divname = $build2grouprule_array["siteid"]."_".$build2grouprule_array["buildname"];
            $divname = str_replace("+", "_", $divname);
            $divname = str_replace(".", "_", $divname);
            $divname = str_replace(':', "_", $divname);
            $divname = str_replace(' ', "_", $divname);

            $build_response['expecteddivname'] = $divname;
            $build_response['submitdate'] = "No Submission";
            $build_response['expectedstarttime'] = date(FMT_TIME, $nextExpected);
            $response[] = $build_response;
        }
    }
    return $response;
}
