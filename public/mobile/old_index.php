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

require_once dirname(dirname(__DIR__)) . '/config/config.php';
require_once 'include/pdo.php';
include 'include/common.php';
include 'include/version.php';
require_once 'models/project.php';
require_once 'models/buildfailure.php';
require_once 'include/filterdataFunctions.php';
require_once 'include/index_functions.php';

@set_time_limit(0);

/** Generate the index table */
function generate_index_table()
{
    $noforcelogin = 1;
    include 'config/config.php';
    require_once 'include/pdo.php';
    include 'public/login.php';
    include_once 'models/banner.php';

    $xml = begin_XML_for_XSLT();
    $xml .= add_XML_value('title', 'CDash - Continuous Integration Made Easy');

    $Banner = new Banner;
    $Banner->SetProjectId(0);
    $text = $Banner->GetText();
    if ($text !== false) {
        $xml .= '<banner>';
        $xml .= add_XML_value('text', $text);
        $xml .= '</banner>';
    }

    $xml .= '<hostname>' . $_SERVER['SERVER_NAME'] . '</hostname>';
    $xml .= '<date>' . date('r') . '</date>';

    // Check if the database is up to date
    $query = "SELECT * FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = '$CDASH_DB_NAME'
            AND TABLE_NAME = 'buildfailuredetails'
            AND COLUMN_NAME = 'id'";
    $dbTest = pdo_single_row_query($query);
    if (empty($dbTest)) {
        $xml .= '<upgradewarning>1</upgradewarning>';
    }

    $xml .= '<dashboard>
 <title>' . $CDASH_MAININDEX_TITLE . '</title>
 <subtitle>' . $CDASH_MAININDEX_SUBTITLE . '</subtitle>
 <googletracker>' . $CDASH_DEFAULT_GOOGLE_ANALYTICS . '</googletracker>';
    if (isset($CDASH_NO_REGISTRATION) && $CDASH_NO_REGISTRATION == 1) {
        $xml .= add_XML_value('noregister', '1');
    }
    $xml .= '</dashboard> ';

    // User
    $userid = 0;
    if (isset($_SESSION['cdash']) && isset($_SESSION['cdash']['loginid'])) {
        $xml .= '<user>';
        $userid = $_SESSION['cdash']['loginid'];
        $user = pdo_query('SELECT admin FROM ' . qid('user') . " WHERE id='$userid'");
        $user_array = pdo_fetch_array($user);
        $xml .= add_XML_value('id', $userid);
        $xml .= add_XML_value('admin', $user_array['admin']);
        $xml .= '</user>';
    }

    $showallprojects = 0;
    if (isset($_GET['allprojects']) && $_GET['allprojects'] == 1) {
        $showallprojects = 1;
    }

    $projects = get_projects(!$showallprojects);
    $row = 0;
    foreach ($projects as $project) {
        $xml .= '<project>';
        $xml .= add_XML_value('name', $project['name']);
        $xml .= add_XML_value('name_encoded', urlencode($project['name']));
        $xml .= add_XML_value('description', $project['description']);
        if ($project['last_build'] == 'NA') {
            $xml .= '<lastbuild>NA</lastbuild>';
            $xml .= '<activitylevel>none</activitylevel>';
        } else {
            $lastbuild = strtotime($project['last_build'] . 'UTC');
            $xml .= '<lastbuild>' . date(FMT_DATETIMEDISPLAY, $lastbuild) . '</lastbuild>';
            $xml .= '<lastbuilddate>' . date(FMT_DATE, $lastbuild) . '</lastbuilddate>';
            $xml .= '<lastbuild_elapsed>' . time_difference(time() - $lastbuild, false, 'ago') . '</lastbuild_elapsed>';
            $xml .= '<lastbuilddatefull>' . $lastbuild . '</lastbuilddatefull>';
            $xml .= '<activitylevel>high</activitylevel>';
        }

        $xml .= '<activity>';
        if (!isset($project['nbuilds']) || $project['nbuilds'] == 0) {
            $xml .= 'none';
        } elseif ($project['nbuilds'] < 20) {
            // 2 builds day

            $xml .= 'low';
        } elseif ($project['nbuilds'] < 70) {
            // 10 builds a day

            $xml .= 'medium';
        } elseif ($project['nbuilds'] >= 70) {
            $xml .= 'high';
        }

        $xml .= '</activity>';

        //$uploadsizeGB = round($project['uploadsize'] / (1024.0*1024.0*1024.0), 2);
        //$xml .= '<uploadsize>'.$uploadsizeGB.'</uploadsize>';
        $xml .= '<row>' . $row . '</row>';
        $xml .= '</project>';
        if ($row == 0) {
            $row = 1;
        } else {
            $row = 0;
        }
    }

    $xml .= '<allprojects>' . $showallprojects . '</allprojects>';
    $xml .= '<nprojects>' . get_number_public_projects() . '</nprojects>';
    $xml .= '</cdash>';
    return $xml;
}

function add_buildgroup_sortlist($groupname)
{
    // This function defines how the build group tables should be sorted.
    // This information can be provided as a query string, otherwise we apply
    // some default ordering here.  Default sort ordering for a group is based
    // on the groupname.
    //
    // Sort settings should probably be definable/overrideable by the user as wel
    // on the users page, or perhaps by the project admin on the project page.
    //
    $st = '';
    $xml = '';

    if (isset($_GET['sort'])) {
        $xml .= add_XML_value('sortlist', '{sortlist: ' . $_GET['sort'] . '}');
        return $xml;
    }

    $gn = strtolower($groupname);

    if (strpos($gn, 'nightly') !== false) {
        $st = 'SortAsNightly';
    } elseif ((strpos($gn, 'continuous') !== false) || (strpos($gn, 'experimental') !== false)) {
        $st = 'SortByTime';
    }

    switch ($st) {
        case 'SortAsNightly':
            // Theoretically, most important to least important:
            //   configure errors DESC, build errors DESC, tests failed DESC,
            //   tests not run DESC, configure warnings DESC, build warnings DESC
            if (isset($_GET['parentid'])) {
                // child build view begins with one text column (not two),
                // so adjust indices accordingly.
                $xml .= add_XML_value('sortlist', '{sortlist: [[3,1],[6,1],[11,1],[10,1],[4,1],[7,1]]}');
            } else {
                $xml .= add_XML_value('sortlist', '{sortlist: [[4,1],[7,1],[11,1],[10,1],[5,1],[8,1]]}');
            }
            break;

        case 'SortByTime':
            $xml .= add_XML_value('sortlist', '{sortlist: [[14,1]]}');
            // build time DESC
            break;

        // By default, no javascript-based sorting. Accept the ordering naturally as it came from
        // MySQL and the php processing code...
    }
    return $xml;
}

/** Get a link to a page showing the children of a given parent build. */
function get_child_builds_hyperlink($parentid, $filterdata)
{
    $baseurl = $_SERVER['REQUEST_URI'];

    // If the current REQUEST_URI already has a &filtercount=... (and other
    // filter stuff), trim it off and just use part that comes before that:
    //
    $idx = strpos($baseurl, '&filtercount=');
    if ($idx !== false) {
        $baseurl = substr($baseurl, 0, $idx);
    }

    // Similarly trim off &display=..., as that parameter is implied
    // when viewing the results of a single (parent) build.
    $idx = strpos($baseurl, '&display=');
    if ($idx !== false) {
        $baseurl = substr($baseurl, 0, $idx);
    }

    // Preserve any filters the user had specified.
    $existing_filter_params = '';
    $n = 0;
    $count = count($filterdata['filters']);
    for ($i = 0; $i < $count; $i++) {
        $filter = $filterdata['filters'][$i];

        if ($filter['field'] != 'buildname' &&
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
                '&field' . $n . '=' . $filter['field'] . '/' . $filter['fieldtype'] .
                '&compare' . $n . '=' . $filter['compare'] .
                '&value' . $n . '=' . htmlspecialchars($filter['value']);
        }
    }

    // Construct & return our URL.
    $url = "$baseurl&parentid=$parentid";
    $url .= $existing_filter_params;
    return $url;
}

/** Generate the main dashboard XML */
function generate_main_dashboard_XML($project_instance, $date)
{
    $start = microtime_float();
    $noforcelogin = 1;
    include_once 'config/config.php';
    require_once 'include/pdo.php';
    include 'public/login.php';
    include_once 'models/banner.php';
    include_once 'models/subproject.php';

    $db = pdo_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN", "$CDASH_DB_PASS");
    if (!$db) {
        echo "Error connecting to CDash database server<br>\n";
        return;
    }
    if (!pdo_select_db("$CDASH_DB_NAME", $db)) {
        echo "Error selecting CDash database<br>\n";
        return;
    }

    $projectid = $project_instance->Id;

    $project = pdo_query("SELECT * FROM project WHERE id='$projectid'");
    if (pdo_num_rows($project) > 0) {
        $project_array = pdo_fetch_array($project);
        $svnurl = make_cdash_url(htmlentities($project_array['cvsurl']));
        $homeurl = make_cdash_url(htmlentities($project_array['homeurl']));
        $bugurl = make_cdash_url(htmlentities($project_array['bugtrackerurl']));
        $googletracker = htmlentities($project_array['googletracker']);
        $docurl = make_cdash_url(htmlentities($project_array['documentationurl']));
        $projectpublic = $project_array['public'];
        $projectname = $project_array['name'];

        if (isset($project_array['testingdataurl']) && $project_array['testingdataurl'] != '') {
            $testingdataurl = make_cdash_url(htmlentities($project_array['testingdataurl']));
        }
    } else {
        redirect_error('This project doesn\'t exist. Maybe the URL you are trying to access is wrong.');
        return false;
    }

    checkUserPolicy(@$_SESSION['cdash']['loginid'], $project_array['id']);

    $xml = begin_XML_for_XSLT();
    $xml .= '<title>CDash - ' . $projectname . '</title>';

    $Banner = new Banner;
    $Banner->SetProjectId(0);
    $text = $Banner->GetText();
    if ($text !== false) {
        $xml .= '<banner>';
        $xml .= add_XML_value('text', $text);
        $xml .= '</banner>';
    }

    $Banner->SetProjectId($projectid);
    $text = $Banner->GetText();
    if ($text !== false) {
        $xml .= '<banner>';
        $xml .= add_XML_value('text', $text);
        $xml .= '</banner>';
    }
    $sitexml = '';

    list($previousdate, $currentstarttime, $nextdate) = get_dates($date, $project_array['nightlytime']);
    $logoid = getLogoID($projectid);

    // Main dashboard section
    $xml .=
        '<dashboard>
  <datetime>' . date('l, F d Y H:i:s T', time()) . '</datetime>
  <date>' . $date . '</date>
  <unixtimestamp>' . $currentstarttime . '</unixtimestamp>
  <svn>' . $svnurl . '</svn>
  <bugtracker>' . $bugurl . '</bugtracker>
  <googletracker>' . $googletracker . '</googletracker>
  <documentation>' . $docurl . '</documentation>
  <logoid>' . $logoid . '</logoid>
  <projectid>' . $projectid . '</projectid>
  <projectname>' . $projectname . '</projectname>
  <projectname_encoded>' . urlencode($projectname) . '</projectname_encoded>
  <previousdate>' . $previousdate . '</previousdate>
  <projectpublic>' . $projectpublic . '</projectpublic>
  <displaylabels>' . $project_array['displaylabels'] . '</displaylabels>
  <nextdate>' . $nextdate . '</nextdate>';

    if (empty($project_array['homeurl'])) {
        $xml .= '<home>index.php?project=' . urlencode($projectname) . '</home>';
    } else {
        $xml .= '<home>' . $homeurl . '</home>';
    }

    if (isset($_GET['parentid'])) {
        $xml .= '<childview>1</childview>';
    } else {
        $xml .= '<childview>0</childview>';
    }

    if ($CDASH_USE_LOCAL_DIRECTORY && file_exists('local/models/proProject.php')) {
        include_once 'local/models/proProject.php';
        $pro = new proProject;
        $pro->ProjectId = $projectid;
        $xml .= '<proedition>' . $pro->GetEdition(1) . '</proedition>';
    }

    if ($currentstarttime > time()) {
        $xml .= '<future>1</future>';
    } else {
        $xml .= '<future>0</future>';
    }

    $xml .= '</dashboard>';

    // Menu definition
    $xml .= '<menu>';
    if (!has_next_date($date, $currentstarttime)) {
        $xml .= add_XML_value('nonext', '1');
    }
    $xml .= '</menu>';

    // Check the builds
    $beginning_timestamp = $currentstarttime;
    $end_timestamp = $currentstarttime + 3600 * 24;

    $beginning_UTCDate = gmdate(FMT_DATETIME, $beginning_timestamp);
    $end_UTCDate = gmdate(FMT_DATETIME, $end_timestamp);

    // Add the extra url if necessary
    if (isset($_GET['display']) && $_GET['display'] == 'project') {
        $xml .= add_XML_value('extraurl', '&display=project');
    }

    // If we have a subproject
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
            $xml .= add_XML_value('extraurl', '&subproject=' . urlencode($subproject_name));
            $xml .= add_XML_value('subprojectname', $subproject_name);

            $xml .= '<subproject>';

            $xml .= add_XML_value('name', $SubProject->GetName());

            $rowparity = 0;
            $dependencies = $SubProject->GetDependencies();
            if ($dependencies) {
                foreach ($dependencies as $dependency) {
                    $xml .= '<dependency>';
                    $DependProject = new SubProject();
                    $DependProject->SetId($dependency);
                    $xml .= add_XML_value('rowparity', $rowparity);
                    $xml .= add_XML_value('name', $DependProject->GetName());
                    $xml .= add_XML_value('name_encoded', urlencode($DependProject->GetName()));
                    $xml .= add_XML_value('nbuilderror', $DependProject->GetNumberOfErrorBuilds($beginning_UTCDate, $end_UTCDate));
                    $xml .= add_XML_value('nbuildwarning', $DependProject->GetNumberOfWarningBuilds($beginning_UTCDate, $end_UTCDate));
                    $xml .= add_XML_value('nbuildpass', $DependProject->GetNumberOfPassingBuilds($beginning_UTCDate, $end_UTCDate));
                    $xml .= add_XML_value('nconfigureerror', $DependProject->GetNumberOfErrorConfigures($beginning_UTCDate, $end_UTCDate));
                    $xml .= add_XML_value('nconfigurewarning', $DependProject->GetNumberOfWarningConfigures($beginning_UTCDate, $end_UTCDate));
                    $xml .= add_XML_value('nconfigurepass', $DependProject->GetNumberOfPassingConfigures($beginning_UTCDate, $end_UTCDate));
                    $xml .= add_XML_value('ntestpass', $DependProject->GetNumberOfPassingTests($beginning_UTCDate, $end_UTCDate));
                    $xml .= add_XML_value('ntestfail', $DependProject->GetNumberOfFailingTests($beginning_UTCDate, $end_UTCDate));
                    $xml .= add_XML_value('ntestnotrun', $DependProject->GetNumberOfNotRunTests($beginning_UTCDate, $end_UTCDate));
                    if (strlen($DependProject->GetLastSubmission()) == 0) {
                        $xml .= add_XML_value('lastsubmission', 'NA');
                    } else {
                        $xml .= add_XML_value('lastsubmission', $DependProject->GetLastSubmission());
                    }
                    $rowparity = ($rowparity == 1) ? 0 : 1;
                    $xml .= '</dependency>';
                }
            }
            $xml .= '</subproject>';
        } else {
            add_log("SubProject '$subproject_name' does not exist",
                __FILE__ . ':' . __LINE__ . ' - ' . __FUNCTION__,
                LOG_WARNING);
        }
    }

    if (isset($testingdataurl)) {
        $xml .= add_XML_value('testingdataurl', $testingdataurl);
    }

    // updates
    $xml .= '<updates>';

    $gmdate = gmdate(FMT_DATE, $currentstarttime);
    $xml .= '<url>viewChanges.php?project=' . urlencode($projectname) . '&amp;date=' . $gmdate . '</url>';

    $dailyupdate = pdo_query("SELECT count(ds.dailyupdateid),count(distinct ds.author)
                            FROM dailyupdate AS d LEFT JOIN dailyupdatefile AS ds ON (ds.dailyupdateid = d.id)
                            WHERE d.date='$gmdate' and d.projectid='$projectid' GROUP BY ds.dailyupdateid");

    if (pdo_num_rows($dailyupdate) > 0) {
        $dailupdate_array = pdo_fetch_array($dailyupdate);
        $xml .= '<nchanges>' . $dailupdate_array[0] . '</nchanges>';
        $xml .= '<nauthors>' . $dailupdate_array[1] . '</nauthors>';
    } else {
        $xml .= '<nchanges>-1</nchanges>';
    }
    $xml .= add_XML_value('timestamp', date('l, F d Y - H:i T', $currentstarttime));
    $xml .= '</updates>';

    // User
    if (isset($_SESSION['cdash']) && isset($_SESSION['cdash']['loginid'])) {
        $xml .= '<user>';
        $userid = $_SESSION['cdash']['loginid'];
        $user2project = pdo_query("SELECT role FROM user2project WHERE userid='$userid' and projectid='$projectid'");
        $user2project_array = pdo_fetch_array($user2project);
        $user = pdo_query('SELECT admin FROM ' . qid('user') . "  WHERE id='$userid'");
        $user_array = pdo_fetch_array($user);
        $xml .= add_XML_value('id', $userid);
        $isadmin = 0;
        if ($user2project_array['role'] > 1 || $user_array['admin']) {
            $isadmin = 1;
        }
        $xml .= add_XML_value('admin', $isadmin);
        $xml .= add_XML_value('projectrole', $user2project_array['role']);
        $xml .= '</user>';
    }

    // Filters:
    //
    $filterdata = get_filterdata_from_request();
    $filter_sql = $filterdata['sql'];
    $limit_sql = '';
    if ($filterdata['limit'] > 0) {
        $limit_sql = ' LIMIT ' . $filterdata['limit'];
    }
    $xml .= $filterdata['xml'];

    // Local function to add expected builds
    function add_expected_builds($groupid, $currentstarttime, $received_builds)
    {
        include 'config/config.php';

        if (isset($_GET['parentid'])) {
            // Don't add expected builds when viewing a single subproject result.
            return;
        }

        $currentUTCTime = gmdate(FMT_DATETIME, $currentstarttime + 3600 * 24);
        $xml = '';
        $build2grouprule = pdo_query("SELECT g.siteid,g.buildname,g.buildtype,s.name,s.outoforder FROM build2grouprule AS g,site as s
                                  WHERE g.expected='1' AND g.groupid='$groupid' AND s.id=g.siteid
                                  AND g.starttime<'$currentUTCTime' AND (g.endtime>'$currentUTCTime' OR g.endtime='1980-01-01 00:00:00')
                                  ");
        while ($build2grouprule_array = pdo_fetch_array($build2grouprule)) {
            $key = $build2grouprule_array['name'] . '_' . $build2grouprule_array['buildname'];
            if (array_search($key, $received_builds) === false) {
                // add only if not found

                $site = $build2grouprule_array['name'];
                $siteid = $build2grouprule_array['siteid'];
                $siteoutoforder = $build2grouprule_array['outoforder'];
                $buildtype = $build2grouprule_array['buildtype'];
                $buildname = $build2grouprule_array['buildname'];
                $xml .= '<build>';
                $xml .= add_XML_value('site', $site);
                $xml .= add_XML_value('siteoutoforder', $siteoutoforder);
                $xml .= add_XML_value('siteid', $siteid);
                $xml .= add_XML_value('buildname', $buildname);
                $xml .= add_XML_value('buildtype', $buildtype);
                $xml .= add_XML_value('buildgroupid', $groupid);
                $xml .= add_XML_value('expected', '1');

                // compute historical average to get approximate expected time
                // PostgreSQL doesn't have the necessary functions for this
                if ($CDASH_DB_TYPE == 'pgsql') {
                    $query = pdo_query("SELECT submittime FROM build,build2group
                              WHERE build2group.buildid=build.id AND siteid='$siteid' AND name='$buildname'
                              AND type='$buildtype' AND build2group.groupid='$groupid'
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
                    $query = pdo_query("SELECT AVG(TIME_TO_SEC(TIME(submittime))) FROM (SELECT submittime FROM build,build2group
                                WHERE build2group.buildid=build.id AND siteid='$siteid' AND name='$buildname'
                                AND type='$buildtype' AND build2group.groupid='$groupid'
                                ORDER BY id DESC LIMIT 5) as t");
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

                $xml .= add_XML_value('expecteddivname', $divname);
                $xml .= add_XML_value('submitdate', 'No Submission');
                $xml .= add_XML_value('expectedstarttime', date(FMT_TIME, $nextExpected));
                $xml .= '</build>';
            }
        }
        return $xml;
    }

    // add a request for the subproject
    $subprojectsql = '';
    $subprojecttablesql = '';
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
    if (isset($_SESSION['cdash']) && isset($_SESSION['cdash']['loginid'])) {
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

    // Postgres differs from MySQL on how to aggregate results
    // into a single column.
    $label_sql = '';
    $groupby_sql = '';
    if ($CDASH_DB_TYPE != 'pgsql') {
        $label_sql = "GROUP_CONCAT(l.text SEPARATOR ', ') AS labels,";
        $groupby_sql = ' GROUP BY b.id';
    }

    $sql = 'SELECT b.id,b.siteid,b.parentid,
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
                  (SELECT count(buildid) FROM buildnote WHERE buildid=b.id) AS countbuildnotes,'
        . $userupdatesql . "
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
                  g.name as groupname,gp.position,g.id as groupid,
                  $label_sql
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
                  " . $subprojectsql . ' ' . $filter_sql . ' ' . $groupby_sql
        . $limit_sql;

    // We shouldn't get any builds for group that have been deleted (otherwise something is wrong)
    $builds = pdo_query($sql);
    echo pdo_error();

    // Sort results from this query.
    // We used to do this in MySQL with the following directive:
    // ORDER BY gp.position ASC,b.name ASC,b.siteid ASC,b.stamp DESC
    // But this dramatically impacted performance when the number of rows was
    // relatively large (in the thousands).  So now we accomplish the same
    // sorting within PHP instead.
    $build_data = array();
    while ($build_row = pdo_fetch_array($builds)) {
        $build_data[] = $build_row;
    }

    $dynamic_builds = array();
    if (empty($filter_sql)) {
        $dynamic_builds = get_dynamic_builds($projectid, $end_UTCDate);
        $build_data = array_merge($build_data, $dynamic_builds);
    }

    $positions = array();
    $names = array();
    $siteids = array();
    $stamps = array();
    foreach ($build_data as $key => $row) {
        $positions[$key] = $row['position'];
        $names[$key] = $row['name'];
        $siteids[$key] = $row['siteid'];
        $stamps[$key] = $row['stamp'];
    }
    array_multisort($positions, SORT_ASC, $names, SORT_ASC, $siteids, SORT_ASC,
        $stamps, SORT_DESC, $build_data);

    // The SQL results are ordered by group so this should work
    // Group position have to be continuous
    $previousgroupposition = -1;

    $received_builds = array();

    // Find the last position of the group
    $groupposition_array = pdo_fetch_array(pdo_query("SELECT gp.position FROM buildgroupposition AS gp,buildgroup AS g
                                                        WHERE g.projectid='$projectid' AND g.id=gp.buildgroupid
                                                        AND gp.starttime<'$end_UTCDate' AND (gp.endtime>'$end_UTCDate' OR gp.endtime='1980-01-01 00:00:00')
                                                        ORDER BY gp.position DESC LIMIT 1"));

    $lastGroupPosition = $groupposition_array['position'];

    // Check if we need to summarize coverage by subproject groups.
    // This happens when we have subprojects and we're looking at the children
    // of a specific build.
    $subproject_groups = array();
    $subproject_group_coverage = array();
    if (isset($_GET['parentid']) && $_GET['parentid'] > 0 &&
        $project_instance->GetNumberOfSubProjects($end_UTCDate) > 0
    ) {
        $groups = $project_instance->GetSubProjectGroups();
        foreach ($groups as $group) {
            // Create an Id -> Object mapping for our subproject groups.
            $subproject_groups[$group->GetId()] = $group;
            // Also keep track of coverage info on a per-group basis.
            $subproject_group_coverage[$group->GetId()] = array();
            $subproject_group_coverage[$group->GetId()]['tested'] = 0;
            $subproject_group_coverage[$group->GetId()]['untested'] = 0;
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
        //  buildduration
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
            $build_row['labels'] = explode(',', $build_row['labels']);
        }

        // If this is a parent build get the labels from all the children too.
        if ($parentid == -1) {
            $query = "SELECT l.text FROM build AS b
        INNER JOIN label2build AS l2b ON l2b.buildid = b.id
        INNER JOIN label AS l ON l.id = l2b.labelid
        WHERE b.parentid='$buildid'";

            $childLabelsResult = pdo_query($query);
            while ($childLabelsArray = pdo_fetch_array($childLabelsResult)) {
                $build_row['labels'][] = $childLabelsArray['text'];
            }
        }

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

        $build_row['buildduration'] = round((strtotime($build_row['endtime']) - strtotime($build_row['starttime'])) / 60, 1);

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

        if ($build_row['countconfigureerrors'] < 0) {
            $build_row['countconfigureerrors'] = 0;
        }
        if ($build_row['countconfigurewarnings'] < 0) {
            $build_row['countconfigurewarnings'] = 0;
        }

        $build_row['hasconfigurestatus'] = 0;
        $build_row['configureduration'] = 0;

        if (strlen($build_row['configurestatus']) > 0) {
            $build_row['hasconfigurestatus'] = 1;
            $build_row['configureduration'] = round((strtotime($build_row['configureendtime']) - strtotime($build_row['configurestarttime'])) / 60, 1);
        }

        if (empty($build_row['countconfigurewarningdiff'])) {
            $build_row['countconfigurewarningdiff'] = 0;
        }

        $build_row['hastest'] = 0;
        if ($build_row['counttestsfailed'] != -1) {
            $build_row['hastest'] = 1;
        }

        if (empty($build_row['testsduration'])) {
            $time_array = pdo_fetch_array(pdo_query("SELECT SUM(time) FROM build2test WHERE buildid='$buildid'"));
            $build_row['testsduration'] = round($time_array[0] / 60, 1);
        } else {
            $build_row['testsduration'] = round($build_row['testsduration'], 1); //already in minutes
        }

        $build_rows[] = $build_row;
    }

    // Generate the xml from the rows of builds:
    //
    $totalUpdatedFiles = 0;
    $totalUpdateError = 0;
    $totalUpdateWarning = 0;
    $totalUpdateDuration = 0;
    $totalConfigureError = 0;
    $totalConfigureWarning = 0;
    $totalConfigureDuration = 0;
    $totalerrors = 0;
    $totalwarnings = 0;
    $totalBuildDuration = 0;
    $totalnotrun = 0;
    $totalfail = 0;
    $totalpass = 0;
    $totalTestsDuration = 0;

    foreach ($build_rows as $build_array) {
        $groupposition = $build_array['position'];

        if ($previousgroupposition != $groupposition) {
            $groupname = $build_array['groupname'];
            if ($previousgroupposition != -1) {
                if (!$filter_sql) {
                    $xml .= add_expected_builds($groupid, $currentstarttime, $received_builds);
                }

                $xml .= add_XML_value('totalUpdatedFiles', $totalUpdatedFiles);
                $xml .= add_XML_value('totalUpdateError', $totalUpdateError);
                $xml .= add_XML_value('totalUpdateWarning', $totalUpdateWarning);
                $xml .= add_XML_value('totalUpdateDuration', $totalUpdateDuration);

                $xml .= add_XML_value('totalConfigureDuration', $totalConfigureDuration);
                $xml .= add_XML_value('totalConfigureError', $totalConfigureError);
                $xml .= add_XML_value('totalConfigureWarning', $totalConfigureWarning);

                $xml .= add_XML_value('totalError', $totalerrors);
                $xml .= add_XML_value('totalWarning', $totalwarnings);
                $xml .= add_XML_value('totalBuildDuration', $totalBuildDuration);

                $xml .= add_XML_value('totalNotRun', $totalnotrun);
                $xml .= add_XML_value('totalFail', $totalfail);
                $xml .= add_XML_value('totalPass', $totalpass);
                $xml .= add_XML_value('totalTestsDuration', time_difference($totalTestsDuration * 60.0, true));
                $xml .= '</buildgroup>';
            }

            // We assume that the group position are continuous in N
            // So we fill in the gap if we are jumping
            $prevpos = $previousgroupposition + 1;
            if ($prevpos == 0) {
                $prevpos = 1;
            }

            for ($i = $prevpos; $i < $groupposition; $i++) {
                $group = pdo_fetch_array(pdo_query("SELECT g.name,g.id FROM buildgroup AS g,buildgroupposition AS gp WHERE g.id=gp.buildgroupid
                                                AND gp.position='$i' AND g.projectid='$projectid'
                                                AND gp.starttime<'$end_UTCDate' AND (gp.endtime>'$end_UTCDate'  OR gp.endtime='1980-01-01 00:00:00')
                                                "));
                $xml .= '<buildgroup>';
                $xml .= add_buildgroup_sortlist($group['name']);
                $xml .= add_XML_value('name', $group['name']);
                $xml .= add_XML_value('linkname', str_replace(' ', '_', $group['name']));
                $xml .= add_XML_value('id', $group['id']);
                if (!$filter_sql) {
                    $xml .= add_expected_builds($group['id'], $currentstarttime, $received_builds);
                }
                $xml .= '</buildgroup>';
            }

            $xml .= '<buildgroup>';
            $totalUpdatedFiles = 0;
            $totalUpdateError = 0;
            $totalUpdateWarning = 0;
            $totalUpdateDuration = 0;
            $totalConfigureError = 0;
            $totalConfigureWarning = 0;
            $totalConfigureDuration = 0;
            $totalerrors = 0;
            $totalwarnings = 0;
            $totalBuildDuration = 0;
            $totalnotrun = 0;
            $totalfail = 0;
            $totalpass = 0;
            $totalTestsDuration = 0;
            $xml .= add_buildgroup_sortlist($groupname);
            $xml .= add_XML_value('name', $groupname);
            $xml .= add_XML_value('linkname', str_replace(' ', '_', $groupname));
            $xml .= add_XML_value('id', $build_array['groupid']);

            $received_builds = array();

            $previousgroupposition = $groupposition;
        }

        $xml .= '<build>';

        $received_builds[] = $build_array['sitename'] . '_' . $build_array['name'];

        $buildid = $build_array['id'];
        $groupid = $build_array['groupid'];
        $siteid = $build_array['siteid'];

        $countChildrenResult = pdo_single_row_query(
            'SELECT count(id) AS nchildren FROM build WHERE parentid=' . qnum($buildid));
        $countchildren = $countChildrenResult['nchildren'];
        $xml .= add_XML_value('countchildren', $countchildren);
        $child_builds_hyperlink = '';
        if ($countchildren > 0) {
            $child_builds_hyperlink =
                get_child_builds_hyperlink($build_array['id'], $filterdata);
            $xml .= add_XML_value('multiplebuildshyperlink', $child_builds_hyperlink);
        }

        $xml .= add_XML_value('type', strtolower($build_array['type']));

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

        if (isset($_GET['parentid']) && empty($sitexml)) {
            $sitexml .= add_XML_value('site', $build_array['sitename']);
            $sitexml .= add_XML_value('siteoutoforder', $build_array['siteoutoforder']);
            $sitexml .= add_XML_value('siteid', $siteid);
            $sitexml .= add_XML_value('buildname', $build_array['name']);
            $sitexml .= add_XML_value('buildplatform', $buildplatform);
        } else {
            $xml .= add_XML_value('site', $build_array['sitename']);
            $xml .= add_XML_value('siteoutoforder', $build_array['siteoutoforder']);
            $xml .= add_XML_value('siteid', $siteid);
            $xml .= add_XML_value('buildname', $build_array['name']);
            $xml .= add_XML_value('buildplatform', $buildplatform);
        }

        if (isset($build_array['userupdates'])) {
            $xml .= add_XML_value('userupdates', $build_array['userupdates']);
        }
        $xml .= add_XML_value('buildid', $build_array['id']);
        $xml .= add_XML_value('generator', $build_array['generator']);
        $xml .= add_XML_value('upload-file-count', $build_array['builduploadfiles']);

        if ($build_array['countbuildnotes'] > 0) {
            $xml .= add_XML_value('buildnote', '1');
        }

        if ($build_array['countnotes'] > 0) {
            $xml .= add_XML_value('note', '1');
        }

        // Are there labels for this build?
        //
        $labels_array = $build_array['labels'];
        if (!empty($labels_array)) {
            $xml .= '<labels>';
            foreach ($labels_array as $label) {
                $xml .= add_XML_value('label', $label);
            }
            $xml .= '</labels>';
        }

        $xml .= '<update>';

        $countupdatefiles = $build_array['countupdatefiles'];
        $totalUpdatedFiles += $countupdatefiles;
        $xml .= add_XML_value('files', $countupdatefiles);
        if (!empty($build_array['updatestarttime'])) {
            $xml .= add_XML_value('defined', 1);

            if ($build_array['countupdateerrors'] > 0) {
                $xml .= add_XML_value('errors', 1);
                $totalUpdateError += 1;
            } else {
                $xml .= add_XML_value('errors', 0);

                if ($build_array['countupdatewarnings'] > 0) {
                    $xml .= add_XML_value('warning', 1);
                    $totalUpdateWarning += 1;
                }
            }

            $duration = $build_array['updateduration'];
            $totalUpdateDuration += $duration;
            $xml .= add_XML_value('time', time_difference($duration * 60.0, true));
            $xml .= add_XML_value('timefull', $duration);
        }
        $xml .= '</update>';

        $xml .= '<compilation>';

        if ($build_array['countbuilderrors'] >= 0) {
            $nerrors = $build_array['countbuilderrors'];
            $totalerrors += $nerrors;
            $xml .= add_XML_value('error', $nerrors);

            $nwarnings = $build_array['countbuildwarnings'];
            $totalwarnings += $nwarnings;
            $xml .= add_XML_value('warning', $nwarnings);
            $duration = $build_array['buildduration'];
            $totalBuildDuration += $duration;
            $xml .= add_XML_value('time', time_difference($duration * 60.0, true));
            $xml .= add_XML_value('timefull', $duration);

            $diff = $build_array['countbuilderrordiffp'];
            if ($diff != 0) {
                $xml .= add_XML_value('nerrordiffp', $diff);
            }
            $diff = $build_array['countbuilderrordiffn'];
            if ($diff != 0) {
                $xml .= add_XML_value('nerrordiffn', $diff);
            }

            $diff = $build_array['countbuildwarningdiffp'];
            if ($diff != 0) {
                $xml .= add_XML_value('nwarningdiffp', $diff);
            }
            $diff = $build_array['countbuildwarningdiffn'];
            if ($diff != 0) {
                $xml .= add_XML_value('nwarningdiffn', $diff);
            }
        }
        $xml .= '</compilation>';

        $xml .= '<configure>';

        $xml .= add_XML_value('error', $build_array['countconfigureerrors']);
        $totalConfigureError += $build_array['countconfigureerrors'];

        $nconfigurewarnings = $build_array['countconfigurewarnings'];
        $xml .= add_XML_value('warning', $nconfigurewarnings);
        $totalConfigureWarning += $nconfigurewarnings;

        $diff = $build_array['countconfigurewarningdiff'];
        if ($diff != 0) {
            $xml .= add_XML_value('warningdiff', $diff);
        }

        if ($build_array['hasconfigurestatus'] != 0) {
            $duration = $build_array['configureduration'];
            $totalConfigureDuration += $duration;
            $xml .= add_XML_value('time', time_difference($duration * 60.0, true));
            $xml .= add_XML_value('timefull', $duration);
        }
        $xml .= '</configure>';

        if ($build_array['hastest'] != 0) {
            $xml .= '<test>';

            $nnotrun = $build_array['counttestsnotrun'];

            if ($build_array['counttestsnotrundiffp'] != 0) {
                $xml .= add_XML_value('nnotrundiffp', $build_array['counttestsnotrundiffp']);
            }
            if ($build_array['counttestsnotrundiffn'] != 0) {
                $xml .= add_XML_value('nnotrundiffn', $build_array['counttestsnotrundiffn']);
            }

            $nfail = $build_array['counttestsfailed'];

            if ($build_array['counttestsfaileddiffp'] != 0) {
                $xml .= add_XML_value('nfaildiffp', $build_array['counttestsfaileddiffp']);
            }
            if ($build_array['counttestsfaileddiffn'] != 0) {
                $xml .= add_XML_value('nfaildiffn', $build_array['counttestsfaileddiffn']);
            }

            $npass = $build_array['counttestspassed'];

            if ($build_array['counttestspasseddiffp'] != 0) {
                $xml .= add_XML_value('npassdiffp', $build_array['counttestspasseddiffp']);
            }
            if ($build_array['counttestspasseddiffn'] != 0) {
                $xml .= add_XML_value('npassdiffn', $build_array['counttestspasseddiffn']);
            }

            if ($project_array['showtesttime'] == 1) {
                $xml .= add_XML_value('timestatus', $build_array['countteststimestatusfailed']);

                if ($build_array['countteststimestatusfaileddiffp'] != 0) {
                    $xml .= add_XML_value('ntimediffp', $build_array['countteststimestatusfaileddiffp']);
                }
                if ($build_array['countteststimestatusfaileddiffn'] != 0) {
                    $xml .= add_XML_value('ntimediffn', $build_array['countteststimestatusfaileddiffn']);
                }
            }

            $totalnotrun += $nnotrun;
            $totalfail += $nfail;
            $totalpass += $npass;

            $xml .= add_XML_value('notrun', $nnotrun);
            $xml .= add_XML_value('fail', $nfail);
            $xml .= add_XML_value('pass', $npass);

            $duration = $build_array['testsduration'];
            $totalTestsDuration += $duration;
            $xml .= add_XML_value('time', time_difference($duration * 60.0, true));
            $xml .= add_XML_value('timefull', $duration);

            $xml .= '</test>';
        }

        $starttimestamp = strtotime($build_array['starttime'] . ' UTC');
        $submittimestamp = strtotime($build_array['submittime'] . ' UTC');
        $xml .= add_XML_value('builddatefull', $starttimestamp); // use the default timezone

        // If the data is more than 24h old then we switch from an elapsed to a normal representation
        if (time() - $starttimestamp < 86400) {
            $xml .= add_XML_value('builddate', date(FMT_DATETIMEDISPLAY, $starttimestamp)); // use the default timezone
            $xml .= add_XML_value('builddateelapsed', time_difference(time() - $starttimestamp, false, 'ago')); // use the default timezone
        } else {
            $xml .= add_XML_value('builddateelapsed', date(FMT_DATETIMEDISPLAY, $starttimestamp)); // use the default timezone
            $xml .= add_XML_value('builddate', time_difference(time() - $starttimestamp, false, 'ago')); // use the default timezone
        }
        $xml .= add_XML_value('submitdate', date(FMT_DATETIMEDISPLAY, $submittimestamp));// use the default timezone
        $xml .= '</build>';

        // Coverage
        //

        // Determine if this is a parent build with no actual coverage of its own.
        $linkToChildCoverage = false;
        if ($countchildren > 0) {
            $countChildrenResult = pdo_single_row_query(
                'SELECT count(fileid) AS nfiles FROM coverage
         WHERE buildid=' . qnum($buildid));
            if ($countChildrenResult['nfiles'] == 0) {
                $linkToChildCoverage = true;
            }
        }

        $coverages = pdo_query("SELECT * FROM coveragesummary WHERE buildid='$buildid'");
        while ($coverage_array = pdo_fetch_array($coverages)) {
            $xml .= '<coverage>';
            $xml .= '  <site>' . $build_array['sitename'] . '</site>';
            $xml .= '  <buildname>' . $build_array['name'] . '</buildname>';
            $xml .= '  <buildid>' . $build_array['id'] . '</buildid>';
            if ($linkToChildCoverage) {
                $xml .= add_XML_value('childlink', "$child_builds_hyperlink#Coverage");
            }

            $percent = round(
                compute_percentcoverage($coverage_array['loctested'],
                    $coverage_array['locuntested']), 2);

            $coverageThreshold = $project_array['coveragethreshold'];
            if ($build_array['subprojectgroup']) {
                $groupId = $build_array['subprojectgroup'];
                $coverageThreshold =
                    $subproject_groups[$groupId]->GetCoverageThreshold();
                $subproject_group_coverage[$groupId]['tested'] +=
                    $coverage_array['loctested'];
                $subproject_group_coverage[$groupId]['untested'] +=
                    $coverage_array['locuntested'];
                $xml .= "  <group>$groupId</group>";
            }

            $xml .= '  <percentage>' . $percent . '</percentage>';
            $xml .= '  <percentagegreen>' . $coverageThreshold . '</percentagegreen>';
            $xml .= '  <percentageyellow>' . $coverageThreshold * 0.7 . '</percentageyellow>';
            $xml .= '  <fail>' . $coverage_array['locuntested'] . '</fail>';
            $xml .= '  <pass>' . $coverage_array['loctested'] . '</pass>';

            // Compute the diff
            $coveragediff = pdo_query("SELECT * FROM coveragesummarydiff WHERE buildid='$buildid'");
            if (pdo_num_rows($coveragediff) > 0) {
                $coveragediff_array = pdo_fetch_array($coveragediff);
                $loctesteddiff = $coveragediff_array['loctested'];
                $locuntesteddiff = $coveragediff_array['locuntested'];
                @$previouspercent = round(($coverage_array['loctested'] - $loctesteddiff) / ($coverage_array['loctested'] - $loctesteddiff + $coverage_array['locuntested'] - $locuntesteddiff) * 100, 2);
                $percentdiff = round($percent - $previouspercent, 2);
                $xml .= '<percentagediff>' . $percentdiff . '</percentagediff>';
                $xml .= '<faildiff>' . $locuntesteddiff . '</faildiff>';
                $xml .= '<passdiff>' . $loctesteddiff . '</passdiff>';
            }

            $starttimestamp = strtotime($build_array['starttime'] . ' UTC');
            $xml .= add_XML_value('datefull', $starttimestamp); // use the default timezone

            // If the data is more than 24h old then we switch from an elapsed to a normal representation
            if (time() - $starttimestamp < 86400) {
                $xml .= add_XML_value('date', date(FMT_DATETIMEDISPLAY, $starttimestamp)); // use the default timezone
                $xml .= add_XML_value('dateelapsed', time_difference(time() - $starttimestamp, false, 'ago')); // use the default timezone
            } else {
                $xml .= add_XML_value('dateelapsed', date(FMT_DATETIMEDISPLAY, $starttimestamp)); // use the default timezone
                $xml .= add_XML_value('date', time_difference(time() - $starttimestamp, false, 'ago')); // use the default timezone
            }

            // Are there labels for this build?
            //
            if (!empty($labels_array)) {
                $xml .= '<labels>';
                foreach ($labels_array as $label) {
                    $xml .= add_XML_value('label', $label);
                }
                $xml .= '</labels>';
            }

            $xml .= '</coverage>';
        }

        // Dynamic Analysis
        //
        $dynanalysis = pdo_query("SELECT checker,status FROM dynamicanalysis WHERE buildid='$buildid' LIMIT 1");
        while ($dynanalysis_array = pdo_fetch_array($dynanalysis)) {
            $xml .= '<dynamicanalysis>';
            $xml .= '  <site>' . $build_array['sitename'] . '</site>';
            $xml .= '  <buildname>' . $build_array['name'] . '</buildname>';
            $xml .= '  <buildid>' . $build_array['id'] . '</buildid>';

            $xml .= '  <checker>' . $dynanalysis_array['checker'] . '</checker>';
            $xml .= '  <status>' . $dynanalysis_array['status'] . '</status>';
            $defect = pdo_query("SELECT sum(dd.value) FROM dynamicanalysisdefect AS dd,dynamicanalysis as d
                                              WHERE d.buildid='$buildid' AND dd.dynamicanalysisid=d.id");
            $defectcount = pdo_fetch_array($defect);
            if (!isset($defectcount[0])) {
                $defectcounts = 0;
            } else {
                $defectcounts = $defectcount[0];
            }
            $xml .= '  <defectcount>' . $defectcounts . '</defectcount>';
            $starttimestamp = strtotime($build_array['starttime'] . ' UTC');
            $xml .= add_XML_value('datefull', $starttimestamp); // use the default timezone

            // If the data is more than 24h old then we switch from an elapsed to a normal representation
            if (time() - $starttimestamp < 86400) {
                $xml .= add_XML_value('date', date(FMT_DATETIMEDISPLAY, $starttimestamp)); // use the default timezone
                $xml .= add_XML_value('dateelapsed', time_difference(time() - $starttimestamp, false, 'ago')); // use the default timezone
            } else {
                $xml .= add_XML_value('dateelapsed', date(FMT_DATETIMEDISPLAY, $starttimestamp)); // use the default timezone
                $xml .= add_XML_value('date', time_difference(time() - $starttimestamp, false, 'ago')); // use the default timezone
            }

            // Are there labels for this build?
            //
            if (!empty($labels_array)) {
                $xml .= '<labels>';
                foreach ($labels_array as $label) {
                    $xml .= add_XML_value('label', $label);
                }
                $xml .= '</labels>';
            }

            $xml .= '</dynamicanalysis>';
        }
    }

    if (pdo_num_rows($builds) + count($dynamic_builds) > 0) {
        if (!$filter_sql) {
            $xml .= add_expected_builds($groupid, $currentstarttime, $received_builds);
        }

        $xml .= add_XML_value('totalUpdatedFiles', $totalUpdatedFiles);
        $xml .= add_XML_value('totalUpdateError', $totalUpdateError);
        $xml .= add_XML_value('totalUpdateWarning', $totalUpdateWarning);
        $xml .= add_XML_value('totalUpdateDuration', $totalUpdateDuration);

        $xml .= add_XML_value('totalConfigureDuration', $totalConfigureDuration);
        $xml .= add_XML_value('totalConfigureError', $totalConfigureError);
        $xml .= add_XML_value('totalConfigureWarning', $totalConfigureWarning);

        $xml .= add_XML_value('totalError', $totalerrors);
        $xml .= add_XML_value('totalWarning', $totalwarnings);
        $xml .= add_XML_value('totalBuildDuration', $totalBuildDuration);

        $xml .= add_XML_value('totalNotRun', $totalnotrun);
        $xml .= add_XML_value('totalFail', $totalfail);
        $xml .= add_XML_value('totalPass', $totalpass);
        $xml .= add_XML_value('totalTestsDuration', time_difference($totalTestsDuration * 60.0, true));
        $xml .= '</buildgroup>';
    }

    // generate subproject coverage by group here.
    if (!empty($subproject_groups)) {
        foreach ($subproject_groups as $groupid => $group) {
            $group_cov = $subproject_group_coverage[$groupid];
            $tested = $group_cov['tested'];
            $untested = $group_cov['untested'];
            if ($tested == 0 && $untested == 0) {
                continue;
            }
            $coverage = round($tested / ($tested + $untested) * 100, 2);

            $xml .= '<subprojectgroup>';
            $xml .= add_XML_value('name', $group->GetName());
            $xml .= add_XML_value('id', $group->GetId());
            $xml .= add_XML_value('thresholdgreen', $group->GetCoverageThreshold());
            $xml .= add_XML_value('thresholdyellow', $group->GetCoverageThreshold() * 0.7);
            $xml .= add_XML_value('coverage', $coverage);
            $xml .= add_XML_value('tested', $tested);
            $xml .= add_XML_value('untested', $untested);
            $xml .= '</subprojectgroup>';
        }
    }

    // Fill in the rest of the info
    $prevpos = $previousgroupposition + 1;
    if ($prevpos == 0) {
        $prevpos = 1;
    }

    for ($i = $prevpos; $i <= $lastGroupPosition; $i++) {
        $group = pdo_fetch_array(pdo_query("SELECT g.name,g.id FROM buildgroup AS g,buildgroupposition AS gp WHERE g.id=gp.buildgroupid
                                                                                     AND gp.position='$i' AND g.projectid='$projectid'
                                                                                     AND gp.starttime<'$end_UTCDate' AND (gp.endtime>'$end_UTCDate'  OR gp.endtime='1980-01-01 00:00:00')"));

        $xml .= '<buildgroup>';
        $xml .= add_buildgroup_sortlist($group['name']);
        $xml .= add_XML_value('id', $group['id']);
        $xml .= add_XML_value('name', $group['name']);
        $xml .= add_XML_value('linkname', str_replace(' ', '_', $group['name']));
        if (!$filter_sql) {
            $xml .= add_expected_builds($group['id'], $currentstarttime, $received_builds);
        }
        $xml .= '</buildgroup>';
    }

    $xml .= add_XML_value('enableTestTiming', $project_array['showtesttime']);

    $end = microtime_float();
    $xml .= '<generationtime>' . round($end - $start, 3) . '</generationtime>';
    if (!empty($sitexml)) {
        $xml .= $sitexml;
    }
    $xml .= '</cdash>';
    return $xml;
}

/** Generate the subprojects dashboard */
function generate_subprojects_dashboard_XML($project_instance, $date)
{
    $start = microtime_float();
    $noforcelogin = 1;
    include_once 'config/config.php';
    require_once 'include/pdo.php';
    include 'public/login.php';
    include_once 'models/banner.php';
    include_once 'models/subproject.php';

    $db = pdo_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN", "$CDASH_DB_PASS");
    if (!$db) {
        echo "Error connecting to CDash database server<br>\n";
        return;
    }
    if (!pdo_select_db("$CDASH_DB_NAME", $db)) {
        echo "Error selecting CDash database<br>\n";
        return;
    }

    $Project = $project_instance;
    $projectid = $project_instance->Id;

    $homeurl = make_cdash_url(htmlentities($Project->HomeUrl));

    checkUserPolicy(@$_SESSION['cdash']['loginid'], $projectid);

    $xml = begin_XML_for_XSLT();
    $xml .= '<title>CDash - ' . $Project->Name . '</title>';

    $Banner = new Banner;
    $Banner->SetProjectId(0);
    $text = $Banner->GetText();
    if ($text !== false) {
        $xml .= '<banner>';
        $xml .= add_XML_value('text', $text);
        $xml .= '</banner>';
    }

    $Banner->SetProjectId($projectid);
    $text = $Banner->GetText();
    if ($text !== false) {
        $xml .= '<banner>';
        $xml .= add_XML_value('text', $text);
        $xml .= '</banner>';
    }

    global $CDASH_SHOW_LAST_SUBMISSION;
    if ($CDASH_SHOW_LAST_SUBMISSION) {
        $xml .= '<showlastsubmission>1</showlastsubmission>';
    }

    list($previousdate, $currentstarttime, $nextdate) = get_dates($date, $Project->NightlyTime);

    $svnurl = make_cdash_url(htmlentities($Project->CvsUrl));
    $homeurl = make_cdash_url(htmlentities($Project->HomeUrl));
    $bugurl = make_cdash_url(htmlentities($Project->BugTrackerUrl));
    $googletracker = htmlentities($Project->GoogleTracker);
    $docurl = make_cdash_url(htmlentities($Project->DocumentationUrl));

    // Main dashboard section
    $xml .=
        '<dashboard>
  <datetime>' . date('l, F d Y H:i:s T', time()) . '</datetime>
  <date>' . $date . '</date>
  <unixtimestamp>' . $currentstarttime . '</unixtimestamp>
  <svn>' . $svnurl . '</svn>
  <bugtracker>' . $bugurl . '</bugtracker>
  <googletracker>' . $googletracker . '</googletracker>
  <documentation>' . $docurl . '</documentation>
  <logoid>' . $Project->getLogoID() . '</logoid>
  <projectid>' . $projectid . '</projectid>
  <projectname>' . $Project->Name . '</projectname>
  <projectname_encoded>' . urlencode($Project->Name) . '</projectname_encoded>
  <previousdate>' . $previousdate . '</previousdate>
  <projectpublic>' . $Project->Public . '</projectpublic>
  <nextdate>' . $nextdate . '</nextdate>';

    if (empty($Project->HomeUrl)) {
        $xml .= '<home>index.php?project=' . urlencode($Project->Name) . '</home>';
    } else {
        $xml .= '<home>' . $homeurl . '</home>';
    }

    if ($CDASH_USE_LOCAL_DIRECTORY && file_exists('local/models/proProject.php')) {
        include_once 'local/models/proProject.php';
        $pro = new proProject;
        $pro->ProjectId = $projectid;
        $xml .= '<proedition>' . $pro->GetEdition(1) . '</proedition>';
    }

    if ($currentstarttime > time()) {
        $xml .= '<future>1</future>';
    } else {
        $xml .= '<future>0</future>';
    }
    $xml .= '</dashboard>';

    // Menu definition
    $xml .= '<menu>';
    if (!has_next_date($date, $currentstarttime)) {
        $xml .= add_XML_value('nonext', '1');
    }
    $xml .= '</menu>';

    $beginning_timestamp = $currentstarttime;
    $end_timestamp = $currentstarttime + 3600 * 24;

    $beginning_UTCDate = gmdate(FMT_DATETIME, $beginning_timestamp);
    $end_UTCDate = gmdate(FMT_DATETIME, $end_timestamp);

    // User
    if (isset($_SESSION['cdash']) && isset($_SESSION['cdash']['loginid'])) {
        $xml .= '<user>';
        $userid = $_SESSION['cdash']['loginid'];
        $user2project = pdo_query("SELECT role FROM user2project WHERE userid='$userid' and projectid='$projectid'");
        $user2project_array = pdo_fetch_array($user2project);
        $user = pdo_query('SELECT admin FROM ' . qid('user') . "  WHERE id='$userid'");
        $user_array = pdo_fetch_array($user);
        $xml .= add_XML_value('id', $userid);
        $isadmin = 0;
        if ($user2project_array['role'] > 1 || $user_array['admin']) {
            $isadmin = 1;
        }
        $xml .= add_XML_value('admin', $isadmin);
        $xml .= add_XML_value('projectrole', $user2project_array['role']);
        $xml .= '</user>';
    }

    // Get some information about the project
    $xml .= '<project>';
    $xml .= add_XML_value('nbuilderror', $Project->GetNumberOfErrorBuilds($beginning_UTCDate, $end_UTCDate, true));
    $xml .= add_XML_value('nbuildwarning', $Project->GetNumberOfWarningBuilds($beginning_UTCDate, $end_UTCDate, true));
    $xml .= add_XML_value('nbuildpass', $Project->GetNumberOfPassingBuilds($beginning_UTCDate, $end_UTCDate, true));
    $xml .= add_XML_value('nconfigureerror', $Project->GetNumberOfErrorConfigures($beginning_UTCDate, $end_UTCDate, true));
    $xml .= add_XML_value('nconfigurewarning', $Project->GetNumberOfWarningConfigures($beginning_UTCDate, $end_UTCDate, true));
    $xml .= add_XML_value('nconfigurepass', $Project->GetNumberOfPassingConfigures($beginning_UTCDate, $end_UTCDate, true));
    $xml .= add_XML_value('ntestpass', $Project->GetNumberOfPassingTests($beginning_UTCDate, $end_UTCDate, true));
    $xml .= add_XML_value('ntestfail', $Project->GetNumberOfFailingTests($beginning_UTCDate, $end_UTCDate, true));
    $xml .= add_XML_value('ntestnotrun', $Project->GetNumberOfNotRunTests($beginning_UTCDate, $end_UTCDate, true));
    if (strlen($Project->GetLastSubmission()) == 0) {
        $xml .= add_XML_value('lastsubmission', 'NA');
    } else {
        $xml .= add_XML_value('lastsubmission', $Project->GetLastSubmission());
    }
    $xml .= '</project>';

    // Look for the subproject
    $row = 0;
    $subprojectids = $Project->GetSubProjects();
    $subprojProp = array();
    foreach ($subprojectids as $subprojectid) {
        $SubProject = new SubProject();
        $SubProject->SetId($subprojectid);
        $subprojProp[$subprojectid] = array('name' => $SubProject->GetName());
    }
    $testSubProj = new SubProject();
    $result = $testSubProj->GetNumberOfErrorBuilds($beginning_UTCDate, $end_UTCDate, true);
    if ($result) {
        foreach ($result as $row) {
            $subprojProp[$row['subprojectid']]['nbuilderror'] = $row[1];
        }
    }
    $result = $testSubProj->GetNumberOfWarningBuilds($beginning_UTCDate, $end_UTCDate, true);
    if ($result) {
        foreach ($result as $row) {
            $subprojProp[$row['subprojectid']]['nbuildwarning'] = $row[1];
        }
    }
    $result = $testSubProj->GetNumberOfPassingBuilds($beginning_UTCDate, $end_UTCDate, true);
    if ($result) {
        foreach ($result as $row) {
            $subprojProp[$row['subprojectid']]['nbuildpass'] = $row[1];
        }
    }
    $result = $testSubProj->GetNumberOfErrorConfigures($beginning_UTCDate, $end_UTCDate, true);
    if ($result) {
        foreach ($result as $row) {
            $subprojProp[$row['subprojectid']]['nconfigureerror'] = $row[1];
        }
    }
    $result = $testSubProj->GetNumberOfWarningConfigures($beginning_UTCDate, $end_UTCDate, true);
    if ($result) {
        foreach ($result as $row) {
            $subprojProp[$row['subprojectid']]['nconfigurewarning'] = $row[1];
        }
    }
    $result = $testSubProj->GetNumberOfPassingConfigures($beginning_UTCDate, $end_UTCDate, true);
    if ($result) {
        foreach ($result as $row) {
            $subprojProp[$row['subprojectid']]['nconfigurepass'] = $row[1];
        }
    }
    $result = $testSubProj->GetNumberOfPassingTests($beginning_UTCDate, $end_UTCDate, true);
    if ($result) {
        foreach ($result as $row) {
            $subprojProp[$row['subprojectid']]['ntestpass'] = $row[1];
        }
    }
    $result = $testSubProj->GetNumberOfFailingTests($beginning_UTCDate, $end_UTCDate, true);
    if ($result) {
        foreach ($result as $row) {
            $subprojProp[$row['subprojectid']]['ntestfail'] = $row[1];
        }
    }
    $result = $testSubProj->GetNumberOfNotRunTests($beginning_UTCDate, $end_UTCDate, true);
    if ($result) {
        foreach ($result as $row) {
            $subprojProp[$row['subprojectid']]['ntestnotrun'] = $row[1];
        }
    }
    $reportArray = array('nbuilderror', 'nbuildwarning', 'nbuildpass',
        'nconfigureerror', 'nconfigurewarning', 'nconfigurepass',
        'ntestpass', 'ntestfail', 'ntestnotrun');
    foreach ($subprojectids as $subprojectid) {
        $SubProject = new SubProject();
        $SubProject->SetId($subprojectid);
        $xml .= '<subproject>';
        $xml .= add_XML_value('name', $SubProject->GetName());
        $xml .= add_XML_value('name_encoded', urlencode($SubProject->GetName()));

        foreach ($reportArray as $reportnum) {
            $reportval = array_key_exists($reportnum, $subprojProp[$subprojectid]) ?
                $subprojProp[$subprojectid][$reportnum] : 0;
            $xml .= add_XML_value($reportnum, $reportval);
        }
        if (strlen($SubProject->GetLastSubmission()) == 0) {
            $xml .= add_XML_value('lastsubmission', 'NA');
        } else {
            $xml .= add_XML_value('lastsubmission', $SubProject->GetLastSubmission());
        }
        $xml .= '</subproject>';

        if ($row == 1) {
            $row = 0;
        } else {
            $row = 1;
        }
    }

    $end = microtime_float();
    $xml .= '<generationtime>' . round($end - $start, 3) . '</generationtime>';
    $xml .= '</cdash>';
    return $xml;
}

// Check if we can connect to the database
$db = pdo_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN", "$CDASH_DB_PASS");
if (!$db
    || pdo_select_db("$CDASH_DB_NAME", $db) === false
    || pdo_query('SELECT id FROM ' . qid('user') . ' LIMIT 1', $db) === false
) {
    // redirect to the install.php script
    if ($CDASH_PRODUCTION_MODE) {
        echo 'CDash cannot connect to the database.';
        return;
    } else {
        echo "<script language=\"javascript\">window.location='install.php'</script>";
    }
    return;
}

@$projectname = $_GET['project'];

// If we should not generate any XSL
if (isset($NoXSLGenerate)) {
    return;
}

if (!isset($projectname)) {
    // if the project name is not set we display the table of projects

    $xml = generate_index_table();
    // Now doing the xslt transition
    generate_XSLT($xml, 'indextable');
} else {
    $projectname = htmlspecialchars(pdo_real_escape_string($projectname));
    $projectid = get_project_id($projectname);

    @$date = $_GET['date'];
    if ($date != null) {
        $date = htmlspecialchars(pdo_real_escape_string($date));
    }

    // Check if the project has any subproject
    $Project = new Project();
    $Project->Id = $projectid;
    $Project->Fill();

    $displayProject = false;
    if ((isset($_GET['display']) && $_GET['display'] == 'project') ||
        (isset($_GET['parentid']) && $_GET['parentid'] > 0)
    ) {
        $displayProject = true;
    }

    if (!$displayProject && !isset($_GET['subproject']) && $Project->GetNumberOfSubProjects($date) > 0) {
        $xml = generate_subprojects_dashboard_XML($Project, $date);
        // Now doing the xslt transition
        generate_XSLT($xml, 'indexsubproject');
    } else {
        $xml = generate_main_dashboard_XML($Project, $date);
        // Now doing the xslt transition
        if ($xml) {
            if (isset($_GET['parentid'])) {
                generate_XSLT($xml, 'indexchildren');
            } else {
                generate_XSLT($xml, 'index');
            }
        }
    }
}
