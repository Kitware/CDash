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
include('test.php');

class BuildUserNote
{
  var $UserId;
  var $Note;
  var $TimeStamp;
  var $Status;
  var $BuildId;
  
  function SetValue($tag,$value)  
    {
    switch($tag)
      {
      case "USERID": $this->UserId = $value;break;
      case "NOTE": $this->Note = $value;break;
      case "TIMESTAMP": $this->TimeStamp = $value;break;
      case "STATUS": $this->Status = $value;break;
      }
    } 
    
  // Insert in the database
  function Insert()
    {
    if(!$this->BuildId)
      {
      echo "BuildUserNote::Insert(): BuildId is not set<br>";
      return false;
      }
      
    if(!$this->UserId)
      {
      echo "BuildUserNote::Insert(): UserId is not set<br>";
      return false;
      }
      
    $query = "INSERT INTO buildnote (buildid,userid,note,timestamp,status)
              VALUES ('$this->BuildId','$this->UserId','$this->Note','$this->TimeStamp','$this->Status')";                     
    if(!pdo_query($query))
      {
      add_last_sql_error("BuildUserNote Insert");
      return false;
      }  
    return true;
    }        
}

/** BuildErrorDiff */
class BuildErrorDiff
{
  var $Type;
  var $Difference;
  
  function SetValue($tag,$value)  
    {
    switch($tag)
      {
      case "BUILDERRORDIFF": $this->Difference = $value;break;
      }
    } 
    
    /** Return if exists */
  function Exists()
    {
    $query = pdo_query("SELECT count(*) FROM builderrordiff WHERE buildid='".$this->BuildId."'");  
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
    if(!$this->BuildId)
      {
      echo "BuildErrorDiff::Save(): BuildId not set<br>";
      return false;    
      }
      
    if($this->Exists())
      {
      // Update
      $query = "UPDATE builderrordiff SET";
      $query .= " type='".$this->Type."'";
      $query .= ",difference='".$this->Difference."'";
      $query .= " WHERE buildid='".$this->BuildId."'";
      if(!pdo_query($query))
        {
        add_last_sql_error("BuildErrorDiff Update");
        return false;
        }
      }
    else // insert
      {    
      $query = "INSERT INTO builderrordiff (buildid,type,difference)
                 VALUES ('$this->BuildId','$this->Type','$this->Difference')";                     
       if(!pdo_query($query))
         {
         add_last_sql_error("BuildErrorDiff Create");
         return false;
         }  
       }
    return true;
    }      
}

/** BuildError */
class BuildError
{
  var $Type;
  var $LogLine;
  var $Text;
  var $SourceFile;
  var $SourceLine;
  var $PreContext;
  var $PostContext;
  var $RepeatCount;
  var $BuildId;
  
  function SetValue($tag,$value)  
    {
    switch($tag)
      {
      case "TYPE": $this->Type = $value;break;
      case "LOGLINE": $this->LogLine = $value;break;
      case "TEXT": $this->Text = $value;break;
      case "SOURCEFILE": $this->SourceFile = $value;break;
      case "SOURCELINE": $this->SourceLine = $value;break;
      case "PRECONTEXT": $this->PreContext = $value;break;
      case "POSTCONTEXT": $this->PostContext = $value;break;
      case "REPEATCOUNT": $this->RepeatCount = $value;break;
      }
    }
      
  // Insert in the database (no update possible)
  function Insert()
    {
    if(!$this->BuildId)
      {
      echo "BuildError::Insert(): BuildId not set<br>";
      return false;    
      }
    
    $text = addslashes($this->Text);
    $precontext = addslashes($this->PreContext);
    $postcontext = addslashes($this->PostContext);
      
    $query = "INSERT INTO builderror (buildid,type,logline,text,sourcefile,sourceline,precontext,postcontext,repeatcount)
              VALUES ('$this->BuildId','$this->Type','$this->LogLine','$text','$this->SourceFile','$this->SourceLine',
              '$precontext','$postcontext','$this->RepeatCount')";                     
    if(!pdo_query($query))
      {
      add_last_sql_error("BuildError Insert");
      return false;
      }  
    return true;
    } // end insert
}

class BuildInformation
{
  var $BuildId;
  var $OSName;
  var $OSPlatform;
  var $OSRelease;
  var $OSVersion;
  var $CompilerName;
  var $CompilerVersion;
  
  function SetValue($tag,$value)  
    {
    switch($tag)
      { 
      case "OSNAME": $this->OSName = $value;break;
      case "OSRELEASE": $this->OSRelease = $value;break;
      case "OSVERSION": $this->OSVersion = $value;break;
      case "OSPLATFORM": $this->OSPlatform = $value;break;
      case "COMPILERNAME": $this->CompilerName = $value;break;
      case "COMPILERVERSION": $this->CompilerVersion = $value;break;
      }
    }
 
    
  /** Save the site information */
  function Save()
    {
    if($this->OSName!="" || $this->OSPlatform!="" || $this->OSRelease!="" || $this->OSVersion!="")
       {
       pdo_query ("INSERT INTO buildinformation (buildid,osname,osrelease,osversion,osplatform,compilername,compilerversion) 
                    VALUES (".qnum($this->BuildId).",'$this->OSName','$this->OSRelease',
                            '$this->OSVersion','$this->OSPlatform','unknown','unknown')");
       add_last_sql_error("BuildInformation Insert");
       }
    } // end function save  
}

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
    
  /** Update the end time */
  function UpdateEndTime($end_time)
    {
    if(!$this->Id)
      {
      return false;
      }
      
    $query = "UPDATE build SET endtime=$endtime WHERE id='$this->Id')";                     
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

  /**  et the value */
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
      }
    }
  
  function GetIdFromName()
    {
    // First we check if the build already exists if this is the case we delete all related information regarding
    // The previous build
    $build = pdo_query("SELECT id FROM build WHERE projectid=".qnum($this->ProjectId)." AND siteid=".qnum($this->SiteId).
                        " AND name='".$this->Name."' AND stamp='".$this->Stamp."'");

    if(pdo_num_rows($build)>0)
      {
      $build_array = pdo_fetch_array($build);
      return $build_array["id"];
      }
    echo pdo_error();    
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
    return true;
    }   
}
?>
