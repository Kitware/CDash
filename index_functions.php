<?php

function get_dynamic_builds($projectid)
  {
  $builds = array();

  // Get the build rules for each dynamic group belonging to this project.
  $rules = pdo_query("
    SELECT b2gr.buildname, b2gr.siteid, b2gr.parentgroupid, bg.id, bg.name,
           bg.type, gp.position
    FROM build2grouprule AS b2gr
    LEFT JOIN buildgroup AS bg ON (bg.id = b2gr.groupid)
    LEFT JOIN buildgroupposition AS gp ON (gp.buildgroupid=bg.id)
    WHERE bg.projectid='$projectid' AND bg.endtime='1980-01-01 00:00:00' AND
          bg.type != 'Daily'");

    if (!$rules)
      {
      echo pdo_error();
      return;
      }

  while ($rule = pdo_fetch_array($rules))
    {
    $buildgroup_name = $rule['name'];
    $buildgroup_id = $rule['id'];
    $buildgroup_position = $rule['position'];
    if ($rule['type'] == 'Latest')
      {
      // optional fields: parentgroupid, site, and build name match.
      // Use these to construct a WHERE clause for our query.
      $where = "";
      $whereClauses = array();
      if (!empty($rule['parentgroupid']))
        {
        $whereClauses[] = "b2g.groupid='" . $rule['parentgroupid'] . "'";
        }
      if (!empty($rule['siteid']))
        {
        $whereClauses[] = "s.id='" . $rule['siteid'] . "'";
        }
      if (!empty($rule['buildname']))
        {
        $whereClauses[] = "b.name LIKE '" . $rule['buildname'] . "'";
        }
      if (!empty($whereClauses))
        {
        $where = "WHERE " . implode($whereClauses, " AND ");
        }

      // We only want the most recent build.
      $order = "ORDER BY b.submittime DESC LIMIT 1";

      // Copied from index.php.
      $sql = "SELECT b.id,b.siteid,b.parentid,
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
              (SELECT count(buildid) FROM errorlog WHERE buildid=b.id) AS nerrorlog,
              (SELECT count(buildid) FROM build2uploadfile WHERE buildid=b.id) AS builduploadfiles
              FROM build AS b
              LEFT JOIN build2group AS b2g ON (b2g.buildid=b.id)
              LEFT JOIN buildgroup AS g ON (g.id=b2g.groupid)
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
              LEFT JOIN label AS l ON (l.id = l2b.labelid) $where $order";
      $build = pdo_single_row_query($sql);
      $build['groupname'] = $buildgroup_name;
      $build['groupid'] = $buildgroup_id;
      $build['position'] = $buildgroup_position;
      $builds[] = $build;
      }
    }
  return $builds;
  }

?>
