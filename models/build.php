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

class Build
{
  var $Id;
  var $SiteId;
  var $ProjectId;
  private $Stamp;
  var $Name;
  var $Type;
  var $Generator;
  var $Starttime;
  var $Endtime;
  var $Submittime;
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


  function __construct()
    {
    $this->Errors = array();
    $this->ErrorDiffs = array();
    }


  function AddError($error)
    {
    $error->BuildId = $this->Id;
    $this->Errors[] = $error;
    }

  
  function AddErrorDiff($diff)
    {
    $diff->BuildId = $this->Id;
    $this->ErrorDiffs[] = $diff;
    }    

    
  function SaveTest($test)
    {
    $test->BuildId = $this->Id;
    $test->Insert();
    }

  
  function SaveTestDiff($diff)
    {
    $diff->BuildId = $this->Id;
    $diff->Insert();
    }    

  
  function SaveUpdate($update)
    {
    $update->BuildId = $this->Id;
    $update->Insert();
    }

    
  function SaveConfigure($configure)
    {
    $configure->BuildId = $this->Id;
    $configure->Insert();
    }

  
  function SaveNote($note)
    {
    $note->BuildId = $this->Id;
    $note->Insert();
    }

  
  function SaveUserNote($note)
    {
    $note->BuildId = $this->Id;
    $note->Insert();
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
      add_log('error: need ProjectId to fetch SubProjectId for ' . $subproject, 'Build::SetSubProject');
      return false;
      }

    $query = pdo_query(
      "SELECT id FROM subproject WHERE name='$subproject' AND " .
        "projectid=" . qnum($this->ProjectId)
        );
    if(!$query)
      {
      add_last_sql_error("Build:SetSubProject()");
      return false;
      }  

    if(pdo_num_rows($query)>0)
      {
      $query_array = pdo_fetch_array($query);
      $this->SubProjectId = $query_array['id'];
      $this->SubProjectName = $subproject;
      return $this->SubProjectId; 
      }

    add_log('error: could not retrieve SubProjectId for subproject: ' . $subproject, 'Build::SetSubProject');
    return false;
    }


  /** Update the end time */
  function UpdateEndTime($end_time)
    {
    if(!$this->Id)
      {
      return false;
      }

    $query = "UPDATE build SET endtime='$end_time' WHERE id='$this->Id'";
    if(!pdo_query($query))
      {
      add_last_sql_error("Build:UpdateEndTime()");
      return false;
      }  
    }


  /** Fill the current build information from the build id */
  function FillFromId($buildid)
    {
    $query = pdo_query("SELECT projectid,starttime,siteid,name,type FROM build WHERE id=".qnum($buildid));
    if(!$query)
      {
      add_last_sql_error("Build:FillFromId()");
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
      "WHERE subproject.id=subproject2build.subprojectid AND subproject2build.buildid=" .
      qnum($buildid));
    if(!$query2)
      {
      add_last_sql_error("Build:FillFromId()");
      return false;
      }
    $subprojectid_array = pdo_fetch_array($query2);
    $this->SubProjectId = $subprojectid_array["id"];
    add_log('info: subprojectid: ' . $this->SubProjectId, 'Build::FillFromId');
    }


  /** Get the previous build id */
  function GetPreviousBuildId()
    {
    if(!$this->Id)
      {
      return false;    
      }
      
    $query = pdo_query("SELECT id FROM build
                        WHERE siteid=".qnum($this->SiteId)." AND type='$this->Type' AND name='$this->Name'
                         AND projectid=".qnum($this->ProjectId)." AND starttime<'$this->StartTime'
                         ORDER BY starttime DESC LIMIT 1");
    if(!$query)
      {
      add_last_sql_error("Build:GetPreviousBuildId()");
      return false;
      }  
      
    if(pdo_num_rows($query)>0)
      {
      $previousbuild_array = pdo_fetch_array($query);              
      return $previousbuild_array["id"];
      }
    return false;
    }


  /** Get the next build id */
  function GetNextBuildId()
    {
    if(!$this->Id)
      {
      return false;    
      }
  
    $query = pdo_query("SELECT id FROM build
                       WHERE siteid=".qnum($this->SiteId)." AND type='$this->Type' AND name='$this->Name'
                       AND projectid=".qnum($this->ProjectId)." AND starttime>'$this->StartTime' 
                       ORDER BY starttime ASC LIMIT 1");
    
    if(!$query)
      {
      add_last_sql_error("Build:GetNextBuildId()");
      return false;
      }  
      
    if(pdo_num_rows($query)>0)
      {
      $nextbuild_array = pdo_fetch_array($query);              
      return $nextbuild_array["id"];
      }
    return false;
    }


  /** Get the last build id */
  function GetLastBuildId()
    {
    if(!$this->Id)
      {
      return false;    
      }
  
    $query = pdo_query("SELECT id FROM build
                        WHERE siteid=".qnum($this->SiteId)." AND type='$this->Type' AND name='$this->Name'
                        AND projectid=".qnum($this->ProjectId)." ORDER BY starttime DESC LIMIT 1");
  
    if(pdo_num_rows($query)>0)
      {
      $nextbuild_array = pdo_fetch_array($query);              
      return $nextbuild_array["id"];
      }
    return false;
    }


  /**  Set the value */
  function SetValue($tag,$value)  
    {
    switch($tag)
      {
      case "SITEID": $this->SiteId = $value;break;
      case "PROJECTID": $this->ProjectId = $value;break;
      case "STAMP": $this->Stamp = $value;break;
      case "NAME": $this->Name = $value;break;
      case "TYPE": $this->Type = $value;break;
      case "GENERATOR": $this->Generator = $value;break;
      case "STARTTIME": $this->StartTime = $value;break;
      case "ENDTIME": $this->EndTime = $value;break;
      case "SUBMITTIME": $this->SubmitTime = $value;break;
      case "COMMAND": $this->Command = $value;break;
      case "LOG": $this->Log = $value;break;
      case "GROUPID": $this->GroupId = $value;break;
      case "SUBPROJECTID": $this->SubProjectId = $value;break;
      }
    }


  function GetIdFromName($subproject)
    {
    $buildid = 0;
    $subprojectid = 0;

    // If there's a subproject given then only return a build id if there is also
    // a record for that subproject already associated with that buildid...
    //
    if ($subproject != '')
      {
      $query = pdo_query("SELECT id FROM subproject WHERE name='".$subproject."'");
      if(pdo_num_rows($query)>0)
        {
        $rows = pdo_fetch_array($query);
        $subprojectid = $rows['id'];
        //add_log('subprojectid='.$subprojectid, 'Build::GetIdFromName');
        }
      }
    else
      {
      //add_log('still no subproject... WT*?', 'Build::GetIdFromName');
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
      //add_log('returning '.$buildid, 'Build::GetIdFromName');
      return $buildid;
      }

    echo pdo_error();
    //add_log('returning 0 after pdo_error...', 'Build::GetIdFromName');
    return 0;  
    }  


  /** Return if exists */
  function Exists()
    {
    if(!$this->Id)
      {
      return false;    
      }

    $query = pdo_query("SELECT count(*) FROM build WHERE id='".$this->Id."'");
    $query_array = pdo_fetch_array($query);
    if($query_array['count(*)']>0)
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

      $query = "INSERT INTO build (".$id."siteid,projectid,stamp,name,type,generator,starttime,endtime,submittime,command,log)
                VALUES (".$idvalue."'$this->SiteId','$this->ProjectId','$this->Stamp','$this->Name',
                        '$this->Type','$this->Generator','$this->StartTime',
                        '$this->EndTime','$this->SubmitTime','$this->Command','$this->Log')";                     
      if(!pdo_query($query))
        {
        add_last_sql_error("Build Insert");
        return false;
        }  
      
      $this->Id = pdo_insert_id("build");
      
      // Add the groupid
      if($this->GroupId)
        {
        $query = "INSERT INTO build2group (groupid,buildid) VALUES ('$this->GroupId','$this->Id')";                     
        if(!pdo_query($query))
          {
          add_last_sql_error("Build Insert");
          return false;
          }  
        }

      // Add the subproject2build relationship:
      if($this->SubProjectId)
        {
        $query = "INSERT INTO subproject2build (subprojectid,buildid) VALUES ('$this->SubProjectId','$this->Id')";
        if(!pdo_query($query))
          {
          add_last_sql_error("Build Insert");
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
        //add_log("info: Append UPDATE into build id: " . $this->Id, 'Build::Save');
        $this->EndTime = pdo_real_escape_string($this->EndTime);
        $this->SubmitTime = pdo_real_escape_string($this->SubmitTime);
        $this->Command = pdo_real_escape_string(' '.$this->Command);
        $this->Log = pdo_real_escape_string(' '.$this->Log);

        $query = "UPDATE build SET endtime='$this->EndTime', submittime='$this->SubmitTime'," .
          "command=CONCAT(command, '$this->Command'), log=CONCAT(log, '$this->Log')" .
          "WHERE id=".qnum($this->Id);
        if(!pdo_query($query))
          {
          add_last_sql_error("Build Insert (Append)");
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

    return true;
    }


  /** Compute the test timing as a weighted average of the previous test.
   *  Also compute the difference in errors and tests between builds.
   *  We do that in one shot for speed reasons. */
  function ComputeTestTiming()
    {
    if(!$this->Id)
       {
      add_log("BuildId is not set","Build::ComputeTestTiming");
      return false;
      }

    if(!$this->ProjectId)
      {
      add_log("ProjectId is not set","Build::ComputeTestTiming");
      return false;
      }

    // TEST TIMING
    $weight = 0.3; // weight of the current test compared to the previous mean/std (this defines a window)
    $build = pdo_query("SELECT projectid,starttime,siteid,name,type FROM build WHERE id=".qnum($this->Id));
      
    echo pdo_error();
    $build_array = pdo_fetch_array($build);                           
    $buildname = $build_array["name"];
    $buildtype = $build_array["type"];
    $starttime = $build_array["starttime"];
    $siteid = $build_array["siteid"];
    $projectid = $build_array["projectid"];
  
    $project = pdo_query("SELECT testtimestd,testtimestdthreshold,testtimemaxstatus FROM project WHERE id=".qnum($this->ProjectId));
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
      compute_error_difference($buildid,$previousbuildid,0); // errors
      compute_error_difference($buildid,$previousbuildid,1); // warnings
      compute_configure_difference($buildid,$previousbuildid,1); // warnings
      compute_test_difference($buildid,$previousbuildid,0,$projecttestmaxstatus); // not run
      compute_test_difference($buildid,$previousbuildid,1,$projecttestmaxstatus); // fail
      compute_test_difference($buildid,$previousbuildid,2,$projecttestmaxstatus); // pass
      compute_test_difference($buildid,$previousbuildid,3,$projecttestmaxstatus); // time
  
      // Loop through the tests
      $tests = pdo_query("SELECT build2test.time,build2test.testid,test.name,build2test.status
                            FROM build2test,test WHERE build2test.buildid=".qnum($this->Id)."
                            AND build2test.testid=test.id
                            ");
      
      // Find the previous test
      $previoustest = pdo_query("SELECT build2test.testid,test.name FROM build2test,test
                                   WHERE build2test.buildid=".qnum($previousbuildid)."
                                   AND test.id=build2test.testid 
                                   ");    
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
        } // loop through the tests          
      } // end if first build
      
    return true;  
    } // end function compute_test_timing

  
  /** Compute the user statistics */
  function ComputeUpdateStatistics()
    {
    if(!$this->Id)
      {
      add_log("BuildId is not set","Build::ComputeUpdateStatistics");
      return false;
      }
    
    if(!$this->ProjectId)
      {
      add_log("ProjectId is not set","Build::ComputeUpdateStatistics");
      return false;
      }
  
    $previousbuildid = $this->GetPreviousBuildId();
    
    // Find the errors, warnings and test failures
    // Find the current number of errors
    $errors = pdo_query("SELECT count(*) FROM builderror WHERE type='0' 
                           AND buildid=".qnum($this->Id));
    $errors_array  = pdo_fetch_array($errors);
    $nerrors = $errors_array[0]; 
     
    // Number of warnings
    $warnings = pdo_query("SELECT count(*) FROM builderror WHERE type='1' 
                             AND buildid=".qnum($this->Id));
    $warnings_array  = pdo_fetch_array($warnings);
    $nwarnings = $warnings_array[0]; 
          
    // Number of tests failing
    $tests = pdo_query("SELECT count(*) FROM build2test WHERE (status='failed' OR status='notrun')
                         AND buildid=".qnum($this->Id));
    $tests_array  = pdo_fetch_array($tests);
    $ntests = $tests_array[0];
          
    // If we have a previous build
    if($previousbuildid>0)
      {
      $previouserrors = pdo_query("SELECT count(*) FROM builderror WHERE type='0' 
                                     AND buildid=".qnum($previousbuildid));
      $previouserrors_array  = pdo_fetch_array($previouserrors);
      $npreviouserrors = $previouserrors_array[0];
            
      $previouswarnings = pdo_query("SELECT count(*) FROM builderror WHERE type='1' 
                                       AND buildid=".qnum($previousbuildid));
      $previouswarnings_array  = pdo_fetch_array($previouswarnings);
      $npreviouswarnings = $previouswarnings_array[0];
            
      $previoustests = pdo_query("SELECT count(*) FROM build2test WHERE (status='failed' OR status='notrun') 
                                        AND buildid=".qnum($previousbuildid));
      $previoustests_array  = pdo_fetch_array($previoustests);
      $nprevioustests = $previoustests_array[0];
            
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
    add_last_sql_error("compute_update_statistics"); 
    $nauthors = $nauthors_array[0];
    
    //add_log("Nauthors = ".$nauthors,"compute_update_statistics");
   
    $newbuild = 1;
    $previousauthor = "";
    // Loop through the updated files
    $updatefiles = pdo_query("SELECT author,checkindate,filename FROM updatefile WHERE buildid=".qnum($this->Id)." 
                              AND checkindate>'1980-01-0100:00:00' ORDER BY author ASC, checkindate ASC");
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
        $warningdiff = $this->FindRealErrors("WARNING",$author,$buildid,$filename);
        $errordiff = $this->FindRealErrors("ERROR",$author,$buildid,$filename);
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
    add_last_sql_error("add_update_statistics");
                                            
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
             
      add_last_sql_error("add_update_statistics");
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
                 nfailedtests=nfailedtests+'$nfailedtests' WHERE userid='$userid' AND projectid='$projectid' AND checkindate>'$checkindate'");
             
      add_last_sql_error("add_update_statistics");            
             
      // Find the previous userstatistics
      $previous = pdo_query("SELECT totalupdatedfiles,totalbuilds,nfixedwarnings,nfailedwarnings,nfixederrors,nfailederrors,nfixedtests,nfailedtests
                             FROM userstatistics WHERE userid='$userid' AND projectid='$projectid' AND checkindate<'$checkindate' ORDER BY checkindate DESC LIMIT 1");
      add_last_sql_error("compute_update_statistics");             
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
                    VALUES ($userid,$projectid,'$checkindate',$totalupdatedfiles,$totalbuilds,$nfixedwarnings,$nfailedwarnings,$nfixederrors,$nfailederrors,$nfixedtests,$nfailedtests)
                    ");
      add_last_sql_error("add_update_statistics");
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
      add_last_sql_error("Build GetName");
      return false;
      }
    $build_array = pdo_fetch_array($build);
    return $build_array['name'];
    }


} // end class Build
?>
