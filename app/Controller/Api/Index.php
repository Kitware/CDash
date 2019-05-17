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

use CDash\Config;
use CDash\Database;
use CDash\Model\Build;
use CDash\Model\BuildGroup;
use CDash\Model\Project;

class Index extends ResultsApi
{
    protected $buildGroupName;
    protected $includeSubProjects;
    protected $includedSubProjects;
    protected $excludeSubProjects;
    protected $excludedSubProjects;
    protected $filterSQL;
    protected $labelIds;
    protected $limitSQL;
    protected $numSelectedSubProjects;
    protected $parentId;
    // This array is used to track if expected builds are found or not.
    protected $receivedBuilds;
    protected $subProjectId;
    protected $subProjectPositions;

    public $buildgroupsResponse;
    public $buildStartTimes;
    public $childView;
    public $filterdata;
    public $shareLabelFilters;
    public $siteResponse;
    public $updateType;

    public function __construct(Database $db, Project $project)
    {
        parent::__construct($db, $project);

        $this->buildGroupName = '';
        $this->buildgroupsResponse = [];
        $this->buildStartTimes = [];
        $this->childView = 0;
        $this->filterdata = [];
        $this->shareLabelFilters = false;
        $this->siteResponse = [];
        $this->updateType = '';

        // SubProject filtering.
        $this->includeSubProjects = false;
        $this->includedSubProjects = [];
        $this->excludeSubProjects = false;
        $this->excludedSubProjects = [];
        $this->numSelectedSubProjects = 0;
        $this->selectedSubProjects = '';

        $this->filterSQL = '';
        $this->labelIds = '';
        $this->limitSQL = '';
        $this->parentId = false;
        $this->receivedBuilds = [];
        $this->subProjectId = false;
        $this->subProjectPositions = [];
    }

    public function getFilterData()
    {
        return $this->filterdata;
    }

    public function setFilterData(array $filterdata)
    {
        $this->filterdata = $filterdata;
        $this->filterSQL = $this->filterdata['sql'];
        if ($this->filterdata['limit'] > 0) {
            $this->limitSQL = ' LIMIT ' . $this->filterdata['limit'];
        }
    }

    public function getFilterSQL()
    {
        return $this->filterSQL;
    }

    public function setParentId($parentid)
    {
        $this->parentId = $parentid;
    }

    public function setSubProjectId($subprojectid)
    {
        $this->subProjectId = $subprojectid;
    }

    public function filterOnBuildGroup($buildgroup_name)
    {
        $this->buildGroupName = $buildgroup_name;
    }

    public function getDailyBuilds()
    {
        // Should we query by subproject?
        $subprojectsql = '';
        if (is_numeric($this->subProjectId)) {
            $subprojectsql = ' AND sp2b.subprojectid=' . $this->subProjectId;
        }

        // Default date clause.
        $date_clause = "AND b.starttime < '{$this->endDate}' AND b.starttime >= '{$this->beginDate}' ";
        if ($this->filterdata['hasdateclause']) {
            // If filterdata has a date clause, then cancel this one out.
            $date_clause = '';
        }

        $parent_clause = '';
        if ($this->parentId) {
            // If we have a parentid, then we should only show children of that build.
            // Date becomes irrelevant in this case.
            $parent_clause = 'AND (b.parentid = ' . qnum($this->parentId) . ') ';
            $date_clause = '';
        } elseif (empty($subprojectsql)) {
            // Only show builds that are not children.
            $parent_clause = 'AND (b.parentid = -1 OR b.parentid = 0) ';
        }

        // If the user is logged in we display if the build has some changes for them.
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

        $buildgroup_clause = '';
        if ($this->buildGroupName) {
            $buildgroup_clause = " AND g.name = '{$this->buildGroupName}' ";
        }

        $sql = $this->getIndexQuery($userupdatesql);
        $sql .= "WHERE b.projectid='{$this->project->Id}' AND g.type='Daily'
            $parent_clause $date_clause $subprojectsql $this->filterSQL
            $buildgroup_clause $this->limitSQL";

        // We shouldn't get any builds for group that have been deleted (otherwise something is wrong)
        $builds = pdo_query($sql);

        // Log any errors.
        $pdo_error = pdo_error();
        if (strlen($pdo_error) > 0) {
            add_log('SQL error: ' . $pdo_error, 'Index.php', LOG_ERR);
        }

        // Gather up results from this query.
        $build_data = [];
        while ($build_row = pdo_fetch_array($builds)) {
            $build_data[] = $build_row;
        }
        return $build_data;
    }

    public function getDynamicBuilds()
    {
        $builds = [];

        // Get the build rules for each dynamic group belonging to this project.
        $stmt = $this->db->prepare("
            SELECT b2gr.buildname, b2gr.siteid, b2gr.parentgroupid, bg.id,
                   bg.name, bg.type, gp.position
            FROM build2grouprule AS b2gr
            LEFT JOIN buildgroup AS bg ON (bg.id = b2gr.groupid)
            LEFT JOIN buildgroupposition AS gp ON (gp.buildgroupid = bg.id)
            WHERE bg.projectid = :projectid AND
                  bg.endtime = :begin_epoch AND
                  bg.type != 'Daily' AND
                  b2gr.starttime < :end_date AND
                  (b2gr.endtime = :begin_epoch OR b2gr.endtime > :end_date)");
        $query_params = [
            ':projectid' => $this->project->Id,
            ':begin_epoch' => self::BEGIN_EPOCH,
            ':end_date' => $this->endDate
        ];
        $this->db->execute($stmt, $query_params);

        while ($rule = $stmt->fetch()) {
            $buildgroup_name = $rule['name'];
            if ($this->buildGroupName && $this->buildGroupName != $buildgroup_name) {
                continue;
            }
            $buildgroup_id = $rule['id'];
            $buildgroup_position = $rule['position'];
            if ($rule['type'] == 'Latest') {
                // optional fields: parentgroupid, site, and build name match.
                // Use these to construct a WHERE clause for our query.
                $where = '';
                $whereClauses = [];
                if (!empty($rule['parentgroupid'])) {
                    $whereClauses[] = "b2g.groupid='" . $rule['parentgroupid'] . "'";
                }
                if (!empty($rule['siteid'])) {
                    $whereClauses[] = "s.id='" . $rule['siteid'] . "'";
                }
                if (!empty($rule['buildname'])) {
                    $whereClauses[] = "b.name LIKE '" . $rule['buildname'] . "'";
                }
                if (!empty($whereClauses)) {
                    $where = 'WHERE ' . implode($whereClauses, ' AND ');
                    $where .= " AND b.starttime<'{$this->endDate}'";
                }

                // We only want the most recent build.
                $order = 'ORDER BY b.submittime DESC LIMIT 1';

                $sql = $this->getIndexQuery();
                $sql .= "$where $this->filterSQL $order";

                $results = pdo_query($sql);
                while ($build = pdo_fetch_array($results)) {
                    if (empty($build)) {
                        continue;
                    }
                    $build['groupname'] = $buildgroup_name;
                    $build['groupid'] = $buildgroup_id;
                    $build['position'] = $buildgroup_position;
                    $builds[] = $build;
                }
            }
        }
        return $builds;
    }

    // Encapsulate this monster query so that it is not duplicated between
    // index.php and get_dynamic_builds.
    public function getIndexQuery($userupdatesql='')
    {
        return
            "SELECT b.id,b.siteid,b.parentid,b.done,b.changeid,b.testduration,
            bu.status AS updatestatus,
            i.osname AS osname,
            bu.starttime AS updatestarttime,
            bu.endtime AS updateendtime,
            bu.nfiles AS countupdatefiles,
            bu.warnings AS countupdatewarnings,
            bu.revision,
            b.configureduration,
            be_diff.difference_positive AS countbuilderrordiffp,
            be_diff.difference_negative AS countbuilderrordiffn,
            bw_diff.difference_positive AS countbuildwarningdiffp,
            bw_diff.difference_negative AS countbuildwarningdiffn,
            ce_diff.difference AS countconfigurewarningdiff,
            btt.time AS testtime,
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
            $userupdatesql
                s.name AS sitename,
            s.outoforder AS siteoutoforder,
            b.stamp,b.name,b.type,b.generator,b.starttime,b.endtime,b.submittime,
            b.configureerrors AS countconfigureerrors,
            b.configurewarnings AS countconfigurewarnings,
            b.builderrors AS countbuilderrors,
            b.buildwarnings AS countbuildwarnings,
            b.buildduration,
            b.testnotrun AS counttestsnotrun,
            b.testfailed AS counttestsfailed,
            b.testpassed AS counttestspassed,
            b.testtimestatusfailed AS countteststimestatusfailed,
            cs.loctested, cs.locuntested,
            csd.loctested AS loctesteddiff, csd.locuntested AS locuntesteddiff,
            das.checker, das.numdefects,
            sp.id AS subprojectid,
            sp.groupid AS subprojectgroup,
            sp.position AS subprojectposition,
            g.name AS groupname,gp.position,g.id AS groupid,
            (SELECT count(buildid) FROM label2build WHERE buildid=b.id) AS numlabels,
            (SELECT count(buildid) FROM build2uploadfile WHERE buildid=b.id) AS builduploadfiles
                FROM build AS b
                LEFT JOIN build2group AS b2g ON (b2g.buildid=b.id)
                LEFT JOIN buildgroup AS g ON (g.id=b2g.groupid)
                LEFT JOIN buildgroupposition AS gp ON (gp.buildgroupid=g.id)
                LEFT JOIN site AS s ON (s.id=b.siteid)
                LEFT JOIN build2update AS b2u ON (b2u.buildid=b.id)
                LEFT JOIN buildupdate AS bu ON (b2u.updateid=bu.id)
                LEFT JOIN buildinformation AS i ON (i.buildid=b.id)
                LEFT JOIN coveragesummary AS cs ON (cs.buildid=b.id)
                LEFT JOIN coveragesummarydiff AS csd ON (csd.buildid=b.id)
                LEFT JOIN dynamicanalysissummary AS das ON (das.buildid=b.id)
                LEFT JOIN builderrordiff AS be_diff ON (be_diff.buildid=b.id AND be_diff.type=0)
                LEFT JOIN builderrordiff AS bw_diff ON (bw_diff.buildid=b.id AND bw_diff.type=1)
                LEFT JOIN configureerrordiff AS ce_diff ON (ce_diff.buildid=b.id AND ce_diff.type=1)
                LEFT JOIN buildtesttime AS btt ON (btt.buildid=b.id)
                LEFT JOIN testdiff AS tnotrun_diff ON (tnotrun_diff.buildid=b.id AND tnotrun_diff.type=0)
                LEFT JOIN testdiff AS tfailed_diff ON (tfailed_diff.buildid=b.id AND tfailed_diff.type=1)
                LEFT JOIN testdiff AS tpassed_diff ON (tpassed_diff.buildid=b.id AND tpassed_diff.type=2)
                LEFT JOIN testdiff AS tstatusfailed_diff ON (tstatusfailed_diff.buildid=b.id AND tstatusfailed_diff.type=3)
                LEFT JOIN subproject2build AS sp2b ON (sp2b.buildid = b.id)
                LEFT JOIN subproject as sp ON (sp2b.subprojectid = sp.id)";
    }

    public function populateBuildRow($build_row)
    {
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
        // Fields that we add within this function:
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
            $build_row['testduration'] = 0;
        } else {
            $build_row['testduration'] = round($build_row['testduration'], 1);
        }

        return $build_row;
    }

    public function beginResponseForBuildGroup(BuildGroup $buildgroup)
    {
        $buildgroup_response = [];
        $groupname = $buildgroup->GetName();
        $this->receivedBuilds[$groupname] = [];

        $buildgroup_response['id'] = $buildgroup->GetId();
        $buildgroup_response['name'] = $groupname;
        $buildgroup_response['linkname'] = urlencode($groupname);
        $buildgroup_response['position'] = $buildgroup->GetPosition();

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

        $buildgroup_response['builds'] = [];
        $this->buildgroupsResponse[] = $buildgroup_response;
    }

    public function generateBuildResponseFromRow($build_array)
    {
        $groupid = $build_array['groupid'];

        // Find the buildgroup array for this build.
        $i = -1;
        for ($j = 0; $j < count($this->buildgroupsResponse); $j++) {
            if ($this->buildgroupsResponse[$j]['id'] == $groupid) {
                $i = $j;
                break;
            }
        }
        if ($i == -1) {
            add_log("BuildGroup '$groupid' not found for build #" . $build_array['id'],
                    __FILE__ . ':' . __LINE__ . ' - ' . __FUNCTION__,
                    LOG_WARNING);
            return false;
        }

        $groupname = $this->buildgroupsResponse[$i]['name'];

        $build_response = [];

        $this->receivedBuilds[$groupname][] =
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
        $selected_proc_time = 0;

        if ($numchildren > 0) {
            $child_builds_hyperlink =
                $this->getChildBuildsHyperlink($build_array['id']);
            $build_response['multiplebuildshyperlink'] = $child_builds_hyperlink;
            $this->buildgroupsResponse[$i]['hasparentbuilds'] = true;

            // Compute selected (excluded or included) SubProject results.
            if ($this->selectedSubProjects) {
                $select_query = "
                    SELECT configureerrors, configurewarnings, configureduration,
                           builderrors, buildwarnings, buildduration,
                           b.starttime, b.endtime, testnotrun, testfailed, testpassed,
                           testduration, sb.name, btt.time AS testtime
                               FROM build AS b
                               INNER JOIN subproject2build AS sb2b ON (b.id = sb2b.buildid)
                               INNER JOIN subproject AS sb ON (sb2b.subprojectid = sb.id)
                               LEFT JOIN buildtesttime AS btt ON (btt.buildid = b.id)
                               WHERE b.parentid=$buildid
                               AND sb.name IN $this->selectedSubProjects";
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
                    $selected_proc_time +=
                        max(0, $select_array['testtime']);
                }
            }
        } else {
            $this->buildgroupsResponse[$i]['hasnormalbuilds'] = true;
        }

        if (strtolower($build_array['type']) == 'continuous') {
            $this->buildgroupsResponse[$i]['sorttype'] = 'time';
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
                $this->project->CvsViewerType === 'github') {
            $changelink = $this->project->CvsUrl . '/pull/' .
                $build_array['changeid'];
            $changeicon = 'img/Octocat.png';
        }

        if (isset($_GET['parentid'])) {
            if (empty($this->siteResponse)) {
                $this->siteResponse['site'] = $build_array['sitename'];
                $this->siteResponse['siteoutoforder'] = $build_array['siteoutoforder'];
                $this->siteResponse['siteid'] = $siteid;
                $this->siteResponse['buildname'] = $build_array['name'];
                $this->siteResponse['buildplatform'] = $buildplatform;
                $this->siteResponse['generator'] = $build_array['generator'];
                if (!is_null($changelink)) {
                    $this->siteResponse['changelink'] = $changelink;
                    $this->siteResponse['changeicon'] = $changeicon;
                }
            }
        } else {
            $build_response['site'] = $build_array['sitename'];
            $build_response['siteoutoforder'] = $build_array['siteoutoforder'];
            $build_response['siteid'] = $siteid;
            $build_response['buildname'] = $build_array['name'];
            $build_response['buildplatform'] = $buildplatform;
            $build_response['uploadfilecount'] = $build_array['builduploadfiles'];
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
        if ($this->numSelectedSubProjects > 0) {
            // Special handling for whitelisting/blacklisting SubProjects.
            if ($this->includeSubProjects) {
                $num_labels = 0;
            }
            $labels_result = pdo_query($label_query);
            while ($label_row = pdo_fetch_array($labels_result)) {
                // Whitelist case
                if ($this->includeSubProjects &&
                        in_array($label_row['text'], $this->includedSubProjects)
                   ) {
                    $num_labels++;
                    $build_labels[] = $label_row['text'];
                }
                // Blacklist case
                if ($this->excludeSubProjects) {
                    if (in_array($label_row['text'], $this->excludedSubProjects)) {
                        $num_labels--;
                    } else {
                        $build_labels[] = $label_row['text'];
                    }
                }
            }

            if ($num_labels === 0) {
                // Skip this build entirely if none of its SubProjects
                // survived filtering.
                return false;
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
            // Keep track of all positions encountered so we can normalize later.
            if (!in_array($build_array['subprojectposition'], $this->subProjectPositions)) {
                $this->subProjectPositions[] = $build_array['subprojectposition'];
            }
        } else {
            $build_response['position'] = 0;
        }

        // We maintain a list of distinct build start times when viewing
        // the children of a specified parent build.
        // We do this because our view differs slightly if the subprojects
        // were built one at a time vs. all at once.
        if ($this->childView == 1 &&
                !in_array($build_array['starttime'], $this->buildStartTimes)) {
            $this->buildStartTimes[] = $build_array['starttime'];
        }

        // Calculate this build's total duration.
        $duration = strtotime($build_array['endtime']) -
            strtotime($build_array['starttime']);
        $build_response['time'] = time_difference($duration, true);
        $build_response['timefull'] = $duration;

        $update_response = array();

        $countupdatefiles = $build_array['countupdatefiles'];
        $this->buildgroupsResponse[$i]['numupdatedfiles'] += $countupdatefiles;

        $build_response['hasupdate'] = false;
        if (!empty($build_array['updatestarttime'])) {
            $build_response['hasupdate'] = true;

            // Record what type of update to report for this project.
            if (!$this->updateType) {
                if (!empty($build_array['revision'])) {
                    $this->updateType = 'Revision';
                } else {
                    $this->updateType = 'Files';
                }
            }
            if ($this->updateType === 'Revision') {
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
                $this->buildgroupsResponse[$i]['numupdateerror'] += 1;
            } else {
                $update_response['errors'] = 0;

                if ($build_array['countupdatewarnings'] > 0) {
                    $update_response['warning'] = 1;
                    $this->buildgroupsResponse[$i]['numupdatewarning'] += 1;
                }
            }

            $duration = $build_array['updateduration'];
            $update_response['time'] = time_difference($duration * 60.0, true);
            $update_response['timefull'] = $duration;
            $this->buildgroupsResponse[$i]['updateduration'] += $duration;
            $this->buildgroupsResponse[$i]['hasupdatedata'] = true;
            $build_response['update'] = $update_response;
        }

        $compilation_response = array();

        if ($build_array['countbuilderrors'] >= 0) {
            if ($this->includeSubProjects && $this->childView != 1) {
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
            $this->buildgroupsResponse[$i]['numbuilderror'] += $nerrors;

            $compilation_response['warning'] = $nwarnings;
            $this->buildgroupsResponse[$i]['numbuildwarning'] += $nwarnings;

            $compilation_response['time'] = time_difference($buildduration, true);
            $compilation_response['timefull'] = $buildduration;

            if ($this->childView == 1 || (!$this->includeSubProjects && !$this->excludeSubProjects)) {
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
            $this->buildgroupsResponse[$i]['hascompilationdata'] = true;
        }

        $build_response['hasconfigure'] = false;
        if ($build_array['hasconfigure'] != 0) {
            $build_response['hasconfigure'] = true;
            $configure_response = array();

            if ($this->includeSubProjects && $this->childView != 1) {
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
            $this->buildgroupsResponse[$i]['numconfigureerror'] += $nconfigureerrors;

            $configure_response['warning'] = $nconfigurewarnings;
            $this->buildgroupsResponse[$i]['numconfigurewarning'] += $nconfigurewarnings;

            if (!$this->includeSubProjects && !$this->excludeSubProjects) {
                $configure_response['warningdiff'] =
                    $build_array['countconfigurewarningdiff'];
            }

            $configure_response['time'] =
                time_difference($configureduration, true);
            $configure_response['timefull'] = $configureduration;

            $build_response['configure'] = $configure_response;
            $this->buildgroupsResponse[$i]['hasconfiguredata'] = true;
            $this->buildgroupsResponse[$i]['configureduration'] += $configureduration;
        }

        $build_response['hastest'] = false;
        if ($build_array['hastest'] != 0) {
            $build_response['hastest'] = true;
            $this->buildgroupsResponse[$i]['hastestdata'] = true;
            $test_response = array();

            if ($this->includeSubProjects && $this->childView != 1) {
                $nnotrun = $selected_tests_not_run;
                $nfail = $selected_tests_failed;
                $npass = $selected_tests_passed;
                $proc_time = $selected_proc_time;
            } else {
                $nnotrun = $build_array['counttestsnotrun'] -
                    $selected_tests_not_run;
                $nfail = $build_array['counttestsfailed'] -
                    $selected_tests_failed;
                $npass = $build_array['counttestspassed'] -
                    $selected_tests_passed;
                $proc_time = $build_array['testtime'] - $selected_proc_time;
            }

            if ($this->childView == 1 || (!$this->includeSubProjects && !$this->excludeSubProjects)) {
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

            if ($this->project->ShowTestTime == 1) {
                $test_response['timestatus'] = $build_array['countteststimestatusfailed'];
                $test_response['ntimediffp'] =
                    $build_array['countteststimestatusfaileddiffp'];
                $test_response['ntimediffn'] =
                    $build_array['countteststimestatusfaileddiffn'];
            }

            if ($this->shareLabelFilters) {
                $label_query_base =
                    "SELECT b2t.status, b2t.newstatus
                    FROM build2test AS b2t
                    INNER JOIN label2test AS l2t ON
                    (l2t.testid=b2t.testid AND l2t.buildid=b2t.buildid)
                    WHERE b2t.buildid = '$buildid' AND
                    l2t.labelid IN $this->labelIds";
                $label_filter_query = $label_query_base . $this->limitSQL;
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

            $this->buildgroupsResponse[$i]['numtestnotrun'] += $nnotrun;
            $this->buildgroupsResponse[$i]['numtestfail'] += $nfail;
            $this->buildgroupsResponse[$i]['numtestpass'] += $npass;

            $testduration = $build_array['testduration'];
            $test_response['time'] = time_difference($testduration, true);
            $test_response['timefull'] = $testduration;
            $this->buildgroupsResponse[$i]['testduration'] += $testduration;

            $test_response['procTime'] = time_difference($proc_time, true);
            $test_response['procTimeFull'] = $proc_time;

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

        if ($this->includeSubProjects || $this->excludeSubProjects) {
            // Check if this build should be filtered out now that its
            // numbers have been updated by the SubProject include/exclude
            // filter.
            if (!build_survives_filters($build_response, $this->filterdata['filters'], $this->filterdata['filtercombine'])) {
                return false;
            }
        }

        if ($build_array['name'] != 'Aggregate Coverage') {
            $this->buildgroupsResponse[$i]['builds'][] = $build_response;
        }

        return $build_response;
    }

    // Get a link to a page showing the children of a given parent build.
    private function getChildBuildsHyperlink($parentid)
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
        $num_filters = 0;
        foreach ($this->filterdata['filters'] as $filter) {
            if (array_key_exists('filters', $filter)) {
                $num_filters++;
                $num_subfilters = 0;
                $existing_subfilter_params = '';
                foreach ($filter['filters'] as $subfilter) {
                    if ($this->preserveFilterForChildBuild($subfilter)) {
                        $num_subfilters++;
                        $existing_subfilter_params .=
                            "&field{$num_filters}field{$num_subfilters}={$subfilter['field']}" .
                            "&field{$num_filters}compare{$num_subfilters}={$subfilter['compare']}" .
                            "&field{$num_filters}value{$num_subfilters}=" . htmlspecialchars($subfilter['value']);
                    }
                }
                if ($num_subfilters > 0) {
                    $existing_filter_params .= "&field{$num_filters}=block&field{$num_filters}count={$num_subfilters}";
                    $existing_filter_params .= $existing_subfilter_params;
                } else {
                    // No subfilters remain. The whole block should be removed.
                    $num_filters--;
                }
                continue;
            }
            if ($this->preserveFilterForChildBuild($filter)) {
                $num_filters++;
                $existing_filter_params .=
                    '&field' . $num_filters . '=' . $filter['field'] .
                    '&compare' . $num_filters . '=' . $filter['compare'] .
                    '&value' . $num_filters . '=' . htmlspecialchars($filter['value']);
            }
        }
        if ($num_filters > 0) {
            $existing_filter_params =
                "&filtercount=$num_filters&showfilters=1$existing_filter_params";

            if (!empty($this->filterdata['filtercombine'])) {
                $existing_filter_params .=
                    '&filtercombine=' . $this->filterdata['filtercombine'];
            }
        }

        // Construct & return our URL.
        $url = "$baseurl&parentid=$parentid";
        $url .= $existing_filter_params;
        return $url;
    }

    // Return true if a filter should be passed from parent to child view,
    // false otherwise.
    private function preserveFilterForChildBuild($filter)
    {
        if ($filter['field'] != 'buildname' &&
                $filter['field'] != 'site' &&
                $filter['field'] != 'stamp' &&
                $filter['compare'] != 0 &&
                $filter['compare'] != 20 &&
                $filter['compare'] != 40 &&
                $filter['compare'] != 60 &&
                $filter['compare'] != 80) {
            return true;
        }
        return false;
    }

    // Find expected builds that haven't submitted yet.
    public function addExpectedBuilds($i, $currentstarttime)
    {
        $config = Config::getInstance();

        if (isset($_GET['parentid'])) {
            // Don't add expected builds when viewing a single subproject result.
            return;
        }

        $groupid = $this->buildgroupsResponse[$i]['id'];
        $groupname = $this->buildgroupsResponse[$i]['name'];
        if ($this->buildGroupName && $this->buildGroupName != $groupname) {
            // When viewing results from a single build group don't check for
            // expected builds from other groups.
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
            if (array_search($key, $this->receivedBuilds[$groupname]) === false) {
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
                if ($config->get('CDASH_DB_TYPE') == 'pgsql') {
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

    // Check if we should be excluding some SubProjects from our
    // build results.
    public function checkForSubProjectFilters()
    {
        $filter_on_labels = false;
        $filters = [];
        foreach ($this->filterdata['filters'] as $filter) {
            if (array_key_exists('filters', $filter)) {
                foreach ($filter['filters'] as $subfilter) {
                    $filters[] = $subfilter;
                }
            } else {
                $filters[] = $filter;
            }
        }
        foreach ($filters as $filter) {
            if ($filter['field'] == 'subprojects') {
                if ($filter['compare'] == 92) {
                    $this->excludedSubProjects[] = $filter['value'];
                } elseif ($filter['compare'] == 93) {
                    $this->includedSubProjects[] = $filter['value'];
                }
            } elseif ($filter['field'] == 'label') {
                $filter_on_labels = true;
            }
        }
        unset($filters);
        if ($filter_on_labels && $this->project->ShareLabelFilters) {
            $this->shareLabelFilters = true;
            $label_ids_array = get_label_ids_from_filterdata($this->filterdata);
            $this->labelIds = '(' . implode(', ', $label_ids_array) . ')';
        }

        // Include takes precedence over exclude.
        if (!empty($this->includedSubProjects)) {
            $this->numSelectedSubProjects = count($this->includedSubProjects);
            $this->selectedSubProjects = implode("','", $this->includedSubProjects);
            $this->selectedSubProjects = "('" . $this->selectedSubProjects . "')";
            $this->includeSubProjects = true;
        } elseif (!empty($this->excludedSubProjects)) {
            $this->numSelectedSubProjects = count($this->excludedSubProjects);
            $this->selectedSubProjects = implode("','", $this->excludedSubProjects);
            $this->selectedSubProjects = "('" . $this->selectedSubProjects . "')";
            $this->excludeSubProjects = true;
        }
    }

    // Normalize subproject order so it's always 1 to N with no gaps.
    public function normalizeSubProjectOrder()
    {
        sort($this->subProjectPositions);
        for ($i = 0; $i < count($this->buildgroupsResponse); $i++) {
            for ($j = 0; $j < count($this->buildgroupsResponse[$i]['builds']); $j++) {
                $idx = array_search($this->buildgroupsResponse[$i]['builds'][$j]['position'], $this->subProjectPositions);
                $this->buildgroupsResponse[$i]['builds'][$j]['position'] = $idx + 1;
            }
        }
    }

    // Record next & previous dates (if any).
    public function determineNextPrevious(&$response, $base_url)
    {
        // Next & previous are handled separately when we're viewing the
        // results of a single parent build.
        if ($this->childView) {
            return;
        }

        // Use the project model to get the bounds of the current testing day.
        list($beginningOfDay, $endOfDay) =
            $this->project->ComputeTestingDayBounds($this->date);

        // Query the database to find the previous testing day
        // that has build results.
        $query_params = [
            ':projectid' => $this->project->Id,
            ':time'      => $beginningOfDay
        ];

        // Only search for builds from a certain group when buildGroupName is set.
        $extra_join = '';
        $extra_where = '';
        if ($this->buildGroupName) {
            $query_params[':groupname'] = $this->buildGroupName;
            $extra_join = '
                JOIN build2group b2g ON b2g.buildid = b.id
                JOIN buildgroup bg ON bg.id = b2g.groupid';
            $extra_where = 'AND bg.name = :groupname';
        }

        $sql = "SELECT b.starttime FROM build b
                $extra_join
                WHERE b.projectid = :projectid
                AND b.starttime < :time
                $extra_where
                ORDER BY starttime DESC LIMIT 1";
        $previous_stmt = $this->db->prepare($sql);
        $this->db->execute($previous_stmt, $query_params);
        $starttime = $previous_stmt->fetchColumn();
        if ($starttime) {
            $previous_date = Build::GetTestingDate($starttime, strtotime($this->project->NightlyTime));
            $response['menu']['previous'] = "$base_url&date=$previous_date";
        } else {
            $response['menu']['previous'] = false;
        }

        // Find the next testing day that has build results.
        $sql = "SELECT b.starttime FROM build b
                $extra_join
                WHERE b.projectid = :projectid
                AND b.starttime >= :time
                $extra_where
                ORDER BY starttime LIMIT 1";
        $next_stmt = $this->db->prepare($sql);
        $query_params[':time'] = $endOfDay;
        $this->db->execute($next_stmt, $query_params);
        $starttime = $next_stmt->fetchColumn();
        if ($starttime) {
            $next_date = Build::GetTestingDate($starttime, strtotime($this->project->NightlyTime));
            $response['menu']['next'] = "$base_url&date=$next_date";
        } else {
            $response['menu']['next'] = false;
        }

        // Add an extra URL argument to menu navigation items when subprojectid is set.
        if ($this->subProjectId) {
            $subproject_name = $response['subprojectname'];
            $extraurl = '&subproject=' . urlencode($subproject_name);
            foreach (['previous', 'next', 'current'] as $item) {
                if ($response['menu'][$item]) {
                    $response['menu'][$item] .= $extraurl;
                }
            }
        }
    }
}
