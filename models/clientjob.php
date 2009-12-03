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
include_once('models/constants.php');
include('models/clienttoolkit.php');
include('models/clienttoolkitversion.php');
include('models/clienttoolkitconfigure.php');

class ClientJob
{
  var $Id;
  var $OsId;
  var $UserId;
  var $ProjectId;
  var $ScheduleDate;
  var $StartDate;
  var $Type;
  var $RepeatTime;
  var $Status;
  var $CMakeId;
  var $CompilerId;

  /** Get ProjectId */
  function GetProjectId()
    {
    if(!$this->Id)
      {
      add_log("ClientJob::GetProjectId()","Id not set");
      return;
      }
    $sys = pdo_query("SELECT projectid FROM client_job WHERE id=".qnum($this->Id));
    $row = pdo_fetch_array($sys);
    return $row[0];
    }
    
  /** Get StartingDate */
  function GetStartingDate()
    {
    if(!$this->Id)
      {
      add_log("ClientJob::GetStartingDate()","Id not set");
      return;
      }
    $sys = pdo_query("SELECT startdate FROM client_job WHERE id=".qnum($this->Id));
    $row = pdo_fetch_array($sys);
    return $row[0];
    }

  /** Get Schedule Date */
  function GetScheduledDate()
    {
    if(!$this->Id)
      {
      add_log("ClientJob::GetScheduledDate()","Id not set");
      return;
      }
    $sys = pdo_query("SELECT scheduleddate FROM client_job WHERE id=".qnum($this->Id));
    $row = pdo_fetch_array($sys);
    return $row[0];
    }
  
  /** Get Finish Date */
  function GetFinishDate()
    {
    if(!$this->Id)
      {
      add_log("ClientJob::GetFinishDate()","Id not set");
      return;
      }
    $sys = pdo_query("SELECT finishdate FROM client_job WHERE id=".qnum($this->Id));
    $row = pdo_fetch_array($sys);
    return $row[0];
    }
    
  /** Get the owner of the job */
  function GetOwner()
    {
    if(!$this->Id)
      {
      add_log("ClientJob::GetOwner()","Id not set");
      return;
      }
    $sys = pdo_query("SELECT userid FROM client_job WHERE id=".qnum($this->Id));
    $row = pdo_fetch_array($sys);
    return $row[0];
    }
  
  /** Get the compiler id for the job */
  function GetCompiler()
    {
    if(!$this->Id)
      {
      add_log("ClientJob::GetCompiler()","Id not set");
      return;
      }
    $sys = pdo_query("SELECT compilerid FROM client_job WHERE id=".qnum($this->Id));
    $row = pdo_fetch_array($sys);
    return $row[0];
    }
    
  /** Get Status */
  function GetStatus()
    {
    if(!$this->Id)
      {
      add_log("ClientJob::GetStatus()","Id not set");
      return;
      }
    $sys = pdo_query("SELECT status FROM client_job WHERE id=".qnum($this->Id));
    $row = pdo_fetch_array($sys);
    return $row[0];
    }
    
  /** Get CMakeId */
  function GetCMakeId()
    {
    if(!$this->Id)
      {
      add_log("ClientJob::GetCMakeId()","Id not set");
      return;
      }
    $sys = pdo_query("SELECT cmakeid FROM client_job WHERE id=".qnum($this->Id));
    $row = pdo_fetch_array($sys);
    return $row[0];
    }
  
  /** Get Type */
  function GetType()
    {
    if(!$this->Id)
      {
      add_log("ClientJob::GetType()","Id not set");
      return;
      }
    $sys = pdo_query("SELECT type FROM client_job WHERE id=".qnum($this->Id));
    $row = pdo_fetch_array($sys);
    return $row[0];
    }

  /** Get Site */
  function GetSite()
    {
    if(!$this->Id)
      {
      add_log("ClientJob::GetSite()","Id not set");
      return;
      }
    $sys = pdo_query("SELECT siteid FROM client_job WHERE id=".qnum($this->Id));
    $row = pdo_fetch_array($sys);
    return $row[0];
    }
    
    
  /** Set the job has finished */
  function SetFinished()
    {
    $now = date('Y-m-d H:i:s');
    $sql = "UPDATE client_job SET status=".CDASH_JOB_FINISHED.",finishdate='".$now."' WHERE siteid=".$this->SiteId." AND status=".CDASH_JOB_RUNNING;
    pdo_query($sql);
    add_last_sql_error("ClientJob::SetFinished()");
    }
  
  /** Set the job has running */
  function SetRunning()
    {
    $now = date('Y-m-d H:i:s');
    $sql = "UPDATE client_job SET status=".CDASH_JOB_RUNNING.",startdate='".$now."' WHERE siteid=".$this->SiteId." AND status=".CDASH_JOB_SCHEDULED;
    pdo_query($sql);
    add_last_sql_error("ClientJob::SetRunning()");
    }
  
  /** Assign a site to a job */
  function AssignSite()
    {
    $sql = "UPDATE client_job SET siteid=".$this->SiteId." WHERE id=".$this->Id;
    pdo_query($sql);
    add_last_sql_error("ClientJob::AssignSite()");
    }
    
  /** Save a job */  
  function Save()
    {    
    $sql = "INSERT INTO client_job (osid,userid,projectid,scheduleddate,status,cmakeid,compilerid,type,repeattime) 
            VALUES ('".$this->OsId."','".$this->UserId."','".$this->ProjectId."','".$this->ScheduleDate."','".$this->Status
            ."','".$this->CMakeId."','".$this->CompilerId."','".$this->Type."','".$this->RepeatTime."')";
    pdo_query($sql);
    $this->Id = pdo_insert_id('client_job');
    add_last_sql_error("ClientJob::Save");
    /* 
    else // update
      {
      $query_array = pdo_fetch_array($query);
      $this->Id = $query_array['id'];
      $sql = "UPDATE client_site SET bits='".$this->Bits."',os='".$this->Os."',osfullname='".$this->OsFullName."',host='".$this->Host."' WHERE id=".qnum($this->Id);
      pdo_query($sql);
      add_last_sql_error("ClientJob::Save()");
      }*/
    }   // end Save
   
   
   /** Remove a job */  
  function Remove()
    {   
    if(!$this->Id)
      {
      add_log("ClientJob::Remove()","Id not set");
      return;
      }
    pdo_query("DELETE FROM client_job WHERE id=".qnum($this->Id));
    pdo_query("DELETE FROM client_job2toolkit WHERE jobid=".qnum($this->Id));
    pdo_query("DELETE FROM client_job2library WHERE jobid=".qnum($this->Id));
    
    
    add_last_sql_error("ClientJob::Remove");
    }   // end Remove
  
  
  /** */
  function getAll($userid,$nresult)
    {
    $query=pdo_query("SELECT id FROM client_job WHERE userid='$userid' ORDER BY scheduleddate DESC LIMIT $nresult");   
    add_last_sql_error("ClientJob::getAll()");
    $result=array();
    while($row = pdo_fetch_array($query))
      {
      $result[] = $row['id'];
      }
    return $result;
    }
  
  /** Add a toolkit configuration */  
  function AddToolkitConfiguration($toolkitconfigurationid)
    {
    if(!$this->Id)
      {
      add_log("ClientJob::AddToolkitConfiguration()","Id not set");
      return;
      }
      
    $query = pdo_query("INSERT INTO client_job2toolkit (jobid,toolkitconfigurationid) VALUES(".qnum($this->Id).",".qnum($toolkitconfigurationid).")");
    if(!$query)
      {
      add_last_sql_error("ClientJob::AddToolkitConfiguration");
      return false;
      }
    return true;  
    }
    
    
  /** Add a library */  
  function AddLibrary($libraryid)
    {
    if(!$this->Id)
      {
      add_log("ClientJob::AddLibrary()","Id not set");
      return;
      }
      
    $query = pdo_query("INSERT INTO client_job2library (jobid,libraryid) VALUES(".qnum($this->Id).",".qnum($libraryid).")");
    if(!$query)
      {
      add_last_sql_error("ClientJob::AddLibrary");
      return false;
      }
    return true;  
    }

  /** Get the libraries */  
  function GetLibraries()
    {
    if(!$this->Id)
      {
      add_log("ClientJob::GetLibraries()","Id not set");
      return;
      }
      
    $query = pdo_query("SELECT libraryid FROM client_job2library WHERE jobid=".qnum($this->Id));
    if(!$query)
      {
      add_last_sql_error("ClientJob::GetLibraries");
      return false;
      }
    
    $libraryids = array();  
    while($query_array = pdo_fetch_array($query))
      {
      $libraryids[] = $query_array['libraryid']; 
      }    
      
    return $libraryids;
    }

  /** Get the toolkits */  
  function GetToolkitConfigurations()
    {
    if(!$this->Id)
      {
      add_log("ClientJob::GetToolkitConfigurations()","Id not set");
      return;
      }
      
    $query = pdo_query("SELECT toolkitconfigurationid FROM client_job2toolkit WHERE jobid=".qnum($this->Id));
    if(!$query)
      {
      add_last_sql_error("ClientJob::GetToolkitConfigurations");
      return false;
      }
    
    $configurationids = array();  
    while($query_array = pdo_fetch_array($query))
      {
      $configurationids[] = $query_array['toolkitconfigurationid']; 
      }    
      
    return $configurationids;
    }
    
  /** Return the job id if we have a job for the current siteid */
  function HasJob()
    {    
    $jobid = 0;
    
    // Check if we have a job for the given site
    $sql = "SELECT j.id,count(jl.libraryid) FROM client_job AS j LEFT JOIN client_job2library AS jl ON (jl.jobid=j.id),client_site AS s,
            client_site2cmake,client_site2compiler
            WHERE j.status=0 AND s.id=".qnum($this->SiteId)." AND s.osid=j.osid
            AND j.cmakeid=client_site2cmake.cmakeid AND client_site2cmake.siteid=s.id
            AND j.compilerid=client_site2compiler.compilerid AND client_site2compiler.siteid=s.id
            GROUP BY j.id";

    $query=pdo_query($sql);
    if(!$query)
      { 
      add_last_sql_error("ClientJob::HasJob");
      return 0;  
      }
      
    if(pdo_num_rows($query)==0)
      {
      return 0;
      }

    while($row = pdo_fetch_array($query))
      {
      $jobid = $row[0];
      $nlibraries = $row[1];
      
      // Check if we have the right libraries for this job
      $library=pdo_query("SELECT count(sl.libraryid) FROM client_job2library AS jl,client_site2library AS sl WHERE jl.jobid=".qnum($jobid)."
                          AND sl.libraryid=jl.libraryid AND sl.siteid=".qnum($this->SiteId));
      if(!$library)
        { 
        add_last_sql_error("ClientJob::HasJob2");
        return 0;  
        }
              
      $library_array = pdo_fetch_array($library);
      if($library_array[0] == $nlibraries)
        {
        $this->Id = $jobid;
        return $jobid;
        }
      }
    return 0;
    }
  
  // Return the ctest script
  function GetCTestScript()
    {
    $this->AssignSite();
    $this->SetRunning();
    
    $ClientSite = new ClientSite();
    $ClientSite->Id = $this->SiteId;
    
    $baseDirectory = $ClientSite->GetBaseDirectory();
    $ctestExecutable = $ClientSite->GetCMakePath($this->GetCMakeId())."/ctest";
    $cmakeExecutable = $ClientSite->GetCMakePath($this->GetCMakeId())."/cmake";
    
    $Project = new Project();
    $Project->Id = $this->GetProjectId();
    $Project->Fill();
    $sourceName = $Project->Name;
    $binaryName = $sourceName."-bin";
    
    // these are the the name of the source and binary directory on disk. 
    $buildtype = "Experimental"; //default
    switch($this->GetType())
      {
      case CDASH_JOB_EXPERIMENTAL: $buildtype="Experimental";break;
      case CDASH_JOB_NIGHTLY: $buildtype="Nightly";break;
      case CDASH_JOB_CONTINUOUS: $buildtype="Continuous";break;
      }
    $ctest_script = 'SET(CTEST_SOURCE_NAME '.$sourceName.')'."\n";
    $ctest_script .= 'SET(CTEST_BINARY_NAME '.$binaryName.')'."\n";
    $ctest_script .= 'SET(CTEST_DASHBOARD_ROOT "'.$baseDirectory.'")'."\n";
    $ctest_script .= 'SET(CTEST_SOURCE_DIRECTORY "${CTEST_DASHBOARD_ROOT}/${CTEST_SOURCE_NAME}")'."\n";
    $ctest_script .= 'SET(CTEST_BINARY_DIRECTORY "${CTEST_DASHBOARD_ROOT}/${CTEST_BINARY_NAME}")'."\n";

    // which ctest command to use for running the dashboard
    $ctest_script .= 'SET(CTEST_COMMAND "'.$ctestExecutable.'")'."\n";
    $ctest_script .= 'SET(CTEST_CMAKE_GENERATOR "'.$ClientSite->GetCompilerGenerator($this->GetCompiler()).'")'."\n";
    //$ctest_script .= 'SET(CTEST_CONFIGURE_COMMAND "'.$cmakeExecutable.' ${CTEST_SOURCE_DIRECTORY}" -G "'.$ClientSite->GetCompilerGenerator($this->GetCompiler()).'")'."\n";
        
    $ctest_script .= 'set(CTEST_DROP_METHOD          "http")'."\n";
    $ctest_script .= 'set(CTEST_BUILD_NAME "test")'."\n";
    $ctest_script .= 'set(CTEST_SITE "'.$ClientSite->GetName().'")'."\n";

    
    //$ctest_script .= 'SET (CTEST_INITIAL_CACHE "'."\n";
    //$ctest_script .= 'CTEST_DROP_SITE:STRING=linux-gcc4.1.2'."\n";
    //$ctest_script .= '")'."\n";
    
    // Deal with the toolkits
    $toolkitconfigurationids = $this->GetToolkitConfigurations();
    foreach($toolkitconfigurationids as $toolkitconfigurationid)
      {      
      $ClientToolkitConfigure = new ClientToolkitConfigure();
      $ClientToolkitConfigure->Id = $toolkitconfigurationid;
      
      $ClientToolkitVersion = new $ClientToolkitVersion();
      $ClientToolkitVersion->Id = $ClientToolkitConfigure->GetToolkitVersionId();

      $ClientToolkit = new ClientToolkit();
      $ClientToolkit->Id = $ClientToolkitVersion->GetToolkitId();

      $buildname = $ClientToolkit->GetName().'-'.$ClientToolkitVersion->GetName()."-".$ClientToolkitConfigure->GetName();
      $ctest_script .= 'set(CDASH_TOOLKIT_SCRIPT "'.$baseDirectory."/CDash-".$buildname.'.cmake")'."\n";
      
      $toolkitSourcePath = $baseDirectory.'/'.$ClientToolkitVersion->GetSourcePath();
      $toolkitBinaryPath = $baseDirectory.'/'.$ClientToolkitConfigure->GetBinaryPath();
      $toolkitRepositoryType = $ClientToolkitVersion->GetRepositoryType();
      $toolkitRepositoryURL = $ClientToolkitVersion->GetRepositoryURL();
      $repositoryModule = $ClientToolkitVersion->GetRepositoryModule();
      $ctestprojectname = $ClientToolkitVersion->GetCTestProjectName();      
      $cmakecache = $ClientToolkitConfigure->GetCMakeCache();
      
      $ctest_toolkit_script = 'SET(CTEST_COMMAND \"'.$ctestExecutable.'\")'."\n";
      $ctest_toolkit_script .= 'SET(CTEST_SOURCE_DIRECTORY \"'.$toolkitSourcePath.'\")'."\n";
      $ctest_toolkit_script .= 'SET(CTEST_BINARY_DIRECTORY \"'.$toolkitBinaryPath.'\")'."\n";
      $ctest_toolkit_script .= 'SET(CTEST_CMAKE_GENERATOR \"'.$ClientSite->GetCompilerGenerator($this->GetCompiler()).'\")'."\n";
      $ctest_toolkit_script .= 'SET(CTEST_DASHBOARD_ROOT "'.$baseDirectory.'")'."\n";
      $ctest_toolkit_script .= 'SET(CTEST_SITE "'.$ClientSite->GetName().'")'."\n";
      $ctest_toolkit_script .= 'set(CTEST_BUILD_NAME \"'.$buildname.'\")'."\n";
    
      $ctest_toolkit_script .= 'file(WRITE '.$toolkitBinaryPath.'/CMakeCache.txt \"'.$cmakecache.'\")'."\n";

      $ctest_toolkit_script .= 'ctest_start('.$buildtype.')'."\n";
      $ctest_toolkit_script .= 'SET(CTEST_PROJECT_NAME \"'.$ctestprojectname.'\")'."\n"; // for ITK we need this...

  
      $ctest_toolkit_script .= 'if(NOT EXISTS \${CTEST_SOURCE_DIRECTORY})'."\n";
      if($toolkitRepositoryType == CDASH_REPOSITORY_CVS)
        {
        $ctest_toolkit_script .= ' execute_process(COMMAND \"cvs\" \"-d\" \"'.$toolkitRepositoryURL.'\" \"checkout\" \"-d\" \"'.$toolkitSourcePath.'\" \"'.$repositoryModule.'\" WORKING_DIRECTORY \${CTEST_DASHBOARD_ROOT})'."\n";
        }
      else if($toolkitRepositoryType == CDASH_REPOSITORY_SVN)
        {
        $ctest_toolkit_script .= ' execute_process(COMMAND \"svn\" \"co\" \"'.$toolkitRepositoryURL.'\" \"'.$toolkitSourcePath.'\" WORKING_DIRECTORY \${CTEST_DASHBOARD_ROOT})'."\n";
        }    
      $ctest_toolkit_script .= 'else(NOT EXISTS \${CTEST_SOURCE_DIRECTORY})'."\n";
      if($toolkitRepositoryType == CDASH_REPOSITORY_CVS)
        {
        $ctest_toolkit_script .= ' execute_process(COMMAND \"cvs\" \"-d\" \"'.$toolkitRepositoryURL.'\" \"update\" \"-dAP\" \"'.$proToolkitVersion->GetSourcePath().'\" WORKING_DIRECTORY \${CTEST_DASHBOARD_ROOT})'."\n";
        }
      else if($toolkitRepositoryType == CDASH_REPOSITORY_SVN)
        {
        $ctest_toolkit_script .= ' execute_process(COMMAND \"svn\" \"update\" \"'.$toolkitRepositoryURL.'\" WORKING_DIRECTORY \${CTEST_DASHBOARD_ROOT})'."\n";
        }    
      $ctest_toolkit_script .= 'endif()'."\n";
      
      $ctest_toolkit_script .= 'ctest_configure(BUILD  \"\${CTEST_BINARY_DIRECTORY}\" RETURN_VALUE res)'."\n";
      $ctest_toolkit_script .= 'ctest_build(BUILD  \"\${CTEST_BINARY_DIRECTORY}\" RETURN_VALUE res)'."\n";
     
      // Run the script
      $ctest_script .= 'file(WRITE ${CDASH_TOOLKIT_SCRIPT} "'.$ctest_toolkit_script.'")'."\n";
      $ctest_script .= 'ctest_run_script(${CDASH_TOOLKIT_SCRIPT})'."\n";
      $ctest_script .= 'MESSAGE("Done installing toolkit: '.$ClientToolkitConfigure->GetName().'")'."\n";
      }
    
    $ctest_script .= 'ctest_start('.$buildtype.')'."\n";

    // Get the repository (for now only the first one)
    $repositories = $Project->GetRepositories();
    $ctest_script .= 'if(NOT EXISTS ${CTEST_SOURCE_DIRECTORY})'."\n";
    $ctest_script .= ' execute_process(COMMAND "cvs" "-d" "'.$repositories[0]['url'].'" "co" "'.$sourceName.'" WORKING_DIRECTORY "${CTEST_DASHBOARD_ROOT}")'."\n";
    $ctest_script .= 'else(NOT EXISTS ${CTEST_SOURCE_DIRECTORY})'."\n";
    $ctest_script .= ' execute_process(COMMAND "cvs" "-d" "'.$repositories[0]['url'].'" "update" "'.$sourceName.'" WORKING_DIRECTORY "${CTEST_DASHBOARD_ROOT}")'."\n";
    $ctest_script .= 'endif()'."\n";

    $ctest_script .= 'ctest_configure(BUILD  "${CTEST_BINARY_DIRECTORY}" RETURN_VALUE res)'."\n";
    $ctest_script .= 'ctest_build(BUILD  "${CTEST_BINARY_DIRECTORY}" RETURN_VALUE res)'."\n";
    $ctest_script .= 'ctest_test(BUILD  "${CTEST_BINARY_DIRECTORY}" RETURN_VALUE res)'."\n";
    
    $ctest_script .= 'set(CTEST_DROP_SITE_CDASH  TRUE)'."\n";
    $ctest_script .= 'set(CTEST_DROP_SITE "localhost")'."\n";
    $ctest_script .= 'set(CTEST_DROP_LOCATION        "/cdash/submit.php?project='.$Project->Name.'")'."\n";
    $ctest_script .= 'set(CTEST_DROP_SITE_CDASH  TRUE)'."\n";
    $ctest_script .= 'ctest_submit(RETURN_VALUE res)'."\n";

    //$ctest_script .= 'ctest_sleep(20)'."\n";
    $ctest_script .= 'MESSAGE("DONE")'."\n";
        
    return $ctest_script;
    }
} // end class proJob
