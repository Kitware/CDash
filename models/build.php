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
// It is assumed that appropriate headers should be included before including this file
include_once('models/test.php');
include_once('models/buildusernote.php');
include_once('models/builderrordiff.php');
include_once('models/builderror.php');
include_once('models/buildinformation.php');
include_once('models/label.php');

class Build
{
  var $Id;
  var $SiteId;
  var $ProjectId;
  private $Stamp;
  var $Name;
  var $Type;
  var $Generator;
  var $StartTime;
  var $EndTime;
  var $SubmitTime;
  var $Command;
  var $Log;
  var $Information;

  // For the moment we accept only one group per build
  var $GroupId;  

  var $Errors;
  var $ErrorDiffs;

  var $SubProjectId;
  var $SubProjectName;
  var $Append;
  var $Labels;

  // Only the build.xml has information about errors and warnings
  // when the InsertErrors is false the build is created but not the errors and warnings
  var $InsertErrors;

  function __construct()
    {
    $this->ProjectId = 0;  
    $this->Errors = array();
    $this->ErrorDiffs = array();
    $this->Append = false;
    $this->InsertErrors = true;
    }

  function AddError($error)
    {
    $error->BuildId = $this->Id;
    $this->Errors[] = $error;
    }

  function AddLabel($label)
    {
    if(!isset($this->Labels))
      {
      $this->Labels = array();
      }

    $label->BuildId = $this->Id;
    $this->Labels[] = $label;
    }

  function SetStamp($stamp)
    {
    $this->Stamp = $stamp;
    if(strlen($this->Type)==0)
      {
      $this->Type = extract_type_from_buildstamp($this->Stamp);
      }
    }


  function GetStamp()
    {
    return $this->Stamp;
    }

  /** Set the subproject id */
  function SetSubProject($subproject)
    {
    if(!empty($this->SubProjectId))
      {
      return $this->SubProjectId;
      }

    if(empty($subproject))
      {
      return false;
      }

    if(empty($this->ProjectId))
      {
      add_log('ProjectId not set' . $subproject, 'Build::SetSubProject',LOG_ERR,
              $this->ProjectId,$this->Id,
              CDASH_OBJECT_BUILD,$this->Id);
      return false;
      }

    $query = pdo_query(
      "SELECT id FROM subproject WHERE name='$subproject' AND " .
        "projectid=" . qnum($this->ProjectId)
        );
    if(!$query)
      {
      add_last_sql_error("Build:SetSubProject()",$this->ProjectId);
      return false;
      }  

    if(pdo_num_rows($query)>0)
      {
      $query_array = pdo_fetch_array($query);
      $this->SubProjectId = $query_array['id'];
      $this->SubProjectName = $subproject;
      return $this->SubProjectId; 
      }

    add_log('Could not retrieve SubProjectId for subproject: '.$subproject,'Build::SetSubProject',LOG_ERR,
            $this->ProjectId,$this->Id,CDASH_OBJECT_BUILD,$this->Id);
    return false;
    }

  /** Return the subproject id */
  function GetSubProjectName()
    {
    if(empty($this->Id))
      {
      return false;
      }
      
    if(!empty($this->SubProjectName))
      {
      return $this->SubProjectName;
      }
      
    $query = pdo_query("SELECT name FROM subproject,subproject2build WHERE subproject.id=subproject2build.subprojectid 
                        AND subproject2build.buildid=".qnum($this->Id));
    if(!$query)
      {
      add_last_sql_error("Build:GetSubProjectName()",$this->ProjectId,$this->Id);
      return false;
      }  

    if(pdo_num_rows($query)>0)
      {
      $query_array = pdo_fetch_array($query);
      $this->SubProjectName = $query_array['name'];
      return $this->SubProjectName; 
      }
      
    return false;
    }
    
  /** Save the total tests time */  
  function SaveTotalTestsTime($time)
    {
    if(!$this->Id || !is_numeric($this->Id))
      {
      return false;
      }  

    // Check if already exists
    $query = pdo_query("SELECT buildid FROM buildtesttime WHERE buildid=".qnum($this->Id));
    if(!$query)
      {
      add_last_sql_error("SaveTotalTestsTime",$this->ProjectId,$this->Id);
      return false;
      }  
    
    $time = pdo_real_escape_string($time);  
    if(pdo_num_rows($query)>0)
      {
      $query = "UPDATE buildtesttime SET time='".$time."' WHERE buildid=".qnum($this->Id); 
      }
    else
      {
      $query = "INSERT INTO buildtesttime (buildid, time) VALUES ('".$this->Id."','".$time."')";
      }
       
    if(!pdo_query($query))
      {
      add_last_sql_error("Build:SaveTotalTestsTime",$this->ProjectId,$this->Id);
      return false;
      }
    }

  /** Update the end time */
  function UpdateEndTime($end_time)
    {
    if(!$this->Id || !is_numeric($this->Id))
      {
      return false;
      }
    
    $query = "UPDATE build SET endtime='$end_time' WHERE id='$this->Id'";
    if(!pdo_query($query))
      {
      add_last_sql_error("Build:UpdateEndTime",$this->ProjectId,$this->Id);
      return false;
      }  
    }

  /** Fill the current build information from the buildid */
  function FillFromId($buildid)
    {
    $query = pdo_query("SELECT projectid,starttime,siteid,name,type FROM build WHERE id=".qnum($buildid));
    if(!$query)
      {
      add_last_sql_error("Build:FillFromId()",$this->ProjectId,$this->Id);
      return false;
      }  
      
    $build_array = pdo_fetch_array($query);                           
    $this->Name = $build_array["name"];
    $this->Type = $build_array["type"];
    $this->StartTime = $build_array["starttime"];
    $this->SiteId = $build_array["siteid"];
    $this->ProjectId = $build_array["projectid"];

    $query2 = pdo_query(
      "SELECT id FROM subproject, subproject2build " .
      "WHERE subproject.id=subproject2build.subprojectid AND subproject2build.buildid=".qnum($buildid));
    if(!$query2)
      {
      add_last_sql_error("Build:FillFromId",$this->ProjectId,$this->Id);
      return false;
      }
    $subprojectid_array = pdo_fetch_array($query2);
    $this->SubProjectId = $subprojectid_array["id"];
    }


  /** Get the previous build id */
  function GetPreviousBuildId()
    {
    if(!$this->Id)
      {
      return false;    
      }

    // If StartTime is not set or if we are appending we set it
    if($this->StartTime=='' || $this->Append)
      {
      $query = pdo_query("SELECT starttime FROM build WHERE id=".qnum($this->Id));
      if(!$query)
        {
        add_last_sql_error("Build:GetPreviousBuildId",$this->ProjectId,$this->Id);
        return false;
        }
      $query_array = pdo_fetch_array($query);              
      $this->StartTime = $query_array['starttime'];
      }
      
    $query = pdo_query("SELECT id FROM build
                        WHERE siteid=".qnum($this->SiteId)." AND type='$this->Type' AND name='$this->Name'
                         AND projectid=".qnum($this->ProjectId)." AND starttime<'$this->StartTime'
                         ORDER BY starttime DESC LIMIT 1");
    if(!$query)
      {
      add_last_sql_error("Build:GetPreviousBuildId",$this->ProjectId,$this->Id);
      return false;
      }
      
    if(pdo_num_rows($query)>0)
      {
      $previousbuild_array = pdo_fetch_array($query);              
      return $previousbuild_array["id"];
      }
    return false;
    }

  /** Get the build id from it's name */
  function GetIdFromName($subproject)
    {
    $buildid = 0;
    $subprojectid = 0;

    // If there's a subproject given then only return a build id if there is also
    // a record for that subproject already associated with that buildid...
    //
    if ($subproject != '')
      {
      $query = pdo_query("SELECT id FROM subproject WHERE name='".$subproject."' AND projectid=".qnum($this->ProjectId));
      if(pdo_num_rows($query)>0)
        {
        $rows = pdo_fetch_array($query);
        $subprojectid = $rows['id'];
        }
      }

    if($subprojectid != 0)
      {
      $build = pdo_query("SELECT id FROM build, subproject2build".
                         " WHERE projectid=".qnum($this->ProjectId).
                         " AND siteid=".qnum($this->SiteId).
                         " AND name='".$this->Name."'".
                         " AND stamp='".$this->Stamp."'".
                         " AND build.id=subproject2build.buildid".
                         " AND subproject2build.subprojectid=".qnum($subprojectid));
      }
    else
      {
      $build = pdo_query("SELECT id FROM build".
                         " WHERE projectid=".qnum($this->ProjectId).
                         " AND siteid=".qnum($this->SiteId).
                         " AND name='".$this->Name."'".
                         " AND stamp='".$this->Stamp."'");
      }

    if(pdo_num_rows($build)>0)
      {
      $build_array = pdo_fetch_array($build);
      $buildid = $build_array["id"];
      return $buildid;
      }

    add_last_sql_error("GetIdFromName",$this->ProjectId);
    return 0;  
    }


  function InsertLabelAssociations()
    {
    if($this->Id)
      {
      if(!isset($this->Labels))
        {
        return;
        }
      
      foreach($this->Labels as $label)
        {
        $label->BuildId = $this->Id;
        $label->Insert();
        }
      }
    else
      {
      add_log('No Build::Id - cannot call $label->Insert...','Build::InsertLabelAssociations',LOG_ERR,
              $this->ProjectId,$this->Id,
              CDASH_OBJECT_BUILD,$this->Id);
      }
    }


  /** Return if exists */
  function Exists()
    {
    if(!$this->Id)
      {
      return false;    
      }
    $query = pdo_query("SELECT count(*) FROM build WHERE id='".$this->Id."'");
    add_last_sql_error("Build::Exists",$this->ProjectId,$this->Id);
    
    $query_array = pdo_fetch_array($query);
    if($query_array[0]>0)
      {
      return true;
      }
    return false;
    }


  // Save in the database
  function Save()
    {
    if(!$this->Exists())
      {       
      $id = "";
      $idvalue = "";
      if($this->Id)
        {
        $id = "id,";
        $idvalue =  qnum($this->Id).",";
        }
      
      if(strlen($this->Type)==0)
        {
        $this->Type = extract_type_from_buildstamp($this->Stamp);
        }
       
      $this->Name = pdo_real_escape_string($this->Name);
      $this->Stamp = pdo_real_escape_string($this->Stamp);
      $this->Type = pdo_real_escape_string($this->Type);
      $this->Generator = pdo_real_escape_string($this->Generator);
      $this->StartTime = pdo_real_escape_string($this->StartTime);
      $this->EndTime = pdo_real_escape_string($this->EndTime);
      $this->SubmitTime = pdo_real_escape_string($this->SubmitTime);
      $this->Command = pdo_real_escape_string($this->Command);
      $this->Log = pdo_real_escape_string($this->Log);
      
      // Compute the number of errors and warnings (this speeds up the display of the main table)
      if($this->InsertErrors)
        {
        $nbuilderrors = 0;
        $nbuildwarnings = 0;
        foreach($this->Errors as $error)
          {
          if($error->Type == 0)
            {
            $nbuilderrors++;
            }
          else
            {
            $nbuildwarnings++;
            }  
          }
        }
      else
        {
        $nbuilderrors = -1;
        $nbuildwarnings = -1;  
        }
        
      $query = "INSERT INTO build (".$id."siteid,projectid,stamp,name,type,generator,starttime,endtime,submittime,command,log,
                                   builderrors,buildwarnings)
                VALUES (".$idvalue."'$this->SiteId','$this->ProjectId','$this->Stamp','$this->Name',
                        '$this->Type','$this->Generator','$this->StartTime',
                        '$this->EndTime','$this->SubmitTime','$this->Command','$this->Log',$nbuilderrors,$nbuildwarnings)";
      if(!pdo_query($query))
        {
        add_last_sql_error("Build Insert",$this->ProjectId,$this->Id);
        return false;
        }  
      
      $this->Id = pdo_insert_id("build");
      
      // Add the groupid
      if($this->GroupId)
        {
        $query = "INSERT INTO build2group (groupid,buildid) VALUES ('$this->GroupId','$this->Id')";                     
        if(!pdo_query($query))
          {
          add_last_sql_error("Build Insert",$this->ProjectId,$this->Id);
          return false;
          }  
        }

      // Add the subproject2build relationship:
      if($this->SubProjectId)
        {
        $query = "INSERT INTO subproject2build (subprojectid,buildid) VALUES ('$this->SubProjectId','$this->Id')";
        if(!pdo_query($query))
          {
          add_last_sql_error("Build Insert",$this->ProjectId,$this->Id);
          return false;
          }  
        }

      // Add errors/warnings
      foreach($this->Errors as $error)
        {
        $error->BuildId = $this->Id;
        $error->Insert();
        }

      // Add ErrorDiff
      foreach($this->ErrorDiffs as $diff)
        {
        $diff->BuildId = $this->Id;
        $diff->Insert();
        } 
        
      // Save the information
      if(!empty($this->Information))
        {
        $this->Information->BuildId = $this->Id;
        $this->Information->Save();
        }      
      }
    else
      {
      if ($this->Append)
        {
        $this->EndTime = pdo_real_escape_string($this->EndTime);
        $this->SubmitTime = pdo_real_escape_string($this->SubmitTime);
        $this->Command = pdo_real_escape_string(' '.$this->Command);
        $this->Log = pdo_real_escape_string(' '.$this->Log);

        // Compute the number of errors and warnings (this speeds up the display of the main table)
        if($this->InsertErrors)
          {
          $nbuilderrors = 0;
          $nbuildwarnings = 0;
          foreach($this->Errors as $error)
            {
            if($error->Type == 0)
              {
              $nbuilderrors++;
              }
            else
              {
              $nbuildwarnings++;
              }  
            }
          }
        else
          {
          $nbuilderrors = -1;
          $nbuildwarnings = -1;  
          }
          
        include('cdash/config.php');
        if($CDASH_DB_TYPE == 'pgsql') // pgsql doesn't have concat...
          {
          $query = "UPDATE build SET 
                  endtime='$this->EndTime',submittime='$this->SubmitTime',
                  builderrors='$nbuilderrors',buildwarnings='$nbuildwarnings'," .
                  "command=command || '$this->Command', 
                  log=log || '$this->Log'" .
          "WHERE id=".qnum($this->Id);  
          }
        else
          {
          $query = "UPDATE build SET 
                  endtime='$this->EndTime',submittime='$this->SubmitTime',
                  builderrors='$nbuilderrors',buildwarnings='$nbuildwarnings'," .
                  "command=CONCAT(command, '$this->Command'), 
                  log=CONCAT(log, '$this->Log')" .
          "WHERE id=".qnum($this->Id);  
          }    

        if(!pdo_query($query))
          {
          add_last_sql_error("Build Insert (Append)",$this->ProjectId,$this->Id);
          return false;
          }

        // Add errors/warnings
        foreach($this->Errors as $error)
          {
          $error->BuildId = $this->Id;
          $error->Insert();
          }

        // Add ErrorDiff
        foreach($this->ErrorDiffs as $diff)
          {
          $diff->BuildId = $this->Id;
          $diff->Insert();
          }
        }
      else
        {
        //echo "info: nothing<br/>";
        }
      }

    // Add label associations regardless of how Build::Save gets called:
    //
    $this->InsertLabelAssociations();

    return true;
    }

  /** Get number of failed tests */
  function GetNumberOfFailedTests()
    {
    $result =
      pdo_query("SELECT testfailed FROM build WHERE id=".qnum($this->Id));
    if(pdo_num_rows($result) > 0)
      {
      $build_array = pdo_fetch_array($result);
      $numTestsFailed = $build_array["testfailed"];
      if($numTestsFailed < 0)
        {
        return 0;
        }
      return $numTestsFailed;
      }
    return 0;
    }

  /** Get number of passed tests */
  function GetNumberOfPassedTests()
    {
    $result =
      pdo_query("SELECT testpassed FROM build WHERE id=".qnum($this->Id));
    if(pdo_num_rows($result) > 0)
      {
      $build_array = pdo_fetch_array($result);
      $numTestsPassed = $build_array["testpassed"];
      if($numTestsPassed < 0)
        {
        return 0;
        }
      return $numTestsPassed;
      }
    return 0;
    }

  /** Get number of not run tests */
  function GetNumberOfNotRunTests()
    {
    $result =
      pdo_query("SELECT testnotrun FROM build WHERE id=".qnum($this->Id));
    if(pdo_num_rows($result) > 0)
      {
      $build_array = pdo_fetch_array($result);
      $numTestsNotRun = $build_array["testnotrun"];
      if($numTestsNotRun < 0)
        {
        return 0;
        }
      return $numTestsNotRun;
      }
    return 0;
    }

  /** Update the test numbers */
  function UpdateTestNumbers($numberTestsPassed,$numberTestsFailed,$numberTestsNotRun)
    {
    if(!is_numeric($numberTestsPassed) ||!is_numeric($numberTestsFailed) || !is_numeric($numberTestsNotRun) ) 
      {
      return;
      }
    pdo_query("UPDATE build SET testnotrun='$numberTestsNotRun',
                                testfailed='$numberTestsFailed',
                                testpassed='$numberTestsPassed' WHERE id=".qnum($this->Id));
    
    add_last_sql_error("Build:UpdateTestNumbers",$this->ProjectId,$this->Id);
    }

  /** Get the errors differences for the build */  
  function GetErrorDifferences()
    {
    if(!$this->Id)
      {
      add_log("BuildId is not set","Build::GetErrorDifferences",LOG_ERR,
              $this->ProjectId,$this->Id,CDASH_OBJECT_BUILD,$this->Id);      
      return false;
      }

    $diff = array();

    $sqlquery = "SELECT id,builderrordiff.type AS builderrortype,
              builderrordiff.difference_positive AS builderrorspositive,
              builderrordiff.difference_negative AS builderrorsnegative,
              configureerrordiff.type AS configureerrortype,
              configureerrordiff.difference AS configureerrors,
              testdiff.type AS testerrortype,
              testdiff.difference_positive AS testerrorspositive,
              testdiff.difference_negative AS testerrorsnegative
              FROM build 
              LEFT JOIN builderrordiff ON builderrordiff.buildid=build.id
              LEFT JOIN configureerrordiff ON configureerrordiff.buildid=build.id
              LEFT JOIN testdiff ON testdiff.buildid=build.id 
              WHERE id=".qnum($this->Id);
    $query = pdo_query($sqlquery);
    add_last_sql_error("Build:GetErrorDifferences",$this->ProjectId,$this->Id);
    
    while($query_array = pdo_fetch_array($query))
      {
      if($query_array['builderrortype'] == 0)
        {
        $diff['builderrorspositive'] = $query_array['builderrorspositive'];
        $diff['builderrorsnegative'] = $query_array['builderrorsnegative'];
        }  
      else
        {
        $diff['buildwarningspositive'] = $query_array['builderrorspositive'];    
        $diff['buildwarningsnegative'] = $query_array['builderrorsnegative'];
        }
        
      if($query_array['configureerrortype'] == 0)
        {
        $diff['configureerrors'] = $query_array['configureerrors'];  
        }
      else
        {
        $diff['configurewarnings'] = $query_array['configureerrors'];    
        }
      
      if($query_array['testerrortype'] == 2)
        {
        $diff['testpassedpositive'] = $query_array['testerrorspositive'];  
        $diff['testpassednegative'] = $query_array['testerrorsnegative'];  
        }
      else if($query_array['testerrortype'] == 1)
        {
        $diff['testfailedpositive'] = $query_array['testerrorspositive'];   
        $diff['testfailednegative'] = $query_array['testerrorsnegative'];    
        }
      else if($query_array['testerrortype'] == 0)
        {
        $diff['testnotrunpositive'] = $query_array['testerrorspositive'];    
        $diff['testnotrunnegative'] = $query_array['testerrorsnegative'];
        }      
      }

    // If some of the errors are not set default to zero
    $variables = array('builderrorspositive','builderrorsnegative',
                       'buildwarningspositive','buildwarningsnegative',
                       'configureerrors','configurewarnings',
                       'testpassedpositive','testpassednegative',
                       'testfailedpositive','testfailednegative',
                       'testnotrunpositive','testnotrunnegative');
    foreach($variables as $var)
      {
      if(!isset($diff[$var]))
        {
        $diff[$var] = 0;  
        }    
      }

    return $diff;  
    }
    
  /** Compute the build errors differences */
  function ComputeDifferences()
    {
    if(!$this->Id)
      {
      add_log("BuildId is not set","Build::ComputeDifferences",LOG_ERR,
              $this->ProjectId,$this->Id,
              CDASH_OBJECT_BUILD,$this->Id);
      return false;
      }
      
    $previousbuildid = $this->GetPreviousBuildId();
    if($previousbuildid == 0)
      {
      return;
      }
    compute_error_difference($this->Id,$previousbuildid,0); // errors
    compute_error_difference($this->Id,$previousbuildid,1); // warnings
    }   

  /** Compute the build errors differences */
  function ComputeConfigureDifferences()
    {
    if(!$this->Id)
      {
      add_log("BuildId is not set","Build::ComputeConfigureDifferences",LOG_ERR,
              $this->ProjectId,$this->Id,
              CDASH_OBJECT_BUILD,$this->Id);
      return false;
      }

    $previousbuildid = $this->GetPreviousBuildId();
    if($previousbuildid == 0)
      {
      return;
      }      
    compute_configure_difference($this->Id,$previousbuildid,1); // warnings
    } 
     
  /** Compute the test timing as a weighted average of the previous test.
   *  Also compute the difference in errors and tests between builds.
   *  We do that in one shot for speed reasons. */
  function ComputeTestTiming()
    {
    if(!$this->Id)
      {
      add_log("BuildId is not set","Build::ComputeTestTiming",LOG_ERR,
              $this->ProjectId,$this->Id,CDASH_OBJECT_BUILD,$this->Id);
      return false;
      }

    if(!$this->ProjectId)
      {
      add_log("ProjectId is not set","Build::ComputeTestTiming",LOG_ERR,
              $this->ProjectId,$this->Id,CDASH_OBJECT_BUILD,$this->Id);
      return false;
      }

    $testtimestatusfailed = 0;
      
    // TEST TIMING
    $weight = 0.3; // weight of the current test compared to the previous mean/std (this defines a window)
    $build = pdo_query("SELECT projectid,starttime,siteid,name,type FROM build WHERE id=".qnum($this->Id));
    add_last_sql_error("Build:ComputeTestTiming",$this->ProjectId,$this->Id);
    
    $buildid = $this->Id;
    $build_array = pdo_fetch_array($build);                           
    $buildname = $build_array["name"];
    $buildtype = $build_array["type"];
    $starttime = $build_array["starttime"];
    $siteid = $build_array["siteid"];
    $projectid = $build_array["projectid"];

    $project = pdo_query("SELECT testtimestd,testtimestdthreshold,testtimemaxstatus FROM project WHERE id=".qnum($this->ProjectId));
    add_last_sql_error("Build:ComputeTestTiming",$this->ProjectId,$this->Id);
    
    $project_array = pdo_fetch_array($project);
    $projecttimestd = $project_array["testtimestd"]; 
    $projecttimestdthreshold = $project_array["testtimestdthreshold"]; 
    $projecttestmaxstatus = $project_array["testtimemaxstatus"]; 
    
    // Find the previous build
    $previousbuildid = get_previous_buildid($projectid,$siteid,$buildtype,$buildname,$starttime);
    if($previousbuildid == 0)
      {
      return;
      }
  
    // If we have one
    if($previousbuildid>0)
      {
      compute_test_difference($buildid,$previousbuildid,0,$projecttestmaxstatus); // not run
      compute_test_difference($buildid,$previousbuildid,1,$projecttestmaxstatus); // fail
      compute_test_difference($buildid,$previousbuildid,2,$projecttestmaxstatus); // pass
      compute_test_difference($buildid,$previousbuildid,3,$projecttestmaxstatus); // time
  
      // Loop through the tests
      $tests = pdo_query("SELECT build2test.time,build2test.testid,test.name,build2test.status,
                            build2test.timestatus
                            FROM build2test,test WHERE build2test.buildid=".qnum($this->Id)."
                            AND build2test.testid=test.id
                            ");
      add_last_sql_error("Build:ComputeTestTiming",$this->ProjectId,$this->Id);
      
      // Find the previous test
      $previoustest = pdo_query("SELECT build2test.testid,test.name FROM build2test,test
                                   WHERE build2test.buildid=".qnum($previousbuildid)."
                                   AND test.id=build2test.testid 
                                   ");
      add_last_sql_error("Build:ComputeTestTiming",$this->ProjectId,$this->Id);
      
      $testarray = array();
      while($test_array = pdo_fetch_array($previoustest))
        {
        $test = array();
        $test['id'] = $test_array["testid"];
        $test['name'] = $test_array["name"];
        $testarray[] = $test;
        }
  
      while($test_array = pdo_fetch_array($tests))
        {
        $testtime = $test_array['time'];
        $testid = $test_array['testid'];
        $teststatus = $test_array['status'];
        $testname = $test_array['name'];
        $previoustestid = 0;
        $timestatus = $test_array['timestatus'];
        
        foreach($testarray as $test)
          {
          if($test['name']==$testname)
            {
            $previoustestid = $test['id'];
            break;
            }
          }
                           
        if($previoustestid>0)
          {
          $previoustest = pdo_query("SELECT timemean,timestd,timestatus FROM build2test
                                       WHERE buildid=".qnum($previousbuildid)." 
                                       AND build2test.testid=".qnum($previoustestid)
                                    );
          add_last_sql_error("Build:ComputeTestTiming",$this->ProjectId,$this->Id);
          
          $previoustest_array = pdo_fetch_array($previoustest);
          $previoustimemean = $previoustest_array["timemean"];
          $previoustimestd = $previoustest_array["timestd"];
          $previoustimestatus = $previoustest_array["timestatus"];
          
          if($teststatus == "passed") // if the current test passed
            {
            if($timestatus>0 && $timestatus<=$projecttestmaxstatus) // if we are currently detecting the time changed we should use previous mean std
              {
              $timemean = $previoustimemean;
              $timestd = $previoustimestd;
              }
            else
              {  
              // Update the mean and std
              $timemean = (1-$weight)*$previoustimemean+$weight*$testtime;
              $timestd = sqrt((1-$weight)*$previoustimestd*$previoustimestd + $weight*($testtime-$timemean)*($testtime-$timemean));
              }
              
            // Check the current status
            if($previoustimestd<$projecttimestdthreshold)
              {
              $previoustimestd = $projecttimestdthreshold;
              }
              
            if($testtime > $previoustimemean+$projecttimestd*$previoustimestd) // only do positive std
              {
              $timestatus = $previoustimestatus+1; // flag
              }
            else
              {
              $timestatus = 0; // reset the time status to 0
              }
            
            }
          else // the test failed so we just replicate the previous test time
            {
            $timemean = $previoustimemean;
            $timestd = $previoustimestd;
            $timestatus = 0;
            }
          }
        else // the test doesn't exist
          {
          $timestd = 0;
          $timestatus = 0;
          $timemean = $testtime;
          }
            
        pdo_query("UPDATE build2test SET timemean=".qnum($timemean).",timestd=".qnum($timestd).",timestatus=".qnum($timestatus)."
                   WHERE buildid=".qnum($this->Id)." AND testid=".qnum($testid));
        add_last_sql_error("Build:ComputeTestTiming",$this->ProjectId,$this->Id);
        if($timestatus>=$projecttestmaxstatus)
          {
          $testtimestatusfailed++;  
          }
        }  // end loop through the test   
      }
    else // this is the first build
      {
      $timestd = 0;
      $timestatus = 0;
          
      // Loop throught the tests
      $tests = pdo_query("SELECT time,testid FROM build2test WHERE buildid=".qnum($this->Id));
      while($test_array = pdo_fetch_array($tests))
        {
        $timemean = $test_array['time'];
        $testid = $test_array['testid'];
          
        pdo_query("UPDATE build2test SET timemean=".qnum($timemean).",timestd=".qnum($timestd).",timestatus=".qnum($timestatus)." 
                    WHERE buildid=".qnum($this->Id)." AND testid=".qnum($testid));
        add_last_sql_error("Build:ComputeTestTiming",$this->ProjectId,$this->Id);
        if($timestatus>=$projecttestmaxstatus)
          {
          $testtimestatusfailed++;  
          }
        } // loop through the tests          
      } // end if first build

    pdo_query("UPDATE build SET testtimestatusfailed=".qnum($testtimestatusfailed)." WHERE id=".$this->Id);   
    add_last_sql_error("Build:ComputeTestTiming",$this->ProjectId,$this->Id); 
    return true;  
    } // end function compute_test_timing

  
  /** Compute the user statistics */
  function ComputeUpdateStatistics()
    {
    if(!$this->Id)
      {
      add_log("ProjectId is not set","Build::ComputeUpdateStatistics",LOG_ERR,
              $this->ProjectId,$this->Id,CDASH_OBJECT_BUILD,$this->Id);
      return false;
      }
    
    if(!$this->ProjectId)
      {
      add_log("ProjectId is not set","Build::ComputeUpdateStatistics",LOG_ERR,0,$this->Id);
      return false;
      }
    $previousbuildid = $this->GetPreviousBuildId();
    
    // Find the errors, warnings and test failures
    // Find the current number of errors
    $errors = pdo_query("SELECT builderrors,buildwarnings,testnotrun,testfailed FROM build WHERE id=".qnum($this->Id));
    add_last_sql_error("Build:ComputeUpdateStatistics",$this->ProjectId,$this->Id);
    $errors_array = pdo_fetch_array($errors);
    $nerrors = $errors_array[0]; 
    $nwarnings = $errors_array[1]; 
    $ntests = $errors_array[2]+$errors_array[3];
          
    // If we have a previous build
    if($previousbuildid>0)
      {
      $previouserrors = pdo_query("SELECT builderrors,buildwarnings,testnotrun,testfailed FROM build WHERE id=".qnum($previousbuildid));
      add_last_sql_error("Build:ComputeUpdateStatistics",$this->ProjectId,$this->Id);
      $previouserrors_array  = pdo_fetch_array($previouserrors);
      $npreviouserrors = $previouserrors_array[0];   
      $npreviouswarnings = $previouserrors_array[1];
      $nprevioustests = $previouserrors_array[2]+$previouserrors_array[3];
            
      $warningdiff = $nwarnings-$npreviouswarnings;
      $errordiff = $nerrors-$npreviouserrors;
      $testdiff = $ntests-$nprevioustests;
      }
    else // this is the first build
      {
      $warningdiff = $nwarnings;
      $errordiff = $nerrors;
      $testdiff = $ntests;
      } 
      
    // Find the number of different users  
    $nauthors_array = pdo_fetch_array(pdo_query("SELECT count(author) FROM (SELECT author FROM updatefile
                                                WHERE buildid=".qnum($this->Id)." GROUP BY author) AS test"));
    add_last_sql_error("Build:ComputeUpdateStatistics",$this->ProjectId,$this->Id);
    $nauthors = $nauthors_array[0];
   
    $newbuild = 1;
    $previousauthor = "";
    // Loop through the updated files
    $updatefiles = pdo_query("SELECT author,checkindate,filename FROM updatefile WHERE buildid=".qnum($this->Id)." 
                              AND checkindate>'1980-01-01T00:00:00' ORDER BY author ASC, checkindate ASC");
    add_last_sql_error("Build:ComputeUpdateStatistics",$this->ProjectId,$this->Id);
    $nupdatedfiles = pdo_num_rows($updatefiles);
    
    while($updatefiles_array = pdo_fetch_array($updatefiles))
      {
      $checkindate = $updatefiles_array["checkindate"];
      $author = $updatefiles_array["author"];
      $filename = $updatefiles_array["filename"];
      
      if($author != $previousauthor)
        {
        $newbuild = 1;
        }
      $previousauthor  = $author;
      
      // If we have more than one author we need to find who caused the error
      if($nauthors>1)
        {
        $warningdiff = $this->FindRealErrors("WARNING",$author,$this->Id,$filename);
        $errordiff = $this->FindRealErrors("ERROR",$author,$this->Id,$filename);
        $testdiff = 0; // no idea how to find if the update file is responsible for the test failure
        }
      else
        {
        $warningdiff /= $nupdatedfiles;
        $errordiff /= $nupdatedfiles;
        $testdiff /= $nupdatedfiles;
        }   
      
      $this->AddUpdateStatistics($author,$checkindate,$newbuild,
                                 $warningdiff,$errordiff,$testdiff);   
         
      $newbuild = 0;
      } // end updatefiles
    
    return true;
    } // end function ComputeUpdateStatistics


  /** Helper function for compute_update_statistics */
  private function AddUpdateStatistics($author,$checkindate,$firstbuild,
                                       $warningdiff,$errordiff,$testdiff)
    {
    // Find the userid from the author name
    $user2project = pdo_query("SELECT userid FROM user2project WHERE cvslogin='$author' AND projectid=".qnum($this->ProjectId));
    if(pdo_num_rows($user2project)==0)
      {
      return;
      }
     
    $user2project_array = pdo_fetch_array($user2project);
    $userid = $user2project_array["userid"];
        
    // Check if we already have a checkin date for this user
    $userstatistics = pdo_query("SELECT totalupdatedfiles
                                 FROM userstatistics WHERE userid=".qnum($userid)." AND projectid=".qnum($this->ProjectId)." AND checkindate='$checkindate'");
    add_last_sql_error("Build:AddUpdateStatistics",$this->ProjectId,$this->Id);
                                            
    if(pdo_num_rows($userstatistics)>0)
      {                 
      $userstatistics_array = pdo_fetch_array($userstatistics);
      $totalbuilds = 0;
      if($firstbuild==1)
        {
        $totalbuilds=1;
        }
              
      $nfailedwarnings = 0;
      $nfixedwarnings = 0;
      $nfailederrors = 0;
      $nfixederrors = 0;
      $nfailedtests = 0;
      $nfixedtests = 0;
                            
      if($warningdiff>0)
        {
        $nfailedwarnings = $warningdiff;
        }
      else
        {
        $nfixedwarnings = abs($warningdiff);
        }
            
      if($errordiff>0)
        {
        $nfailederrors = $errordiff;
        }
      else
        {
        $nfixederrors = abs($errordiff);
        }
              
      if($testdiff>0)
        {
        $nfailedtests = $testdiff;
        }
      else
        {
        $nfixedtests = abs($testdiff);
        }
           
      pdo_query("UPDATE userstatistics SET totalupdatedfiles=totalupdatedfiles+1,
                  totalbuilds=totalbuilds+'$totalbuilds',
                  nfixedwarnings=nfixedwarnings+'$nfixedwarnings',
                  nfailedwarnings=nfailedwarnings+'$nfailedwarnings',
                  nfixederrors=nfixederrors+'$nfixederrors',
                  nfailederrors=nfailederrors+'$nfailederrors',
                  nfixedtests=nfixedtests+'$nfixedtests',
                  nfailedtests=nfailedtests+'$nfailedtests' WHERE userid=".qnum($userid)." AND projectid=".qnum($this->ProjectId)." AND checkindate>='$checkindate'");     
      add_last_sql_error("Build:AddUpdateStatistics",$this->ProjectId,$this->Id);
      }
    else // insert into the database
      {
      if($warningdiff>0)
        {
        $nfixedwarnings = 0;
        $nfailedwarnings = $warningdiff;
        }
      else
        {
        $nfixedwarnings = $warningdiff;
        $nfailedwarnings = 0;
        }
             
      if($errordiff>0)
        {
        $nfixederrors = 0;
        $nfailederrors = $errordiff;
        }
      else
        {
        $nfixederrors = $errordiff;
        $nfailederrors = 0;
        }
              
      if($testdiff>0)
        {
        $nfixedtests = 0;
        $nfailedtests = $testdiff;
        }
      else
        {
        $nfixedtests = $testdiff;
        $nfailedtests = 0;
        }
  
      $totalupdatedfiles=1;
      $totalbuilds = 0;
      if($firstbuild==1)
        {
        $totalbuilds=1;
        }
  
      pdo_query("UPDATE userstatistics SET totalupdatedfiles=totalupdatedfiles+1,
                 totalbuilds=totalbuilds+1,
                 nfixedwarnings=nfixedwarnings+'$nfixedwarnings',
                 nfailedwarnings=nfailedwarnings+'$nfailedwarnings',
                 nfixederrors=nfixederrors+'$nfixederrors',
                 nfailederrors=nfailederrors+'$nfailederrors',
                 nfixedtests=nfixedtests+'$nfixedtests',
                 nfailedtests=nfailedtests+'$nfailedtests' WHERE userid=".qnum($userid)." AND projectid=".qnum($this->ProjectId)." AND checkindate>'$checkindate'");
             
      add_last_sql_error("Build:AddUpdateStatistics",$this->ProjectId,$this->Id);
             
      // Find the previous userstatistics
      $previous = pdo_query("SELECT totalupdatedfiles,totalbuilds,nfixedwarnings,nfailedwarnings,nfixederrors,nfailederrors,nfixedtests,nfailedtests
                             FROM userstatistics WHERE userid='$userid' AND projectid=".qnum($this->ProjectId)." AND checkindate<'$checkindate' ORDER BY checkindate DESC LIMIT 1");
      add_last_sql_error("Build:AddUpdateStatistics",$this->ProjectId,$this->Id);
      if(pdo_num_rows($previous)>0)
        {
        $previous_array = pdo_fetch_array($previous);
        $totalupdatedfiles += $previous_array["totalupdatedfiles"];
        $totalbuilds += $previous_array["totalbuilds"];
        $nfixedwarnings += $previous_array["nfixedwarnings"];
        $nfailedwarnings += $previous_array["nfailedwarnings"];
        $nfixederrors += $previous_array["nfixederrors"];
        $nfailederrors += $previous_array["nfailederrors"];
        $nfixedtests += $previous_array["nfixedtests"];
        $nfailedtests += $previous_array["nfailedtests"];
        }
  
      pdo_query("INSERT INTO userstatistics (userid,projectid,checkindate,totalupdatedfiles,totalbuilds,
                 nfixedwarnings,nfailedwarnings,nfixederrors,nfailederrors,nfixedtests,nfailedtests)
                 VALUES (".qnum($userid).",".qnum($this->ProjectId).",'$checkindate',$totalupdatedfiles,$totalbuilds,$nfixedwarnings,
                         $nfailedwarnings,$nfixederrors,$nfailederrors,$nfixedtests,$nfailedtests)
                    ");
      add_last_sql_error("Build:AddUpdateStatistics",$this->ProjectId,$this->Id);
      } 
    } // end add_update_statistics


  /** Find the errors associated with a user */
  private function FindRealErrors($type,$author,$buildid,$filename)
    {
    $errortype=0;
    if($type=="WARNING")
      {
      $errortype=1;
      }
    $errors = pdo_query("SELECT count(*) FROM builderror WHERE type=".qnum($errortype)."
                          AND sourcefile LIKE '%$filename%' AND buildid=".qnum($this->Id));
    $errors_array  = pdo_fetch_array($errors);
    return $errors_array[0];
    } // end FindRealErrors

  /** Return the siteid of a build */
  function GetSiteId()
    {
    if(!$this->Id)
      {
      echo "Build GetSiteId(): Id not set";
      return false;
      }

    $build = pdo_query("SELECT siteid FROM build WHERE id=".qnum($this->Id));
    if(!$build)
      {
      add_last_sql_error("Build:GetSiteId",$this->ProjectId,$this->Id);
      return false;
      }
    $build_array = pdo_fetch_array($build);
    return $build_array['siteid'];
    }
    
  /** Return the name of a build */
  function GetName()
    {
    if(!$this->Id)
      {
      echo "Build GetName(): Id not set";
      return false;
      }

    $build = pdo_query("SELECT name FROM build WHERE id=".qnum($this->Id));
    if(!$build)
      {
      add_last_sql_error("Build:GetName",$this->ProjectId,$this->Id);
      return false;
      }
    $build_array = pdo_fetch_array($build);
    return $build_array['name'];
    }

  /** Get all the labels for a given build */
  function GetLabels($labelarray=array())
    {
    if(!$this->Id)
      {
      echo "Build GetLabels(): Id not set";
      return false;
      }    
    
    $sql = "SELECT label.id as labelid FROM label WHERE 
                         label.id IN (SELECT labelid AS id FROM label2build WHERE label2build.buildid=".qnum($this->Id).")";
    
    if(empty($labelarray) || isset($labelarray['test']['errors']))
      {
      $sql .= " OR label.id IN (SELECT labelid AS id FROM label2test WHERE label2test.buildid=".qnum($this->Id).")";  
      }
    if(empty($labelarray) || isset($labelarray['coverage']['errors']))
      {
      $sql .= " OR label.id IN (SELECT labelid AS id FROM label2coveragefile WHERE label2coveragefile.buildid=".qnum($this->Id).")";  
      }
    if(empty($labelarray) || isset($labelarray['build']['errors']))
      {
      $sql .= "  OR label.id IN (SELECT labelid AS id FROM label2buildfailure,buildfailure 
                            WHERE label2buildfailure.buildfailureid=buildfailure.id AND buildfailure.type='0'
                            AND buildfailure.buildid=".qnum($this->Id).")";  
      }  
    if(empty($labelarray) || isset($labelarray['build']['warnings']))
      {
      $sql .= "  OR label.id IN (SELECT labelid AS id FROM label2buildfailure,buildfailure 
                            WHERE label2buildfailure.buildfailureid=buildfailure.id AND buildfailure.type='1'
                            AND buildfailure.buildid=".qnum($this->Id).")";  
      }  
    if(empty($labelarray) || isset($labelarray['dynamicanalysis']['errors']))
      {
      $sql .= " OR label.id IN (SELECT labelid AS id FROM label2dynamicanalysis,dynamicanalysis 
                            WHERE label2dynamicanalysis.dynamicanalysisid=dynamicanalysis.id AND dynamicanalysis.buildid=".qnum($this->Id).")";
      }
    
    $labels = pdo_query($sql);
      
    if(!$labels)
      {
      add_last_sql_error("Build:GetLabels",$this->ProjectId,$this->Id);
      return false;
      }
    
    $labelids = array();
    while($label_array = pdo_fetch_array($labels))
      {
      $labelids[] = $label_array['labelid'];
      }
   
    return array_unique($labelids);
    }

  // Get the group for a build
  function GetGroup()
    {
    if(!$this->Id)
      {
      echo "Build GetGroup(): Id not set";
      return false;
      }
    $group = pdo_query("SELECT groupid FROM build2group WHERE buildid=".qnum($this->Id));
    if(!$group)
      {
      add_last_sql_error("Build:GetGroup",$this->ProjectId,$this->Id);
      return false;
      }
      
    $buildgroup_array = pdo_fetch_array($group);
    return $buildgroup_array["groupid"];
    }

  /** Get the number of errors for a build */
  function GetNumberOfErrors()
    {
    if(!$this->Id)
      {
      echo "Build::GetNumberOfErrors(): Id not set";
      return false;    
      }
   
    $builderror = pdo_query("SELECT builderrors FROM build WHERE id=".qnum($this->Id));
    add_last_sql_error("Build:GetNumberOfErrors",$this->ProjectId,$this->Id);
    $builderror_array = pdo_fetch_array($builderror);
    if($builderror_array[0] == -1)
      {
      return 0;
      }
    return $builderror_array[0];  
    } // end GetNumberOfErrors() 

  /** Get the number of warnings for a build */
  function GetNumberOfWarnings()
    {
    if(!$this->Id)
      {
      echo "Build::GetNumberOfWarnings(): Id not set";
      return false;    
      }
   
    $builderror = pdo_query("SELECT buildwarnings FROM build WHERE id=".qnum($this->Id));
    add_last_sql_error("Build:GetNumberOfWarnings",$this->ProjectId,$this->Id);
    $builderror_array = pdo_fetch_array($builderror);
    if($builderror_array[0] == -1)
      {
      return 0;
      }
    return $builderror_array[0];  
    } // end GetNumberOfWarnings() 


} // end class Build
?>
