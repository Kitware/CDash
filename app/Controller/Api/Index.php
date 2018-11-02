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
use CDash\Model\Project;

class Index
{
    const BEGIN_EPOCH = '1980-01-01 00:00:00';

    private $project;

    public function __construct(Database $db, Project $project)
    {
        $this->db = $db;
        $this->project = $project;
        $this->endDate = self::BEGIN_EPOCH;
    }

    public function setEndDate($end_date)
    {
        $this->endDate = $end_date;
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
                  bg.type != 'Daily'");
        $query_params = [
            ':projectid' => $this->project->Id,
            ':begin_epoch' => self::BEGIN_EPOCH
        ];
        $this->db->execute($stmt, $query_params);

        while ($rule = $stmt->fetch()) {
            $buildgroup_name = $rule['name'];
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
                $sql .= "$where $order";
                $build = pdo_single_row_query($sql);
                if (empty($build)) {
                    continue;
                }
                $build['groupname'] = $buildgroup_name;
                $build['groupid'] = $buildgroup_id;
                $build['position'] = $buildgroup_position;
                $builds[] = $build;
            }
        }
        return $builds;
    }

    // Encapsulate this monster query so that it is not duplicated between
    // index.php and get_dynamic_builds.
    public function getIndexQuery($userupdatesql='')
    {
        return
            "SELECT b.id,b.siteid,b.parentid,b.done,b.changeid,
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
            $time_array = pdo_fetch_array(pdo_query("SELECT SUM(time) FROM build2test WHERE buildid='$buildid'"));
            $build_row['testduration'] = round($time_array[0], 1);
        } else {
            $build_row['testduration'] = round($build_row['testduration'], 1);
        }

        return $build_row;
    }
}
