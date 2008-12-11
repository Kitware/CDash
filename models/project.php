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

class DailyUpdateFile
{ 
  var $Filename;
  var $CheckinDate;
  var $Author;
  var $Log;
  var $Revision;
  var $PriorRevision;
  var $DailyUpdateId;
  
  function SetValue($tag,$value)  
    {
    switch($tag)
      {
      case "FILENAME": $this->Filename = $value;break;
      case "CHECKINDATE": $this->CheckinDate = $value;break;
      case "AUTHOR": $this->Author = $value;break;
      case "LOG": $this->Log = $value;break;
      case "REVISION": $this->Revision = $value;break;
      case "PRIORREVISION": $this->PriorRevision = $value;break;
      }
    }
    
  /** Check if exists */  
  function Exists()
    {
    // If no id specify return false
    if(!$this->DailyUpdateId)
      {
      return false;    
      }
    
    $query = pdo_query("SELECT count(*) FROM dailyupdate WHERE dailyupdateid='".$this->dailyupadteid."' AND filename='".$this->Filename."'");
    $query_array = pdo_fetch_array($query);
    if($query_array['count(*)']==0)
      {
      return false;
      }
    
    return true;  
    }
    
  /** Save the group */
  function Save()
    {
    if(!$this->DailyUpdateId)
      {
      echo "DailyUpdateFile::Save(): DailyUpdateId not set!";
      return false;
      }
    
    if(!$this->Filename)
      {
      echo "DailyUpdateFile::Save(): Filename not set!";
      return false;
      }
      
    if($this->Exists())
      {
      // Update the project
      $query = "UPDATE dailyupdatefile SET";
      $query .= " checkindate='".$this->Command."'";
      $query .= ",author='".$this->Type."'";
      $query .= ",log='".$this->Status."'";
      $query .= ",revision='".$this->Revision."'";
      $query .= ",priorrevision='".$this->PriorRevision."'";
      $query .= " WHERE dailyupdateid='".$this->DailyUpdateId."' AND filename='".$this->Filename."'";
      
      if(!pdo_query($query))
        {
        add_last_sql_error("DailyUpdateFile Update");
        return false;
        }
      }
    else
      {                                              
      if(!pdo_query("INSERT INTO dailyupdatefile (dailyupdateid,filename,checkindate,author,log,revision,priorrevision)
                     VALUES ('$this->DailyUpdateId','$this->Filename','$this->CheckinDate','$this->Author','$this->Log',
                     '$this->Revision','$this->PriorRevision')"))
         {
         add_last_sql_error("DailyUpdateFile Insert");
         return false;
         }
      }
    } // end function save    
}

class DailyUpdate
{ 
  var $Id;
  var $Date;
  var $Command;
  var $Type;
  var $Status;
  var $ProjectId;
  
  function AddFile($file)
    {
    $file->DailyUpdateId = $this->Id;
    $file->Save();
    }
    
  function SetValue($tag,$value)  
    {
    switch($tag)
      {
      case "DATE": $this->Date = $value;break;
      case "COMMAND": $this->Command = $value;break;
      case "TYPE": $this->Type = $value;break;
      case "STATUS": $this->Status = $value;break;
      }
    }
    
  /** Check if exists */  
  function Exists()
    {
    // If no id specify return false
    if(!$this->Id || !$this->Date)
      {
      return false;    
      }
    
    $query = pdo_query("SELECT count(*) FROM dailyupdate WHERE date='".$this->Date."' AND projectid='".$this->ProjectId."'");
    $query_array = pdo_fetch_array($query);
    if($query_array['count(*)']==0)
      {
      return false;
      }
    
    return true;  
    }
    
  /** Save the group */
  function Save()
    {
    if(!$this->ProjectId)
      {
      echo "DailyUpdate::Save(): ProjectId not set!";
      return false;
      }
      
    if($this->Exists())
      {
      // Update the project
      $query = "UPDATE dailyupdate SET";
      $query .= " command='".$this->Command."'";
      $query .= ",type='".$this->Type."'";
      $query .= ",status='".$this->Status."'";
      $query .= " WHERE projectid='".$this->ProjectId."' AND date='".$this->Date."'";
      
      if(!pdo_query($query))
        {
        add_last_sql_error("DailyUpdate Update");
        return false;
        }
      }
    else
      {                                              
      if(pdo_query("INSERT INTO dailyupdate (projectid,date,command,type,status)
                     VALUES ('$this->ProjectId','$this->Date','$this->Command','$this->Type','$this->Status')"))
         {
         $this->Id = pdo_insert_id("dailyupdate");
         }
       else
         {
         add_last_sql_error("DailyUpdate Insert");
         return false;
         }
      }
  } // end function save
}


class BuildGroupRule
{
  var $BuildType;
  var $BuildName;
  var $SiteId;
  var $Expected;
  var $StartTime;
  var $EndTime;  
  var $GroupId;  
  
  function SetValue($tag,$value)  
    {
    switch($tag)
      {
      case "BUILDTYPE": $this->BuildType = $value;break;
      case "BUILDNAME": $this->BuildName = $value;break;
      case "SITEID": $this->SiteId = $value;break;
      case "EXPECTED": $this->Expected = $value;break;
      case "STARTTIME": $this->StartTime = $value;break;
      case "ENDTIME": $this->EndTime = $value;break;
      }
    }
  
  function __construct()
    {
    $this->StartTime = '1980-01-01 00:00:00';
    $this->EndTime = '1980-01-01 00:00:00';
    }
    
  /** Check if the rule already exists */  
  function Exists()
    {
    // If no id specify return false
    if(!$this->GroupId)
      {
      return false;    
      }
    
    $query = pdo_query("SELECT count(*) FROM build2grouprule WHERE 
                        groupid='".$this->GroupId."' AND buildtype='".$this->Position."'
                        AND buildname='".$this->BuildName."'
                        AND siteid='".$this->SiteId."'
                        AND starttime='".$this->StartTime."'
                        AND endtime='".$this->EndTime."'"
                        );
    $query_array = pdo_fetch_array($query);
    if($query_array['count(*)']==0)
      {
      return false;
      }
    return true;  
    }  
    
  /** Save the goup position */
  function Add()
    {
    if(!$this->Exists())
      {
      if(!pdo_query("INSERT INTO build2grouprule (groupid,buildtype,buildname,siteid,expected,starttime,endtime)
                     VALUES ('$this->GroupId','$this->BuildType','$this->BuildName','$this->SiteId','$this->Expected','$this->StartTime','$this->EndTime')"))
         {
         add_last_sql_error("BuildGroupRule Insert()");
         return false;
         }
      }  
    } // end function save    
        
}
  
class BuildGroupPosition
{
  var $Position;
  var $StartTime;
  var $EndTime;
  var $GroupId;
  
  function __construct()
    {
    $this->StartTime = '1980-01-01 00:00:00';
    $this->EndTime = '1980-01-01 00:00:00';
    $this->Position = 1;
    }
    
  function SetValue($tag,$value)  
    {
    switch($tag)
      {
      case "STARTTIME": $this->StartTime = $value;break;
      case "ENDTIME": $this->EndTime = $value;break;
      case "POSITION": $this->Position = $value;break;
      }
    }
    
  /** Check if the position already exists */  
  function Exists()
    {
    // If no id specify return false
    if(!$this->GroupId)
      {
      return false;    
      }
    
    $query = pdo_query("SELECT count(*) FROM buildgroupposition WHERE 
                        buildgroupid='".$this->GroupId."' AND position='".$this->Position."'
                        AND starttime='".$this->StartTime."'
                        AND endtime='".$this->EndTime."'"
                        );
    $query_array = pdo_fetch_array($query);
    if($query_array['count(*)']==0)
      {
      return false;
      }
    return true;  
    }  
    
  /** Save the goup position */
  function Add()
    {
    if(!$this->Exists())
      {
      if(!pdo_query("INSERT INTO buildgroupposition (buildgroupid,position,starttime,endtime)
                     VALUES ('$this->GroupId','$this->Position','$this->StartTime','$this->EndTime')"))
         {
         add_last_sql_error("BuildGroupPosition Insert()");
         return false;
         }
      }  
    } // end function save
}

    
class BuildGroup
{
  var $Id;
  var $Name;
  var $StartTime;
  var $EndTime;
  var $Description;
  var $SummaryEmail;
  var $ProjectId;

  function __construct()
    {
    $this->StartTime = '1980-01-01 00:00:00';
    $this->EndTime = '1980-01-01 00:00:00';
    $this->SummaryEmail = 0;
    }
  
  function SetPosition($position)
    {
    $position->GroupId = $this->Id;
    $position->Add();
    }
    
  function AddRule($rule)
    {
    $rule->GroupId = $this->Id;
    $rule->Add();
    }
    
  /** Get the next position available for that group */
  function GetNextPosition()
    {
    $query = pdo_query("SELECT bg.position FROM buildgroupposition as bg,buildgroup as g 
                        WHERE bg.buildgroupid=g.id AND g.projectid='".$this->ProjectId."' 
                        AND bg.endtime='1980-01-01 00:00:00'
                        ORDER BY bg.position DESC LIMIT 1");
    if(pdo_num_rows($query)>0)
      {
      $query_array = pdo_fetch_array($query);
      return $query_array['position']+1;
      }
    return 1;    
    }  
    
  function SetValue($tag,$value)  
    {
    switch($tag)
      {
      case "NAME": $this->Name = $value;break;
      case "DESCRIPTION": $this->Description = $value;break;
      case "STARTTIME": $this->StartTime = $value;break;
      case "ENDTIME": $this->EndTime = $value;break;
      case "SUMMARYEMAIL": $this->SummaryEmail = $value;break;
      case "PROJECTID": $this->ProjectId = $value;break;  
      }
    }
    
  /** Check if the group already exists */  
  function Exists()
    {
    // If no id specify return false
    if(!$this->Id || !$this->ProjectId)
      {
      return false;    
      }
    
    $query = pdo_query("SELECT count(*) FROM buildgroup WHERE id='".$this->Id."' AND projectid='".$this->ProjectId."'");
    $query_array = pdo_fetch_array($query);
    if($query_array['count(*)']==0)
      {
      return false;
      }
    
    return true;  
    }
    
  /** Save the group */
  function Save()
    {
    if($this->Exists())
      {
      // Update the project
      $query = "UPDATE buildgroup SET";
      $query .= " name='".$this->Name."'";
      $query .= ",projectid='".$this->ProjectId."'";
      $query .= ",starttime='".$this->StartTime."'";
      $query .= ",endtime='".$this->EndTime."'";
      $query .= ",description='".$this->Description."'";
      $query .= ",summaryemail='".$this->SummaryEmail."'";
      $query .= " WHERE id='".$this->Id."'";
      
      if(!pdo_query($query))
        {
        add_last_sql_error("BuildGroup Update");
        return false;
        }
      }
    else
      {
      $id = "";
      $idvalue = "";
      if($this->Id)
        {
        $id = "id,";
        $idvalue = "'".$this->Id."',";
        }
                                                      
      if(pdo_query("INSERT INTO buildgroup (".$id."name,projectid,starttime,endtime,description)
                     VALUES (".$idvalue."'$this->Name','$this->ProjectId','$this->StartTime','$this->EndTime','$this->Description')"))
         {
         $this->Id = pdo_insert_id("buildgroup");
         }
       else
         {
         add_last_sql_error("Buildgroup Insert");
         return false;
         }
    
      // Insert the default position for this group
      // Find the position for this group
      $position = $this->GetNextPosition();               
      pdo_query("INSERT INTO buildgroupposition(buildgroupid,position,starttime,endtime) 
                 VALUES ('".$this->Id."','".$position."','".$this->StartTime."','".$this->EndTime."')");
 
      }  
    } // end function save
  
}

/** Main project class */
class Project
{
  var $Name;
  var $Id; 
  var $Description;
  var $HomeUrl;
  var $CvsUrl;
  var $DocumentationUrl;
  var $BugTrackerUrl;
  var $ImageId;
  var $Public;
  var $CoverageThreshold;
  var $NightlyTime;
  var $GoogleTracker;
  var $EmailBuildMissing;
  var $EmailLowCoverage;
  var $EmailTestTimingChanged;
  var $EmailBrokenSubmission;
  var $CvsViewerType;
  var $TestTimeStd;
  var $TestTimeStdThreshold;
  var $ShowTestTime;
  var $TestTimeMaxStatus;
  var $EmailMaxItems;
  var $EmailMaxChars;

  function __construct()
    {
    $this->EmailBuildMissing=0;
    $this->EmailLowCoverage=0;
    $this->EmailTestTimingChanged=0;
    $this->EmailBrokenSubmission=0;
    }

  /** Add a build group */
  function AddBuildGroup($buildgroup)
    {
    $buildgroup->ProjectId = $this->Id;
    $buildgroup->Save();
    }

  function AddDailyUpdate($dailyupdate)
    {
    $dailyupdate->ProjectId = $this->Id;
    $dailyupdate->Save();
    }
    
  function SetValue($tag,$value)  
    {
    switch($tag)
      {
      case "NAME": $this->Name = $value;break;
      case "DESCRIPTION": $this->Description = $value;break;
      case "HOMEURL": $this->HomeUrl = $value;break;
      case "CVSURL": $this->CvsUrl = $value;break;
      case "BUGTRACKERURL": $this->DocumentationUrl = $value;break;
      case "DOCUMENTATIONURL": $this->BugTrackerUrl = $value;break;
      case "IMAGEID": $this->ImageId = $value;break;
      case "PUBLIC": $this->Public = $value;break;
      case "COVERAGETHRESHOLD": $this->CoverageThreshold = $value;break;
      case "NIGHTLYTIME": $this->NightlyTime = $value;break;
      case "GOOGLETRACKER": $this->GoogleTracker = $value;break;
      case "EMAILBUILDMISSING": $this->EmailBuildMissing = $value;break;
      case "EMAILLOWCOVERAGE": $this->EmailLowCoverage = $value;break;
      case "EMAILTESTTIMINGCHANGED": $this->EmailTestTimingChanged = $value;break;
      case "EMAILBROKENSUBMISSION": $this->EmailBrokenSubmission = $value;break;
      case "CVSVIEWERTYPE": $this->CvsViewerType = $value;break;
      case "TESTTIMESTD": $this->TestTimeStd = $value;break;
      case "TESTTIMESTDTHRESHOLD": $this->TestTimeStdThreshold = $value;break;
      case "SHOWTESTTIME": $this->ShowTestTime = $value;break;
      case "TESTTIMEMAXSTATUS": $this->TestTimeMaxStatus = $value;break;
      case "EMAILMAXITEMS": $this->EmailMaxItems = $value;break;
      case "EMAILMAXCHARS": $this->EmailMaxChars = $value;break;
      }
    }    
  
  /** Delete a project */
  function Delete()
    {
    if(!$this->Id)
      {
      return false;    
      }
    // Remove the project groups and rules
    $buildgroup = pdo_query("SELECT * FROM buildgroup WHERE projectid=$this->Id");
    while($buildgroup_array = pdo_fetch_array($buildgroup))
      {
      $groupid = $buildgroup_array["id"];
      pdo_query("DELETE FROM buildgroupposition WHERE buildgroupid=$groupid");
      pdo_query("DELETE FROM build2grouprule WHERE groupid=$groupid");
      pdo_query("DELETE FROM build2group WHERE groupid=$groupid");
      }
   
    pdo_query("DELETE FROM buildgroup WHERE projectid=$this->Id");
    pdo_query("DELETE FROM project WHERE id=$this->Id");
    pdo_query("DELETE FROM user2project WHERE projectid=$this->Id");
    }
      
  /** Return if a project exists */
  function Exists()
    {
    // If no id specify return false
    if(!$this->Id)
      {
      return false;    
      }
    
    $query = pdo_query("SELECT count(*) FROM project WHERE id='".$this->Id."'");
    $query_array = pdo_fetch_array($query);
    if($query_array[0]>0)
      {
      return true;
      }
    return false;
    }      
      
  // Save the project in the database
  function Save()
    {
    // Check if the project is already
    if($this->Exists())
      {
      // Trim the name
      $this->Name = trim($this->Name);
      
      // Update the project
      $query = "UPDATE project SET ";
      $query .= "description='".$this->Description."'";
      $query .= ",homeurl='".$this->HomeUrl."'";
      $query .= ",cvsurl='".$this->CvsUrl."'";
      $query .= ",documentationurl='".$this->DocumentationUrl."'";
      $query .= ",bugtrackerurl='".$this->BugTrackerUrl."'";
      $query .= ",public=".qnum($this->Public);
      $query .= ",coveragethreshold=".qnum($this->CoverageThreshold);
      $query .= ",nightlytime='".$this->NightlyTime."'";
      $query .= ",googletracker='".$this->GoogleTracker."'";
      $query .= ",emailbuildmissing=".qnum($this->EmailBuildMissing);
      $query .= ",emaillowcoverage=".qnum($this->EmailLowCoverage);
      $query .= ",emailtesttimingchanged=".qnum($this->EmailTestTimingChanged);
      $query .= ",emailbrokensubmission=".qnum($this->EmailBrokenSubmission);
      $query .= ",cvsviewertype='".$this->CvsViewerType."'";
      $query .= ",testtimestd=".qnum($this->TestTimeStd);
      $query .= ",testtimestdthreshold=".qnum($this->TestTimeStdThreshold);
      $query .= ",showtesttime=".qnum($this->ShowTestTime);
      $query .= ",testtimemaxstatus=".qnum($this->TestTimeMaxStatus);
      $query .= ",emailmaxitems=".qnum($this->EmailMaxItems);
      $query .= ",emailmaxchars=".qnum($this->EmailMaxChars);
      $query .= " WHERE id=".qnum($this->Id)."";
      
      if(!pdo_query($query))
        {
        add_last_sql_error("Project Update");
        return false;
        }
      }
    else // insert the project
      {      
      $id = "";
      $idvalue = "";
      if($this->Id)
        {
        $id = "id,";
        $idvalue = "'".$this->Id."',";
        }
      
      if(strlen($this->ImageId) == 0)
        {
        $this->ImageId = 0;
        }
      
      // Trim the name
      $this->Name = trim($this->Name);
      
      $query = "INSERT INTO project(".$id."name,description,homeurl,cvsurl,bugtrackerurl,documentationurl,public,imageid,coveragethreshold,nightlytime,
                                    googletracker,emailbrokensubmission,emailbuildmissing,emaillowcoverage,emailtesttimingchanged,cvsviewertype,
                                    testtimestd,testtimestdthreshold,testtimemaxstatus,emailmaxitems,emailmaxchars,showtesttime)
                 VALUES (".$idvalue."'$this->Name','$this->Description','$this->HomeUrl','$this->CvsUrl','$this->BugTrackerUrl','$this->DocumentationUrl',
                 ".qnum($this->Public).",".qnum($this->ImageId).",".qnum($this->CoverageThreshold).",'$this->NightlyTime',
                 '$this->GoogleTracker',".qnum($this->EmailBrokenSubmission).",".qnum($this->EmailBuildMissing).",".qnum($this->EmailLowCoverage).",
                 ".qnum($this->EmailTestTimingChanged).",'$this->CvsViewerType',".qnum($this->TestTimeStd).",".qnum($this->TestTimeStdThreshold).",
                 ".qnum($this->TestTimeMaxStatus).",".qnum($this->EmailMaxItems).",".qnum($this->EmailMaxChars).",".qnum($this->ShowTestTime).")";
                    
       if(pdo_query($query))
         {
         $this->Id = pdo_insert_id("project");
         }
       else
         {
         add_last_sql_error("Project Create");
         return false;
         }  
       }
      
    return true;
    }  
    
  /** Get the user's role */
  function GetUserRole($userid)
    {
    if(!$this->Id || !is_numeric($this->Id))
      {
      return -1;
      }
     
    $role = -1; 
      
    $user2project = pdo_query("SELECT role FROM user2project WHERE userid='$userid' AND projectid='".$this->Id."'");
    if(pdo_num_rows($user2project)>0)
      {
      $user2project_array = pdo_fetch_array($user2project);
      $role = $user2project_array["role"];
      }
    
    return $role;
    }
  
  /** Return true if the project exists */
  function ExistsByName($name)
    {
    $project = pdo_query("SELECT id FROM project WHERE name='$name'");
    if(pdo_num_rows($project)>0)
      {
      return true;
      } 
    return false;    
    }
  
  /** Get the logo id */
  function GetLogoId()
    {
    $query = pdo_query("SELECT imageid FROM project WHERE id=".$this->Id);
    
    if(!$query)
      {
      add_last_sql_error("Project GetLogoId");
      return 0;
      }
    
    if($query_array = pdo_fetch_array($query))
      {
      return qnum($query_array["imageid"]);
      }
    return 0;  
    }
  
  /** Fill in all the information from the database */
  function Fill()
    {
    if(!$this->Id)
      {
      echo "Project Fill(): Id not set";
      }
  
    $project = pdo_query("SELECT * FROM project WHERE id=".$this->Id);
    if(!$project)
      {
      add_last_sql_error("Project Fill");
      return;
      }
    
    if($project_array = pdo_fetch_array($project))
      {
      $this->Name = $project_array['name'];
      $this->Description = $project_array['description'];
      $this->HomeUrl = $project_array['homeurl'];
      $this->CvsUrl = $project_array['cvsurl'];
      $this->DocumentationUrl = $project_array['documentationurl'];
      $this->BugTrackerUrl = $project_array['bugtrackerurl'];
      $this->ImageId = $project_array['imageid'];
      $this->Public = $project_array['public'];
      $this->CoverageThreshold = $project_array['coveragethreshold'];
      $this->NightlyTime = $project_array['nightlytime'];
      $this->GoogleTracker = $project_array['googletracker'];
      $this->EmailBuildMissing = $project_array['emailbuildmissing'];
      $this->EmailLowCoverage = $project_array['emaillowcoverage'];
      $this->EmailTestTimingChanged = $project_array['emailtesttimingchanged'];
      $this->EmailBrokenSubmission = $project_array['emailbrokensubmission'];
      $this->CvsViewerType = $project_array['cvsviewertype'];
      $this->TestTimeStd = $project_array['testtimestd'];
      $this->TestTimeStdThreshold = $project_array['testtimestdthreshold'];
      $this->ShowTestTime = $project_array['showtesttime'];
      $this->TestTimeMaxStatus = $project_array['testtimemaxstatus'];
      $this->EmailMaxItems = $project_array['emailmaxitems'];
      $this->EmailMaxChars = $project_array['emailmaxchars'];
      }
    }  
    
  /** Add a logo */
  function AddLogo($contents,$filetype)
    {
    if(strlen($contents) == 0)
      {
      return; 
      }

    $imgid = $this->GetLogoId();
    $checksum = crc32($contents);
    
    
    
    //check if we already have a copy of this file in the database
    $sql = "SELECT id FROM image WHERE checksum = '$checksum'";
    $result = pdo_query("$sql");
    if($row = pdo_fetch_array($result))
      {
      $imgid = $row["id"];
      // Insert into the project
      pdo_query("UPDATE project SET imageid=".qnum($imgid)." WHERE id=".$this->Id);
      add_last_sql_error("Project AddLogo");
      }
    else if($imgid==0)
      {
      include("config.php");
      if($CDASH_DB_TYPE == "pgsql")
        {
        $contents = pg_escape_bytea($contents);
        }
      $sql = "INSERT INTO image(img, extension, checksum) VALUES ('$contents', '$filetype', '$checksum')";
      if(pdo_query("$sql"))
        {
        $imgid = pdo_insert_id("image");
        
        // Insert into the project
        pdo_query("UPDATE project SET imageid=".qnum($imgid)." WHERE id=".qnum($this->Id));
        add_last_sql_error("Project AddLogo");
        }
      }
     else // update the current image
       {
       include("config.php");
       if($CDASH_DB_TYPE == "pgsql")
         {
         $contents = pg_escape_bytea($contents);
         }
       pdo_query("UPDATE image SET img='$contents',extension='$filetype',checksum='$checksum' WHERE id=".qnum($imgid));
       add_last_sql_error("Project AddLogo");
       } 
    return $imgid;   
    }
  
  /** Add CVS/SVN repositories */
  function AddRepositories($repositories)
    {
    // First we update/delete any registered repositories
    $currentRepository = 0;
    $repositories_query = pdo_query("SELECT repositoryid from project2repositories WHERE projectid=".qnum($this->Id)." ORDER BY repositoryid");
    while($repository_array = pdo_fetch_array($repositories_query))
      {
      $repositoryid = $repository_array["repositoryid"];
      if(!isset($repositories[$currentRepository]) || strlen($repositories[$currentRepository])==0)
        {
        $query = pdo_query("SELECT * FROM project2repositories WHERE repositoryid=".qnum($repositoryid));
        if(pdo_num_rows($query)==1)
          {
          pdo_query("DELETE FROM repositories WHERE id='$repositoryid'");
          }
        pdo_query("DELETE FROM project2repositories WHERE projectid=".qnum($this->Id)." AND repositoryid=.".qnum($repositoryid));  
        }
      else
        {
        pdo_query("UPDATE repositories SET url='$repositories[$currentRepository]' WHERE id=".qnum($repositoryid));
        }  
      $currentRepository++;
      }
  
    //  Then we add new repositories
    for($i=$currentRepository;$i<count($repositories);$i++)
      {
      $url = $repositories[$i];
      if(strlen($url) == 0)
        {
        continue;
        }
    
      // Insert into repositories if not any
      $repositories_query = pdo_query("SELECT id FROM repositories WHERE url='$url'");
      if(pdo_num_rows($repositories_query) == 0)
        {
        pdo_query("INSERT INTO repositories (url) VALUES ('$url')");
        $repositoryid = pdo_insert_id("repositories");
        }
      else
        {
        $repositories_array = pdo_fetch_array($repositories_query);
        $repositoryid = $repositories_array["id"];
        } 
      pdo_query("INSERT INTO project2repositories (projectid,repositoryid) VALUES (".qnum($this->Id).",'$repositoryid')");
      echo pdo_error();   
      } // end add repository
    } // end function   AddRepositories
 
   /** Get the repositories */
   function GetRepositories()
     {
     $repositories = array();
     $repository = pdo_query("SELECT url from repositories,project2repositories
                               WHERE repositories.id=project2repositories.repositoryid
                               AND project2repositories.projectid=".qnum($this->Id));
     while($repository_array = pdo_fetch_array($repository))
       {
       $rep['url'] = $repository_array['url'];
       $repositories[] = $rep;
       }
     return $repositories;   
     } // end GetRepositories


  /** Get Ids of all the project registered
   *  Maybe this function should go somewhere else but for now here */
  function GetIds()
    {
    $ids = array();
    $query = pdo_query("SELECT id FROM project ORDER BY id");
    while($query_array = pdo_fetch_array($query))
      {
      $ids[] = $query_array["id"];
      }
    return $ids;    
    }
    
}  // end class Project



?>
