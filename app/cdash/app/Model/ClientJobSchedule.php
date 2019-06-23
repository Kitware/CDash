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
namespace CDash\Model;

use CDash\Config;

class ClientJobSchedule
{
    public $Id;
    public $UserId;
    public $ProjectId;
    public $StartDate;
    public $FinishDate;
    public $Type;
    public $StartTime;
    public $RepeatTime;
    public $Enable;
    public $CMakeCache;
    public $ClientScript;
    public $Repository;
    public $Module;
    public $Tag;
    public $BuildNameSuffix;
    public $BuildConfigurations;
    public $BuildConfiguration;
    public $Description;

    public function __construct()
    {
        $this->BuildConfigurations = array(
            0 => 'Debug',
            1 => 'Release',
            2 => 'RelWithDebInfo',
            3 => 'MinSizeRel',
        );
    }

    /** Get ProjectId */
    public function GetProjectId()
    {
        if (!$this->Id) {
            add_log('ClientJobSchedule::GetProjectId', 'Id not set');
            return;
        }
        $sys = pdo_query('SELECT projectid FROM client_jobschedule WHERE id=' . qnum($this->Id));
        $row = pdo_fetch_array($sys);
        return $row[0];
    }

    /** Get the CMakeCache */
    public function GetCMakeCache()
    {
        if (!$this->Id) {
            add_log('ClientJobSchedule::GetCMakeCache', 'Id not set');
            return;
        }
        $sys = pdo_query('SELECT cmakecache FROM client_jobschedule WHERE id=' . qnum($this->Id));
        $row = pdo_fetch_array($sys);
        return $row[0];
    }

    /** Get the Description */
    public function GetDescription()
    {
        if (!$this->Id) {
            add_log('ClientJobSchedule::GetDescription', 'Id not set');
            return;
        }
        $sys = pdo_query('SELECT description FROM client_jobschedule WHERE id=' . qnum($this->Id));
        $row = pdo_fetch_array($sys);
        return $row[0];
    }

    /** Get the custom client script */
    public function GetClientScript()
    {
        if (!$this->Id) {
            add_log('ClientJobSchedule::GetClientScript', 'Id not set');
            return;
        }
        $query = pdo_query('SELECT clientscript FROM client_jobschedule WHERE id=' . qnum($this->Id));
        $row = pdo_fetch_array($query);
        return $row[0];
    }

    /** Get StartingDate */
    public function GetStartDate()
    {
        if (!$this->Id) {
            add_log('ClientJobSchedule::GetStartDate', 'Id not set');
            return;
        }
        $sys = pdo_query('SELECT startdate FROM client_jobschedule WHERE id=' . qnum($this->Id));
        $row = pdo_fetch_array($sys);
        return $row[0];
    }

    /** Get EndDate */
    public function GetEndDate()
    {
        if (!$this->Id) {
            add_log('ClientJobSchedule::GetEndDate', 'Id not set');
            return;
        }
        $sys = pdo_query('SELECT enddate FROM client_jobschedule WHERE id=' . qnum($this->Id));
        $row = pdo_fetch_array($sys);
        return $row[0];
    }

    /** Get StartTime */
    public function GetStartTime()
    {
        if (!$this->Id) {
            add_log('ClientJobSchedule::GetStartTime', 'Id not set');
            return;
        }
        $sys = pdo_query('SELECT starttime FROM client_jobschedule WHERE id=' . qnum($this->Id));
        $row = pdo_fetch_array($sys);
        return $row[0];
    }

    /** Get Finish Date */
    public function GetRepeatTime()
    {
        if (!$this->Id) {
            add_log('ClientJobSchedule::GetRepeatTime', 'Id not set');
            return;
        }
        $sys = pdo_query('SELECT repeattime FROM client_jobschedule WHERE id=' . qnum($this->Id));
        $row = pdo_fetch_array($sys);
        return $row[0];
    }

    /** Get Type */
    public function GetType()
    {
        if (!$this->Id) {
            add_log('ClientJobSchedule::GetType', 'Id not set');
            return;
        }
        $sys = pdo_query('SELECT type FROM client_jobschedule WHERE id=' . qnum($this->Id));
        $row = pdo_fetch_array($sys);
        return $row[0];
    }

    /** Get repository */
    public function GetRepository()
    {
        if (!$this->Id) {
            add_log('ClientJobSchedule::GetRepository', 'Id not set');
            return;
        }
        $sys = pdo_query('SELECT repository FROM client_jobschedule WHERE id=' . qnum($this->Id));
        $row = pdo_fetch_array($sys);
        return $row[0];
    }

    /** Get Tag */
    public function GetTag()
    {
        if (!$this->Id) {
            add_log('ClientJobSchedule::GetTag', 'Id not set');
            return;
        }
        $sys = pdo_query('SELECT tag FROM client_jobschedule WHERE id=' . qnum($this->Id));
        $row = pdo_fetch_array($sys);
        return $row[0];
    }

    /** Get Buildname suffix */
    public function GetBuildNameSuffix()
    {
        if (!$this->Id) {
            add_log('ClientJobSchedule::GetBuildNameSuffix', 'Id not set');
            return;
        }
        $sys = pdo_query('SELECT buildnamesuffix FROM client_jobschedule WHERE id=' . qnum($this->Id));
        $row = pdo_fetch_array($sys);
        return $row[0];
    }

    /** Get the build configuration */
    public function GetBuildConfiguration()
    {
        if (!$this->Id) {
            add_log('ClientJobSchedule::GetBuildConfiguration', 'Id not set');
            return;
        }
        $sys = pdo_query('SELECT buildconfiguration FROM client_jobschedule WHERE id=' . qnum($this->Id));
        $row = pdo_fetch_array($sys);
        return $row[0];
    }

    /** Get Module */
    public function GetModule()
    {
        if (!$this->Id) {
            add_log('ClientJobSchedule::GetModule', 'Id not set');
            return;
        }
        $sys = pdo_query('SELECT module FROM client_jobschedule WHERE id=' . qnum($this->Id));
        $row = pdo_fetch_array($sys);
        return $row[0];
    }

    /** Get Enable */
    public function GetEnable()
    {
        if (!$this->Id) {
            add_log('ClientJobSchedule::GetEnable', 'Id not set');
            return;
        }
        $sys = pdo_query('SELECT enable FROM client_jobschedule WHERE id=' . qnum($this->Id));
        $row = pdo_fetch_array($sys);
        return $row[0];
    }

    /** Save a job schedule */
    public function Save()
    {
        $cmakecache = pdo_real_escape_string($this->CMakeCache);
        $clientscript = pdo_real_escape_string($this->ClientScript);
        $description = pdo_real_escape_string($this->Description);

        if (!$this->Id) {
            $sql = "INSERT INTO client_jobschedule (userid,projectid,startdate,enddate,starttime,enable,type,
                                              repeattime,cmakecache,clientscript,repository,module,buildnamesuffix,
                                              tag,buildconfiguration,description)
              VALUES ('" . $this->UserId . "','" . $this->ProjectId . "','" . $this->StartDate . "','" . $this->EndDate .
                "','" . $this->StartTime . "','" . $this->Enable . "','" . $this->Type . "','" . $this->RepeatTime .
                "','" . $cmakecache . "','" . $clientscript . "','" . $this->Repository . "','" . $this->Module . "','" . $this->BuildNameSuffix .
                "','" . $this->Tag . "','" . $this->BuildConfiguration . "','" . $description . "')";
            pdo_query($sql);
            add_last_sql_error('ClientJobSchedule::Save');
            $this->Id = pdo_insert_id('client_jobschedule');
            add_last_sql_error('ClientJobSchedule::Save');
        } else {
            // update

            $sql = "UPDATE client_jobschedule SET
             startdate='" . $this->StartDate . "',
             enddate='" . $this->EndDate . "',
             starttime='" . $this->StartTime . "',
             repeattime='" . $this->RepeatTime . "',
             cmakecache='" . $cmakecache . "',
             clientscript='" . $clientscript . "',
             repository='" . $this->Repository . "',
             module='" . $this->Module . "',
             buildnamesuffix='" . $this->BuildNameSuffix . "',
             buildconfiguration='" . $this->BuildConfiguration . "',
             tag='" . $this->Tag . "',
             description='" . $description . "',
             enable='" . $this->Enable . "',
             type='" . $this->Type . "' WHERE id=" . qnum($this->Id);
            pdo_query($sql);
            add_last_sql_error('ClientJobSchedule::Save');
        }
    }

    /** Remove only the dependences. This is used when updating */
    public function RemoveDependencies()
    {
        if (!$this->Id) {
            add_log('ClientJobSchedule::RemoveDependencies', 'Id not set');
            return;
        }
        pdo_query('DELETE FROM client_jobschedule2cmake WHERE scheduleid=' . qnum($this->Id));
        pdo_query('DELETE FROM client_jobschedule2compiler WHERE scheduleid=' . qnum($this->Id));
        pdo_query('DELETE FROM client_jobschedule2library WHERE scheduleid=' . qnum($this->Id));
        pdo_query('DELETE FROM client_jobschedule2os WHERE scheduleid=' . qnum($this->Id));
        pdo_query('DELETE FROM client_jobschedule2site WHERE scheduleid=' . qnum($this->Id));
        add_last_sql_error('ClientJobSchedule::RemoveDependencies');
    }

    /** Remove a job schedule */
    public function Remove()
    {
        if (!$this->Id) {
            add_log('ClientJobSchedule::Remove', 'Id not set');
            return;
        }
        pdo_query('DELETE FROM client_job WHERE scheduleid=' . qnum($this->Id));
        pdo_query('DELETE FROM client_jobschedule2cmake WHERE scheduleid=' . qnum($this->Id));
        pdo_query('DELETE FROM client_jobschedule2compiler WHERE scheduleid=' . qnum($this->Id));
        pdo_query('DELETE FROM client_jobschedule2library WHERE scheduleid=' . qnum($this->Id));
        pdo_query('DELETE FROM client_jobschedule2os WHERE scheduleid=' . qnum($this->Id));
        pdo_query('DELETE FROM client_jobschedule2site WHERE scheduleid=' . qnum($this->Id));
        pdo_query('DELETE FROM client_jobschedule2build WHERE scheduleid=' . qnum($this->Id));
        pdo_query('DELETE FROM client_jobschedule WHERE id=' . qnum($this->Id));

        add_last_sql_error('ClientJobSchedule::Remove');
    }

    /** Get the owner of the schedule */
    public function GetOwner()
    {
        if (!$this->Id) {
            add_log('ClientJobSchedule::GetOwner()', 'Id not set');
            return;
        }
        $sys = pdo_query('SELECT userid FROM client_jobschedule WHERE id=' . qnum($this->Id));
        $row = pdo_fetch_array($sys);
        return $row[0];
    }

    /** Get all the schedules for a given user */
    public function GetLastJobId()
    {
        if (!$this->Id) {
            add_log('ClientJobSchedule::GetLastJobId', 'Id not set');
            return;
        }
        $query = pdo_query('SELECT id FROM client_job WHERE scheduleid=' . qnum($this->Id) . ' ORDER BY id DESC LIMIT 1');
        add_last_sql_error('ClientJobSchedule::GetLastJobId');
        $result = 0;
        if ($row = pdo_fetch_array($query)) {
            return $row['id'];
        }
        return $result;
    }

    /** Get all the schedules for a given user */
    public function getAll($userid, $nresult)
    {
        $query = pdo_query("SELECT id FROM client_jobschedule WHERE userid='$userid' ORDER BY id DESC LIMIT $nresult");
        add_last_sql_error('ClientJobSchedule::getAll');
        $result = array();
        while ($row = pdo_fetch_array($query)) {
            $result[] = $row['id'];
        }
        return $result;
    }

    /** Add a library */
    public function AddLibrary($libraryid)
    {
        if (!$this->Id) {
            add_log('ClientJobSchedule::AddLibrary', 'Id not set');
            return;
        }

        $query = pdo_query('INSERT INTO client_jobschedule2library (scheduleid,libraryid) VALUES(' . qnum($this->Id) . ',' . qnum($libraryid) . ')');
        if (!$query) {
            add_last_sql_error('ClientJobSchedule::AddLibrary');
            return false;
        }
        return true;
    }

    /** Add an OS */
    public function AddOS($osid)
    {
        if (!$this->Id) {
            add_log('ClientJobSchedule::AddOS', 'Id not set');
            return;
        }

        $query = pdo_query('INSERT INTO client_jobschedule2os (scheduleid,osid) VALUES(' . qnum($this->Id) . ',' . qnum($osid) . ')');
        if (!$query) {
            add_last_sql_error('ClientJobSchedule::AddOS');
            return false;
        }
        return true;
    }

    /** Add a compiler */
    public function AddCompiler($compilerid)
    {
        if (!$this->Id) {
            add_log('ClientJobSchedule::AddCompiler', 'Id not set');
            return;
        }

        $query = pdo_query('INSERT INTO client_jobschedule2compiler (scheduleid,compilerid)
                        VALUES(' . qnum($this->Id) . ',' . qnum($compilerid) . ')');
        if (!$query) {
            add_last_sql_error('ClientJobSchedule::AddCompiler');
            return false;
        }
        return true;
    }

    /** Add a CMake */
    public function AddCMake($cmakeid)
    {
        if (!$this->Id) {
            add_log('ClientJobSchedule::AddCMake', 'Id not set');
            return;
        }

        $query = pdo_query('INSERT INTO client_jobschedule2cmake (scheduleid,cmakeid)
                        VALUES(' . qnum($this->Id) . ',' . qnum($cmakeid) . ')');
        if (!$query) {
            add_last_sql_error('ClientJobSchedule::AddCMake');
            return false;
        }
        return true;
    }

    /** Add a site */
    public function AddSite($siteid)
    {
        if (!$this->Id) {
            add_log('ClientJobSchedule::AddSite', 'Id not set');
            return;
        }

        $query = pdo_query('INSERT INTO client_jobschedule2site (scheduleid,siteid)
                        VALUES(' . qnum($this->Id) . ',' . qnum($siteid) . ')');
        if (!$query) {
            add_last_sql_error('ClientJobSchedule::AddSite');
            return false;
        }
        return true;
    }

    /** Get the compilers */
    public function GetCompilers()
    {
        if (!$this->Id) {
            add_log('ClientJobSchedule::GetCompilers', 'Id not set');
            return;
        }

        $query = pdo_query('SELECT compilerid FROM client_jobschedule2compiler WHERE scheduleid=' . qnum($this->Id));
        if (!$query) {
            add_last_sql_error('ClientJobSchedule::GetCompilers');
            return false;
        }

        $compilerids = array();
        while ($query_array = pdo_fetch_array($query)) {
            $compilerids[] = $query_array['compilerid'];
        }
        return $compilerids;
    }

    /** Get the sites */
    public function GetSites()
    {
        if (!$this->Id) {
            add_log('ClientJobSchedule::GetSites', 'Id not set');
            return;
        }

        $query = pdo_query('SELECT siteid FROM client_jobschedule2site WHERE scheduleid=' . qnum($this->Id));
        if (!$query) {
            add_last_sql_error('ClientJobSchedule::GetSites');
            return false;
        }

        $siteids = array();
        while ($query_array = pdo_fetch_array($query)) {
            $siteids[] = $query_array['siteid'];
        }
        return $siteids;
    }

    /** Get the Operating systems */
    public function GetSystems()
    {
        if (!$this->Id) {
            add_log('ClientJobSchedule::GetSystems', 'Id not set');
            return;
        }

        $query = pdo_query('SELECT osid FROM client_jobschedule2os WHERE scheduleid=' . qnum($this->Id));
        if (!$query) {
            add_last_sql_error('ClientJobSchedule::GetSystems');
            return false;
        }

        $cmakeids = array();
        while ($query_array = pdo_fetch_array($query)) {
            $cmakeids[] = $query_array['osid'];
        }
        return $cmakeids;
    }

    /** Get the cmake */
    public function GetCMakes()
    {
        if (!$this->Id) {
            add_log('ClientJobSchedule::GetCMakes', 'Id not set');
            return;
        }

        $query = pdo_query('SELECT cmakeid FROM client_jobschedule2cmake WHERE scheduleid=' . qnum($this->Id));
        if (!$query) {
            add_last_sql_error('ClientJobSchedule::GetCMakes');
            return false;
        }

        $cmakeids = array();
        while ($query_array = pdo_fetch_array($query)) {
            $cmakeids[] = $query_array['cmakeid'];
        }
        return $cmakeids;
    }

    /** Get the libraries */
    public function GetLibraries()
    {
        if (!$this->Id) {
            add_log('ClientJobSchedule::GetLibraries', 'Id not set');
            return;
        }

        $query = pdo_query('SELECT libraryid FROM client_jobschedule2library WHERE scheduleid=' . qnum($this->Id));
        if (!$query) {
            add_last_sql_error('ClientJobSchedule::GetLibraries');
            return false;
        }

        $libraryids = array();
        while ($query_array = pdo_fetch_array($query)) {
            $libraryids[] = $query_array['libraryid'];
        }
        return $libraryids;
    }

    /** Get the status of the scheduled build */
    public function GetStatus()
    {
        if (!$this->Id) {
            add_log('ClientJobSchedule:GetStatus', 'Id not set');
            return -1;
        }

        // If we have a projectid we check also the projectid
        $extrasql = '';
        if ($this->ProjectId) {
            $extrasql = " AND js.projectid='" . $this->ProjectId . "' ";
        }

        $sql = 'SELECT js.lastrun,j.id,j.status,j.startdate,j.enddate FROM client_jobschedule AS js
            LEFT JOIN client_job AS j ON (j.scheduleid=js.id)
            WHERE js.id=' . qnum($this->Id) . $extrasql . '
            ORDER BY j.id
            ';
        $query = pdo_query($sql);
        if (pdo_num_rows($query) == 0) {
            return -1; // No schedule found
        }

        $query_array = pdo_fetch_array($query);
        if ($query_array['lastrun'] == '1980-01-01 00:00:00') {
            return Job::SCHEDULED;
        } else {
            return $query_array['status'];
        }
    }

    public function ProjectAllowed($projectid)
    {
        // By default, all projects are allowed:
        //
        $allowed = true;

        $count = pdo_get_field_value(
            'SELECT COUNT(*) AS c FROM client_site2project ' .
            '  WHERE siteid=' . qnum($this->SiteId),
            'c', 0);
        if ($count > 0) {
            // If some records contain siteid, only listed projects are allowed:
            //
            $projectid_count = pdo_get_field_value(
                'SELECT COUNT(*) AS c FROM client_site2project ' .
                '  WHERE siteid=' . qnum($this->SiteId) . ' AND projectid=' . qnum($projectid),
                'c', 0);
            if ($projectid_count > 0) {
                $allowed = true;
            } else {
                $allowed = false;
            }
        }
        return $allowed;
    }

    /** Return the job id if we have a job for the current siteid */
    public function HasJob()
    {
        if (!$this->SiteId) {
            add_log('ClientJobSchedule:HasJob', 'SiteId not set');
            return 0;
        }

        $jobid = 0;
        $now = date(FMT_DATETIMESTD);
        $currenttime = date(FMT_TIME);
        $currentday = date(FMT_DATE);

        // Update the lastping
        pdo_query("UPDATE client_site SET lastping='" . $now . "' WHERE id=" . qnum($this->SiteId));

        $sql = 'SELECT js.id, js.lastrun, js.starttime, js.repeattime,
       count(library.libraryid) AS c, js.projectid
     FROM client_jobschedule AS js
     LEFT JOIN client_jobschedule2cmake AS cmake ON (cmake.scheduleid=js.id)
     LEFT JOIN client_jobschedule2compiler AS compiler ON (compiler.scheduleid=js.id)
     LEFT JOIN client_jobschedule2os AS os ON (os.scheduleid=js.id)
     LEFT JOIN client_jobschedule2site AS site ON (site.scheduleid=js.id)
     LEFT JOIN client_jobschedule2library AS library ON (library.scheduleid=js.id)
     ,client_site2cmake,client_site2compiler,client_site AS s
     WHERE s.id=' . qnum($this->SiteId) . "
      AND client_site2cmake.siteid=s.id
      AND (cmake.scheduleid IS NULL OR cmake.cmakeid=client_site2cmake.cmakeid)
      AND client_site2compiler.siteid=s.id
      AND (compiler.scheduleid IS NULL OR compiler.compilerid=client_site2compiler.compilerid)
      AND (site.scheduleid IS NULL OR site.siteid=s.id)
      AND (os.osid IS NULL OR os.osid=s.osid)
      AND js.startdate<'" . $now . "' AND (js.enddate='1980-01-01 00:00:00' OR js.enddate>'" . $now . "')
      AND js.enable=1
      GROUP BY js.id,js.lastrun,js.starttime,js.repeattime,js.projectid
      ";
        $query = pdo_query($sql);
        if (!$query) {
            add_last_sql_error('ClientJobSchedule::HasJob');
            return 0;
        }
        if (pdo_num_rows($query) == 0) {
            return 0;
        }

        // For each job schedule make sure we have the right libraries
        while ($row = pdo_fetch_array($query)) {
            // Check if this site allows builds for this project:
            //
            $projectid = $row[5];
            if (!$this->ProjectAllowed($projectid)) {
                continue;
            }

            // Make sure the time is right
            $lastrun = $row[1];
            $starttime = $row[2];
            $interval = $row[3];

            // Find the last anticipated start date
            if ($interval > 0) {
                list($hr, $m, $s) = explode(':', $currenttime);
                $currentimeseconds = ((int)$hr * 3600) + ((int)$m * 60) + (int)$s;
                list($hr, $m, $s) = explode(':', $starttime);
                $starttimeseconds = ((int)$hr * 3600) + ((int)$m * 60) + (int)$s;

                // We assume the interval is symetrical (might not be the case...)
                $secondsdiff = (floor(($currentimeseconds - $starttimeseconds) / ($interval * 3600))) * ($interval * 3600);
                if ($secondsdiff < 0) {
                    $expectedstartingdate = strtotime($currentday . ' ' . $starttime . ' ' . $secondsdiff . ' seconds');
                } else {
                    $expectedstartingdate = strtotime($currentday . ' ' . $starttime . ' +' . $secondsdiff . ' seconds');
                }

                if (strtotime($lastrun) > $expectedstartingdate) {
                    continue;
                }
            } else {
                if ($lastrun != '1980-01-01 00:00:00') {
                    continue;
                }
            }

            $scheduleid = $row[0];
            $nlibraries = $row[4];

            // Check if we have the right libraries for this job
            $library = pdo_query('SELECT count(sl.libraryid) FROM client_jobschedule2library AS jsl,
                          client_site2library AS sl WHERE jsl.scheduleid=' . qnum($scheduleid) . '
                          AND sl.libraryid=jsl.libraryid AND sl.siteid=' . qnum($this->SiteId));
            if (!$library) {
                add_last_sql_error('ClientJobSchedule::HasJob-Library');
                return 0;
            }

            $library_array = pdo_fetch_array($library);
            if ($library_array[0] == $nlibraries) {
                $this->Id = $scheduleid;
                return $scheduleid;
            }
        }
        return 0;
    }

    // Return the ctest script
    public function GetCTestScript()
    {
        if (!$this->Id || !$this->SiteId) {
            add_log('ClientJobSchedule:GetCTestScript', 'Id not set');
            return;
        }

        // Update the current run
        pdo_query("UPDATE client_jobschedule SET lastrun='" . date(FMT_DATETIMESTD) . "' WHERE id=" . qnum($this->Id));

        $ClientSite = new ClientSite();
        $ClientSite->Id = $this->SiteId;

        // Create a job
        $job = new ClientJob();
        $job->ScheduleId = $this->Id;
        $job->StartDate = date('Y-m-d H:i:s');
        $job->EndDate = date('1980-01-01 00:00:00');
        $job->Status = Job::RUNNING;
        $job->SiteId = $this->SiteId;
        $job->OsId = $ClientSite->GetOS();

        // Determine the appropriate CMake id (get the newest version)
        $cmake = pdo_query('SELECT sc.cmakeid FROM client_cmake,client_site2cmake AS sc
                      LEFT JOIN client_jobschedule2cmake AS jc ON (jc.cmakeid=sc.cmakeid)
                      WHERE client_cmake.id=sc.cmakeid AND sc.siteid=' . $this->SiteId . '
                      ORDER BY client_cmake.version DESC LIMIT 1');
        $cmake_array = pdo_fetch_array($cmake);
        $job->CMakeId = $cmake_array[0];

        // Determine the appropriate compiler
        $compiler = pdo_query('SELECT sc.compilerid FROM client_compiler,client_site2compiler AS sc
                         LEFT JOIN client_jobschedule2compiler AS jc ON (jc.compilerid=sc.compilerid)
                         WHERE client_compiler.id=sc.compilerid AND sc.siteid=' . $this->SiteId . '
                         ORDER BY client_compiler.version DESC LIMIT 1');
        $compiler_array = pdo_fetch_array($compiler);
        $job->CompilerId = $compiler_array[0];
        $job->Save();

        $Project = new Project();
        $Project->Id = $this->GetProjectId();
        $Project->Fill();

        $compiler = new ClientCompiler();
        $compiler->Id = $job->CompilerId;
        $os = new ClientOS();
        $os->Id = $job->OsId;

        // Initialize the variables
        $buildtype = 'Experimental'; //default
        switch ($this->GetType()) {
            case Job::EXPERIMENTAL:
                $buildtype = 'Experimental';
                break;
            case Job::NIGHTLY:
                $buildtype = 'Nightly';
                break;
            case Job::CONTINOUS:
                $buildtype = 'Continuous';
                break;
        }
        $ctest_script = '#' . $this->Id . "\n";
        $ctest_script .= '#' . $Project->Name . "\n";
        $ctest_script .= 'SET(JOB_BUILDTYPE ' . $buildtype . ')' . "\n";
        $ctest_script .= 'SET(PROJECT_NAME "' . $Project->Name . '")' . "\n";
        if (strlen($this->GetModule()) > 0) {
            $ctest_script .= 'SET(JOB_MODULE "' . $this->GetModule() . '")' . "\n";
        }
        if (strlen($this->GetTag()) > 0) {
            $ctest_script .= 'SET(JOB_TAG "' . $this->GetTag() . '")' . "\n";
        }
        if (strlen($this->GetBuildNameSuffix()) > 0) {
            $ctest_script .= 'SET(JOB_BUILDNAME_SUFFIX "' . $this->GetBuildNameSuffix() . '")' . "\n";
        }
        $ctest_script .= 'SET(JOB_CMAKE_GENERATOR "' . $ClientSite->GetCompilerGenerator($job->CompilerId) . '")' . "\n";
        $ctest_script .= 'SET(JOB_BUILD_CONFIGURATION "' . $this->BuildConfigurations[$this->GetBuildConfiguration()] . '")' . "\n";

        $ctest_script .= 'SET(CLIENT_BASE_DIRECTORY "' . $ClientSite->GetBaseDirectory() . '")' . "\n";
        $ctest_script .= 'SET(CLIENT_CMAKE_PATH "' . $ClientSite->GetCMakePath($job->CMakeId) . '")' . "\n";
        $ctest_script .= 'SET(CLIENT_SITE "' . $ClientSite->GetName() . '")' . "\n";

        $ctest_script .= 'SET(JOB_OS_NAME "' . $os->GetName() . '")' . "\n";
        $ctest_script .= 'SET(JOB_OS_VERSION "' . $os->GetVersion() . '")' . "\n";
        $ctest_script .= 'SET(JOB_OS_BITS "' . $os->GetBits() . '")' . "\n";
        $ctest_script .= 'SET(JOB_COMPILER_NAME "' . $compiler->GetName() . '")' . "\n";
        $ctest_script .= 'SET(JOB_COMPILER_VERSION "' . $compiler->GetVersion() . '")' . "\n";
        $ctest_script .= 'SET(JOB_REPOSITORY "' . $this->GetRepository() . '")' . "\n";

        // Set the program variables
        $programs = $ClientSite->GetPrograms();
        $currentname = '';
        foreach ($programs as $program) {
            $program_name = strtoupper($program['name']);
            $program_version = str_replace('.', '_', strtoupper($program['version']));
            if ($program['name'] != $currentname) {
                $ctest_script .= 'SET(CLIENT_EXECUTABLE_' . $program_name . ' "' . $program['path'] . '")' . "\n";
                $currentname = $program['name'];
            }
            $ctest_script .= 'SET(CLIENT_EXECUTABLE_' . $program_name . '_' . $program_version . ' "' . $program['path'] . '")' . "\n";
        }

        $config = Config::getInstance();
        if ($config->get('CDASH_USE_HTTPS') === true) {
            $ctest_script .= 'set(CTEST_DROP_METHOD "https")' . "\n";
        } else {
            $ctest_script .= 'set(CTEST_DROP_METHOD "http")' . "\n";
        }

        $serverName = $config->getServer();

        $ctest_script .= 'set(CTEST_DROP_SITE "' . $serverName . '")' . "\n";
        $dropLocation = dirname($_SERVER['PHP_SELF']) . '/submit.php?project=' . urlencode($Project->Name);
        $dropLocation .= '&clientscheduleid=' . $this->Id;
        $ctest_script .= 'set(CTEST_DROP_LOCATION "' . $dropLocation . '")' . "\n";
        $ctest_script .= 'set(JOB_DROP_LOCATION "' . $dropLocation . '")' . "\n";
        $ctest_script .= 'set(JOB_DROP_SITE "' . $serverName . '")' . "\n";
        $ctest_script .= 'set(CTEST_DROP_SITE_CDASH TRUE)' . "\n";
        $ctest_script .= 'set(CTEST_NOTES_FILES ${CTEST_SCRIPT_DIRECTORY}/${CTEST_SCRIPT_NAME})' . "\n";

        // Make the cache available
        $ctest_script .= 'set(JOB_INITIAL_CACHE "' . $this->GetCMakeCache() . '\n")' . "\n";

        // Set the macro to warn CDash that the script failed
        $ctest_script .= "\n" . 'MACRO(JOB_FAILED)' . "\n";

        $uri = $_SERVER['REQUEST_URI'];
        $pos = strpos($uri, 'submit.php');
        if ($pos !== false) {
            $uri = substr($uri, 0, $pos + 10);
        }

        $ctest_script .= '  file(DOWNLOAD "${CTEST_DROP_METHOD}://${CTEST_DROP_SITE}' . $uri . '?siteid=' . $this->SiteId . '&jobfailed=1" "${CLIENT_BASE_DIRECTORY}/scriptfailed.txt")' . "\n";
        $ctest_script .= '  return()' . "\n";
        $ctest_script .= 'ENDMACRO(JOB_FAILED)' . "\n\n";

        if (strlen(trim($this->GetClientScript())) > 0) {
            $ctest_script .= $this->GetClientScript();
        } elseif (strlen($Project->CTestTemplateScript) > 0) {
            $ctest_script .= $Project->CTestTemplateScript;
        } else {
            $ctest_script .= $Project->getDefaultJobTemplateScript();
        }
        return $ctest_script;
    }

    /** Associate a build with this client job schedule if the association does not already exist. */
    public function AssociateBuild($buildid)
    {
        if (!$this->Id) {
            add_log('ClientJobSchedule::AssociateBuild', 'Id not set');
            return;
        }
        $result = pdo_query("SELECT scheduleid FROM client_jobschedule2build WHERE scheduleid='" . $this->Id . "' AND buildid='$buildid'");
        if (pdo_num_rows($result) == 0) {
            pdo_query("INSERT INTO client_jobschedule2build (scheduleid, buildid) VALUES ('" . $this->Id . "','$buildid')");
        }
    }

    /** Get all builds associated with this job schedule */
    public function GetAssociatedBuilds()
    {
        if (!$this->Id) {
            add_log('ClientJobSchedule::GetAssociateBuilds', 'Id not set');
            return false;
        }
        $result = pdo_query("SELECT buildid FROM client_jobschedule2build WHERE scheduleid='" . $this->Id . "'");

        $builds = array();
        while ($row = pdo_fetch_array($result)) {
            $builds[] = $row['buildid'];
        }
        return $builds;
    }
}
