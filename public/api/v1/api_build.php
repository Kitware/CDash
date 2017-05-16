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

class BuildAPI extends CDashAPI
{
    /** Return the defects: builderrors, buildwarnings, testnotrun, testfailed. */
    private function ListDefects()
    {
        include_once 'include/common.php';
        global $CDASH_DB_TYPE;

        if (!isset($this->Parameters['project'])) {
            echo 'Project not set';
            return;
        }

        $projectid = get_project_id($this->Parameters['project']);
        if (!is_numeric($projectid) || $projectid <= 0) {
            echo 'Project not found';
            return;
        }

        $builds = array();

        if ($CDASH_DB_TYPE == 'pgsql') {
            $query = pdo_query('SELECT EXTRACT(YEAR FROM starttime) AS y ,
                              EXTRACT(MONTH FROM starttime) AS m,
                              EXTRACT(DAY FROM starttime) AS d,
                  AVG(builderrors) AS builderrors,AVG(buildwarnings) AS buildwarnings,
                  AVG(testnotrun) AS testnotrun,AVG(testfailed) AS testfailed
                  FROM build WHERE projectid=' . $projectid . '
                  AND starttime<NOW()
                  GROUP BY y,m,d
                  ORDER BY y,m,d ASC LIMIT 1000'); // limit the request
        } else {
            $query = pdo_query('SELECT YEAR(starttime) AS y ,MONTH(starttime) AS m,DAY(starttime) AS d,
                  AVG(builderrors) AS builderrors,AVG(buildwarnings) AS buildwarnings,
                  AVG(testnotrun) AS testnotrun,AVG(testfailed) AS testfailed
                  FROM build WHERE projectid=' . $projectid . '
                  AND starttime<NOW()
                  GROUP BY YEAR(starttime),MONTH(starttime),DAY(starttime)
                  ORDER BY YEAR(starttime),MONTH(starttime),DAY(starttime) ASC LIMIT 1000'); // limit the request
        }

        echo pdo_error();

        while ($query_array = pdo_fetch_array($query)) {
            $build['month'] = $query_array['m'];
            $build['day'] = $query_array['d'];
            $build['year'] = $query_array['y'];
            $build['time'] = strtotime($query_array['y'] . '-' . $query_array['m'] . '-' . $query_array['d']);

            $build['builderrors'] = 0;
            if ($query_array['builderrors'] >= 0) {
                $build['builderrors'] = $query_array['builderrors'];
            }
            $build['buildwarnings'] = 0;
            if ($query_array['buildwarnings'] >= 0) {
                $build['buildwarnings'] = $query_array['buildwarnings'];
            }
            $build['testnotrun'] = 0;
            if ($query_array['testnotrun'] >= 0) {
                $build['testnotrun'] = $query_array['testnotrun'];
            }
            $build['testfailed'] = 0;
            if ($query_array['testfailed'] >= 0) {
                $build['testfailed'] = $query_array['testfailed'];
            }
            $builds[] = $build;
        }
        return $builds;
    }

    /** Return the defects: builderrors, buildwarnings, testnotrun, testfailed. */
    private function RevisionStatus()
    {
        include_once 'include/common.php';
        include dirname(dirname(dirname(__DIR__))) . '/config/config.php';

        if (!isset($this->Parameters['project'])) {
            echo 'Project not set';
            return;
        }

        if (!isset($this->Parameters['revision'])) {
            echo 'revision not set';
            return;
        }

        $revision = trim($this->Parameters['revision']);

        $projectid = get_project_id($this->Parameters['project']);
        if (!is_numeric($projectid) || $projectid <= 0) {
            echo 'Project not found';
            return;
        }

        $builds = array();

        // Finds all the buildid
        $query = pdo_query("SELECT b.name,b.id, b.starttime,b.endtime,b.submittime,b.builderrors,b.buildwarnings,b.testnotrun,b.testfailed,b.testpassed
                        FROM build AS b, buildupdate AS bu, build2update AS b2u WHERE
                        b2u.buildid=b.id AND b2u.updateid=bu.id AND
                        bu.revision='" . $revision . "' AND b.projectid='" . $projectid . "'   "); // limit the request

        echo pdo_error();

        while ($query_array = pdo_fetch_array($query)) {
            $build['id'] = $query_array['id'];
            $build['name'] = $query_array['name'];
            $build['starttime'] = $query_array['starttime'];
            $build['endtime'] = $query_array['endtime'];
            $build['submittime'] = $query_array['submittime'];

            // Finds the osname
            $infoquery = pdo_query("SELECT osname FROM buildinformation WHERE buildid='" . $build['id'] . "'");
            if (pdo_num_rows($infoquery) > 0) {
                $query_infoarray = pdo_fetch_array($infoquery);
                $build['os'] = $query_infoarray['osname'];
            }

            // Finds the configuration errors
            $configquery = pdo_query(
                "SELECT count(*) AS c FROM configureerror AS ce
                JOIN build2configure AS b2c ON (b2c.configureid=ce.configureid)
                WHERE b2c.buildid='" . $build['id'] . "' AND type='0'");
            $query_configarray = pdo_fetch_array($configquery);
            $build['configureerrors'] = $query_configarray['c'];

            $configquery = pdo_query(
                "SELECT count(*) AS c FROM configureerror AS ce
                JOIN build2configure AS b2c ON (b2c.configureid=ce.configureid)
                WHERE b2c.buildid='" . $build['id'] . "' AND type='1'");
            $query_configarray = pdo_fetch_array($configquery);
            $build['configurewarnings'] = $query_configarray['c'];

            $coveragequery = pdo_query("SELECT loctested,locuntested FROM coveragesummary WHERE buildid='" . $build['id'] . "'");
            if ($coveragequery) {
                $coveragequery_configarray = pdo_fetch_array($coveragequery);
                $build['loctested'] = $coveragequery_configarray['loctested'];
                $build['locuntested'] = $coveragequery_configarray['locuntested'];
            }

            $build['builderrors'] = 0;
            if ($query_array['builderrors'] >= 0) {
                $build['builderrors'] = $query_array['builderrors'];
            }
            $build['buildwarnings'] = 0;
            if ($query_array['buildwarnings'] >= 0) {
                $build['buildwarnings'] = $query_array['buildwarnings'];
            }
            $build['testnotrun'] = 0;
            if ($query_array['testnotrun'] >= 0) {
                $build['testnotrun'] = $query_array['testnotrun'];
            }
            $build['testpassed'] = 0;
            if ($query_array['testpassed'] >= 0) {
                $build['testpassed'] = $query_array['testpassed'];
            }
            $build['testfailed'] = 0;
            if ($query_array['testfailed'] >= 0) {
                $build['testfailed'] = $query_array['testfailed'];
            }
            $builds[] = $build;
        }
        return $builds;
    }

    /** Return the number of defects per number of checkins */
    private function ListCheckinsDefects()
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

        $builds = array();
        $query = pdo_query('SELECT nfiles, builderrors, buildwarnings, testnotrun, testfailed
                FROM build,buildupdate,build2update WHERE build.projectid=' . $projectid . '
                AND buildupdate.id=build2update.updateid
                AND build2update.buildid=build.id
                AND nfiles>0
                AND build.starttime<NOW()
                ORDER BY build.starttime DESC LIMIT 1000'); // limit the request
        echo pdo_error();

        while ($query_array = pdo_fetch_array($query)) {
            $build['nfiles'] = $query_array['nfiles'];
            $build['builderrors'] = 0;
            if ($query_array['builderrors'] >= 0) {
                $build['builderrors'] = $query_array['builderrors'];
            }
            $build['buildwarnings'] = 0;
            if ($query_array['buildwarnings'] >= 0) {
                $build['buildwarnings'] = $query_array['buildwarnings'];
            }
            $build['testnotrun'] = 0;
            if ($query_array['testnotrun'] >= 0) {
                $build['testnotrun'] = $query_array['testnotrun'];
            }
            $build['testfailed'] = 0;
            if ($query_array['testfailed'] >= 0) {
                $build['testfailed'] = $query_array['testfailed'];
            }
            $builds[] = $build;
        }
        return $builds;
    }

    /** Return an array with two sub arrays:
     *  array1: id, buildname, os, bits, memory, frequency
     *  array2: array1_id, test_fullname */
    private function ListSiteTestFailure()
    {
        include dirname(dirname(dirname(__DIR__))) . '/config/config.php';
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

        $group = 'Nightly';
        if (isset($this->Parameters['group'])) {
            $group = pdo_real_escape_string($this->Parameters['group']);
        }

        // Get first all the unique builds for today's dashboard and group
        $query = pdo_query('SELECT nightlytime FROM project WHERE id=' . qnum($projectid));
        $project_array = pdo_fetch_array($query);

        $date = date('Y-m-d');
        list($previousdate, $currentstarttime, $nextdate) = get_dates($date, $project_array['nightlytime']);
        $currentUTCTime = date(FMT_DATETIME, $currentstarttime);

        // Get all the unique builds for the section of the dashboard
        if ($CDASH_DB_TYPE == 'pgsql') {
            $query = pdo_query("SELECT max(b.id) AS buildid,s.name || '-' || b.name AS fullname,s.name AS sitename,b.name,
               si.totalphysicalmemory,si.processorclockfrequency
               FROM build AS b, site AS s, siteinformation AS si, buildgroup AS bg, build2group AS b2g
               WHERE b.projectid=" . $projectid . " AND b.siteid=s.id AND si.siteid=s.id
               AND bg.name='" . $group . "' AND b.testfailed>0 AND b2g.buildid=b.id AND b2g.groupid=bg.id
               AND b.starttime>'$currentUTCTime' AND b.starttime<NOW() GROUP BY fullname,
               s.name,b.name,si.totalphysicalmemory,si.processorclockfrequency
               ORDER BY buildid");
        } else {
            $query = pdo_query("SELECT max(b.id) AS buildid,CONCAT(s.name,'-',b.name) AS fullname,s.name AS sitename,b.name,
               si.totalphysicalmemory,si.processorclockfrequency
               FROM build AS b, site AS s, siteinformation AS si, buildgroup AS bg, build2group AS b2g
               WHERE b.projectid=" . $projectid . " AND b.siteid=s.id AND si.siteid=s.id
               AND bg.name='" . $group . "' AND b.testfailed>0 AND b2g.buildid=b.id AND b2g.groupid=bg.id
               AND b.starttime>'$currentUTCTime' AND b.starttime<UTC_TIMESTAMP() GROUP BY fullname ORDER BY buildid");
        }
        $sites = array();
        $buildids = '';
        while ($query_array = pdo_fetch_array($query)) {
            if ($buildids != '') {
                $buildids .= ',';
            }
            $buildids .= $query_array['buildid'];
            $site = array();
            $site['name'] = $query_array['sitename'];
            $site['buildname'] = $query_array['name'];
            $site['cpu'] = $query_array['processorclockfrequency'];
            $site['memory'] = $query_array['totalphysicalmemory'];
            $sites[$query_array['buildid']] = $site;
        }

        if (empty($sites)) {
            return $sites;
        }

        $query = pdo_query('SELECT bt.buildid AS buildid,t.name AS testname,t.id AS testid
              FROM build2test AS bt,test as t
              WHERE bt.buildid IN (' . $buildids . ") AND bt.testid=t.id AND bt.status='failed'");

        $tests = array();

        while ($query_array = pdo_fetch_array($query)) {
            $test = array();
            $test['id'] = $query_array['testid'];
            $test['name'] = $query_array['testname'];
            $sites[$query_array['buildid']]['tests'][] = $test;
        }
        return $sites;
    }

    /** Schedule a build */
    private function ScheduleBuild()
    {
        include dirname(dirname(dirname(__DIR__))) . '/config/config.php';
        include_once 'include/common.php';
        include_once 'models/clientjobschedule.php';
        include_once 'models/clientos.php';
        include_once 'models/clientcmake.php';
        include_once 'models/clientcompiler.php';
        include_once 'models/clientlibrary.php';

        if (!isset($this->Parameters['token'])) {
            return array('status' => false, 'message' => 'You must specify a token parameter.');
        }

        $clientJobSchedule = new ClientJobSchedule();

        $status = array();
        $status['scheduled'] = 0;
        if (!isset($this->Parameters['project'])) {
            return array('status' => false, 'message' => 'You must specify a project parameter.');
        }

        $projectid = get_project_id($this->Parameters['project']);
        if (!is_numeric($projectid) || $projectid <= 0) {
            return array('status' => false, 'message' => 'Project not found.');
        }
        $clientJobSchedule->ProjectId = $projectid;

        // Perform the authentication (make sure user has project admin priviledges)
        if (!web_api_authenticate($projectid, $this->Parameters['token'])) {
            return array('status' => false, 'message' => 'Invalid API token.');
        }

        // We would need a user login/password at some point
        $clientJobSchedule->UserId = '1';
        if (isset($this->Parameters['userid'])) {
            $clientJobSchedule->UserId = pdo_real_escape_string($this->Parameters['userid']);
        }

        // Experimental: 0
        // Nightly: 1
        // Continuous: 2
        $clientJobSchedule->Type = 0;
        if (isset($this->Parameters['type'])) {
            $clientJobSchedule->Type = pdo_real_escape_string($this->Parameters['type']);
        }

        if (!isset($this->Parameters['repository'])) {
            return array('status' => false, 'message' => 'You must specify a repository parameter.');
        }

        $clientJobSchedule->Repository = pdo_real_escape_string($this->Parameters['repository']);

        if (isset($this->Parameters['module'])) {
            $clientJobSchedule->Module = pdo_real_escape_string($this->Parameters['module']);
        }

        if (isset($this->Parameters['tag'])) {
            $clientJobSchedule->Tag = pdo_real_escape_string($this->Parameters['tag']);
        }

        if (isset($this->Parameters['suffix'])) {
            $clientJobSchedule->BuildNameSuffix = pdo_real_escape_string($this->Parameters['suffix']);
        }

        // Build Configuration
        // Debug: 0
        // Release: 1
        // RelWithDebInfo: 2
        // MinSizeRel: 3
        $clientJobSchedule->BuildConfiguration = 0;
        if (isset($this->Parameters['configuration'])) {
            $clientJobSchedule->BuildConfiguration = pdo_real_escape_string($this->Parameters['configuration']);
        }

        $clientJobSchedule->StartDate = date('Y-m-d H:i:s');
        $clientJobSchedule->StartTime = date('Y-m-d H:i:s');
        $clientJobSchedule->EndDate = '1980-01-01 00:00:00';
        $clientJobSchedule->RepeatTime = 0; // No repeat
        $clientJobSchedule->Enable = 1;
        $clientJobSchedule->Save();

        // Remove everything and add them back in
        $clientJobSchedule->RemoveDependencies();

        // Set CMake
        if (isset($this->Parameters['cmakeversion'])) {
            $cmakeversion = pdo_real_escape_string($this->Parameters['cmakeversion']);
            $ClientCMake = new ClientCMake();
            $ClientCMake->Version = $cmakeversion;
            $cmakeid = $ClientCMake->GetIdFromVersion();
            if (!empty($cmakeid)) {
                $clientJobSchedule->AddCMake($cmakeid);
            }
        }

        // Set the site id (for now only one)
        if (isset($this->Parameters['siteid'])) {
            $siteid = pdo_real_escape_string($this->Parameters['siteid']);
            $clientJobSchedule->AddSite($siteid);
        }

        if (isset($this->Parameters['osname'])
            || isset($this->Parameters['osversion'])
            || isset($this->Parameters['osbits'])
        ) {
            $ClientOS = new ClientOS();
            $osname = '';
            $osversion = '';
            $osbits = '';
            if (isset($this->Parameters['osname'])) {
                $osname = $this->Parameters['osname'];
            }
            if (isset($this->Parameters['osversion'])) {
                $osversion = $this->Parameters['osversion'];
            }
            if (isset($this->Parameters['osbits'])) {
                $osbits = $this->Parameters['osbits'];
            }
            $osids = $ClientOS->GetOS($osname, $osversion, $osbits);

            foreach ($osids as $osid) {
                $clientJobSchedule->AddOS($osid);
            }
        }

        if (isset($this->Parameters['compilername'])
            || isset($this->Parameters['compilerversion'])
        ) {
            $ClientCompiler = new ClientCompiler();
            $compilername = '';
            $compilerversion = '';
            if (isset($this->Parameters['compilername'])) {
                $compilername = $this->Parameters['compilername'];
            }
            if (isset($this->Parameters['compilerversion'])) {
                $compilerversion = $this->Parameters['compilerversion'];
            }
            $compilerids = $ClientCompiler->GetCompiler($compilername, $compilerversion);
            foreach ($compilerids as $compilerid) {
                $clientJobSchedule->AddCompiler($compilerid);
            }
        }

        if (isset($this->Parameters['libraryname'])
            || isset($this->Parameters['libraryversion'])
        ) {
            $ClientLibrary = new ClientLibrary();
            $libraryname = '';
            $libraryversion = '';
            if (isset($this->Parameters['libraryname'])) {
                $libraryname = $this->Parameters['libraryname'];
            }
            if (isset($this->Parameters['libraryversion'])) {
                $libraryversion = $this->Parameters['libraryversion'];
            }
            $libraryids = $ClientLibrary->GetLibrary($libraryname, $libraryversion);
            foreach ($libraryids as $libraryid) {
                $clientJobSchedule->AddLibrary($libraryid);
            }
        }

        $status['scheduleid'] = $clientJobSchedule->Id;
        $status['scheduled'] = 1;
        $status['status'] = true;
        return $status;
    }

    /** Return the status of a scheduled build */
    private function ScheduleStatus()
    {
        include dirname(dirname(dirname(__DIR__))) . '/config/config.php';
        include_once 'include/common.php';
        include_once 'models/clientjobschedule.php';
        include_once 'models/clientos.php';
        include_once 'models/clientcmake.php';
        include_once 'models/clientcompiler.php';
        include_once 'models/clientlibrary.php';

        $status = array();
        $status['scheduled'] = 0;
        if (!isset($this->Parameters['project'])) {
            echo 'Project name should be set';
            return;
        }

        $projectid = get_project_id($this->Parameters['project']);
        if (!is_numeric($projectid) || $projectid <= 0) {
            echo 'Project not found';
            return;
        }

        $scheduleid = $this->Parameters['scheduleid'];
        if (!is_numeric($scheduleid) || $scheduleid <= 0) {
            echo 'ScheduleId not set';
            return;
        }

        $clientJobSchedule = new ClientJobSchedule();
        $clientJobSchedule->Id = $scheduleid;
        $clientJobSchedule->ProjectId = $projectid;

        $status['status'] = $clientJobSchedule->GetStatus();
        switch ($status['status']) {
            case -1:
                $status['statusstring'] = 'not found';
                break;
            case 0:
                $status['statusstring'] = 'scheduled';
                break;
            case 2:
                $status['statusstring'] = 'running';
                break;
            case 3:
                $status['statusstring'] = 'finished';
                break;
            case 4:
                $status['statusstring'] = 'aborted';
                break;
            case 5:
                $status['statusstring'] = 'failed';
                break;
        }

        $status['scheduleid'] = $clientJobSchedule->Id;
        $status['builds'] = $clientJobSchedule->GetAssociatedBuilds();
        $status['scheduled'] = 0;
        if ($status['status'] > 0) {
            $status['scheduled'] = 1;
        }
        return $status;
    }

    /** Run function */
    public function Run()
    {
        switch ($this->Parameters['task']) {
            case 'defects':
                return $this->ListDefects();
            case 'revisionstatus':
                return $this->RevisionStatus();
            case 'checkinsdefects':
                return $this->ListCheckinsDefects();
            case 'sitetestfailures':
                return $this->ListSiteTestFailure();
            case 'schedule':
                return $this->ScheduleBuild();
            case 'schedulestatus':
                return $this->ScheduleStatus();
        }
    }
}
