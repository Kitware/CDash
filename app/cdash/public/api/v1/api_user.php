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

include_once 'api.php';

class UserAPI extends CDashAPI
{
    /** List Defects */
    private function ListDefects()
    {
        include_once 'include/common.php';
        if (!isset($this->Parameters['project'])) {
            echo 'Project not set';
            return;
        }

        $projectid = get_project_id($this->Parameters['project']);
        if (!is_numeric($projectid) || $projectid <= 0) {
            echo 'Project not found';
            return;
        }

        // We need multiple queries (4 to be exact)
        // First for the build failures
        $users = array();
        $query = pdo_query('SELECT SUM(errors) AS nerrors,SUM(nfiles) AS nfiles,author FROM(
            SELECT b.id,bed.difference_positive AS errors,u.author,
            COUNT(u.author) AS nfiles, COUNT(DISTINCT u.author) AS dauthor
            FROM build2group AS b2g, buildgroup AS bg,updatefile AS u,build2update AS b2u, builderrordiff AS bed, build AS b
            WHERE b.projectid=' . $projectid . " AND u.updateid=b2u.updateid AND b2u.buildid=b.id AND b2g.buildid=b.id AND b2g.groupid=bg.id AND bg.name!='Experimental'
            AND bed.buildid=b.id AND bed.difference_positive>0 AND bed.difference_negative!=bed.difference_positive
            AND b.starttime<NOW()
            GROUP BY b.id,bed.difference_positive,u.author HAVING COUNT(DISTINCT u.author)=1) AS q GROUP BY author");
        echo pdo_error();

        while ($query_array = pdo_fetch_array($query)) {
            $users[$query_array['author']]['builderrors'] = $query_array['nerrors'];
            $users[$query_array['author']]['builderrorsfiles'] = $query_array['nfiles'];
        }

        // Then for the build fixes
        $query = pdo_query('SELECT SUM(fixes) AS nfixes,SUM(nfiles) AS nfiles,author FROM(
            SELECT b.id,bed.difference_positive AS errors,bed.difference_negative AS fixes,u.author,
            COUNT(u.author) AS nfiles, COUNT(DISTINCT u.author) AS dauthor
            FROM build2group AS b2g, buildgroup AS bg,updatefile AS u,build2update AS b2u, builderrordiff AS bed, build AS b
            WHERE b.projectid=' . $projectid . " AND u.updateid=b2u.updateid AND b2u.buildid=b.id AND b2g.buildid=b.id AND b2g.groupid=bg.id AND bg.name!='Experimental'
            AND bed.buildid=b.id AND bed.difference_negative>0 AND bed.difference_positive<bed.difference_negative
            AND b.starttime<NOW()
            GROUP BY b.id,bed.difference_positive,bed.difference_negative,u.author HAVING COUNT(DISTINCT u.author)=1) AS q GROUP BY author");
        echo pdo_error();

        while ($query_array = pdo_fetch_array($query)) {
            $users[$query_array['author']]['buildfixes'] = $query_array['nfixes'];
            $users[$query_array['author']]['buildfixesfiles'] = $query_array['nfiles'];
        }

        // Then for the test failures
        $query = pdo_query('SELECT SUM(testerrors) AS ntesterrors,SUM(nfiles) AS nfiles,author FROM(SELECT b.id, td.difference_positive AS testerrors,
              u.author,COUNT(u.author) AS nfiles, COUNT(DISTINCT u.author) AS dauthor
              FROM build2group AS b2g, buildgroup AS bg,updatefile AS u, build2update AS b2u, build AS b, testdiff AS td
              WHERE b.projectid=' . $projectid . " AND u.updateid=b2u.updateid AND b2u.buildid=b.id AND b2g.buildid=b.id AND b2g.groupid=bg.id AND bg.name!='Experimental'
              AND td.buildid=b.id AND td.difference_positive>0 AND td.type=1
              AND b.starttime<NOW()
              GROUP BY b.id,td.difference_positive,u.author HAVING COUNT(DISTINCT u.author)=1) AS q GROUP BY author");
        echo pdo_error();
        while ($query_array = pdo_fetch_array($query)) {
            $users[$query_array['author']]['testerrors'] = $query_array['ntesterrors'];
            $users[$query_array['author']]['testerrorsfiles'] = $query_array['nfiles'];
        }

        // Then for the test fixes
        $query = pdo_query('SELECT SUM(testfixes) AS ntestfixes,SUM(nfiles) AS nfiles,author FROM(SELECT b.id, td.difference_positive AS testfixes,
              u.author,COUNT(u.author) AS nfiles, COUNT(DISTINCT u.author) AS dauthor
              FROM build2group AS b2g, buildgroup AS bg,updatefile AS u, build2update AS b2u, build AS b, testdiff AS td
              WHERE b.projectid=' . $projectid . " AND u.updateid=b2u.updateid AND b2u.buildid=b.id AND b2g.buildid=b.id AND b2g.groupid=bg.id AND bg.name!='Experimental'
              AND td.buildid=b.id AND td.difference_positive>0 AND td.type=2 AND td.difference_negative=0
              AND b.starttime<NOW()
              GROUP BY b.id,td.difference_positive,u.author HAVING COUNT(DISTINCT u.author)=1) AS q GROUP BY author");
        echo pdo_error();
        while ($query_array = pdo_fetch_array($query)) {
            $users[$query_array['author']]['testfixes'] = $query_array['ntestfixes'];
            $users[$query_array['author']]['testfixesfiles'] = $query_array['nfiles'];
        }

        // Another select for neutral
        $query = pdo_query('SELECT b.id, bed.difference_positive AS errors,
          u.author AS author,count(*) AS nfiles
         FROM build2group AS b2g, buildgroup AS bg,updatefile AS u, build2update AS b2u, build AS b
         LEFT JOIN builderrordiff AS bed ON (bed.buildid=b.id AND difference_positive!=difference_negative)
         LEFT JOIN testdiff AS t ON (t.buildid=b.id)
         WHERE b.projectid=' . $projectid . " AND u.updateid=b2u.updateid AND b2u.buildid=b.id AND b2g.buildid=b.id AND b2g.groupid=bg.id AND bg.name!='Experimental'
         AND bed.difference_positive IS NULL
         AND t.difference_positive IS NULL
         AND b.starttime<NOW() GROUP BY u.author,b.id,bed.difference_positive");
        echo pdo_error();

        while ($query_array = pdo_fetch_array($query)) {
            $users[$query_array['author']]['neutralfiles'] = $query_array['nfiles'];
        }
        return $users;
    }

    /** Run function */
    public function Run()
    {
        switch ($this->Parameters['task']) {
            case 'defects':
                return $this->ListDefects();
        }
    }
}
