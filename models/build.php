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

class BuildNote
{
  var $Id;
  var $Time;
  var $Text;
  var $Name;
  var $Crc32;
  var $BuildId;
  
  function SetValue($tag,$value)  
    {
    switch($tag)
      {
      case "TIME": $this->Time = $value;break;
      case "TEXT": $this->Text = $value;break;
      case "NAME": $this->Name = $value;break;
      case "CRC32": $this->Crc32 = $value;break;
      }
    } 
  
  /** Get the CRC32 */
  function GetCrc32()
    {
    if(strlen($this->Crc32)>0)
      {
      return $this->Crc32;
      }
    
    // Compute the CRC32 for the note
    $text = pdo_real_escape_string($this->Text);
    $timestamp = pdo_real_escape_string($this->Time);
    $name = pdo_real_escape_string($this->Name);
   
    $this->Crc32 = crc32($text.$name);
    return $this->Crc32;
    }
  
    
  // Insert in the database
  function Insert()
    {
    if(!$this->BuildId)
      {
      echo "BuildUserNote::Insert(): BuildId is not set<br>";
      return false;
      }
      
    // Check if the note already exists
    $crc32 = $this->GetCrc32();
    
    $text = pdo_real_escape_string($this->Text);
    $timestamp = pdo_real_escape_string($this->Time);
    $name = pdo_real_escape_string($this->Name);
 
    $notecrc32 =  pdo_query("SELECT id FROM note WHERE crc32='$crc32'");
    if(pdo_num_rows($notecrc32) == 0)
      {
      if($this->Id)
        {
        $query = "INSERT INTO note (id,text,name,crc32) VALUES ('$this->Id','$text','$name','$crc32')";
        }
      else
        {
        $query = "INSERT INTO note (text,name,crc32) VALUES ('$text','$name','$crc32')";
        }
      if(!pdo_query($query))
        {
        add_last_sql_error("BuildUserNote Insert");
        return false;
        }  
      $this->NoteId = pdo_insert_id("note");
      }
    else // already there
      {
      $notecrc32_array = pdo_fetch_array($notecrc32);
      $this->Id = $notecrc32_array["id"];
      }
   
    if(!$this->Id)
      {
      echo "BuildUserNote::Insert(): No NoteId";
      return false;
      }
  
    $query = "INSERT INTO build2note (buildid,noteid,time)
              VALUES ('$this->BuildId','$this->Id','$this->Time')";                     
    if(!pdo_query($query))
      {
      add_last_sql_error("BuildUserNote Insert");
      return false;
      }  
    return true;
    }
}

class BuildUpdateFile
{
  var $Filename;
  var $CheckindDate;
  var $Author;
  var $Email;
  var $Log;  
  var $Revision;  
  var $PriorRevision;
  
  function SetValue($tag,$value)  
    {
    switch($tag)
      {
      case "FILENAME": $this->Filename = $value;break;
      case "CHECKINDATE": $this->CheckinDate = $value;break;
      case "AUTHOR": $this->Author = $value;break;
      case "EMAIL": $this->Email = $value;break;
      case "LOG": $this->Log = $value;break;
      case "REVISION": $this->Revision = $value;break;
      case "PRIORREVISION": $this->PriorRevision = $value;break;    
      }
    } 
}

class BuildUpdate
{
  var $Files;
  var $StartTime;
  var $EndTime;
  var $Command;
  var $Type;
  var $Status;
  
  function AddFile($file)
    {
    $this->Files[] = $file;
    }
    
  function SetValue($tag,$value)  
    {
    switch($tag)
      {
      case "STARTTIME": $this->StartTime = $value;break;
      case "ENDTIME": $this->EndTime = $value;break;
      case "COMMAND": $this->Command = $value;break;
      case "TYPE": $this->Type = $value;break;
      case "STATUS": $this->Status = $value;break;
      }
    }    
}

/** BuildConfigureError */
class BuildConfigureError
{
  var $Type;
  var $Text;
  var $BuildId;
  
  function SetValue($tag,$value)  
    {
    switch($tag)
      {
      case "TYPE": $this->Type = $value;break;
      case "TEXT": $this->Text = $value;break;
      }
    }
  
  /** Return if exists */
  function Exists()
    {
    $query = pdo_query("SELECT count(*) FROM configureerror WHERE buildid='".$this->BuildId."'
                         AND type='".$this->Type."' AND text='".$this->Text."'");  
    $query_array = pdo_fetch_array($query);
    if($query_array['count(*)']>0)
      {
      return true;
      }
    return false;
    }      
      
  /** Save in the database */
  function Save()
    {
    if(!$this->BuildId)
      {
      echo "BuildConfigureError::Save(): BuildId not set<br>";
      return false;    
      }
      
    if(!$this->Exists())
      {
      $query = "INSERT INTO configureerror (buildid,type,text)
                 VALUES ('$this->BuildId','$this->Type','$this->Text')";                     
      if(!pdo_query($query))
        {
        add_last_sql_error("BuildConfigureError Create");
        return false;
        }  
      }
    return true;
    }        
}

class BuildConfigureErrorDiff
{
  var $Type;
  var $Difference;
  var $BuildId;
  
  function SetValue($tag,$value)  
    {
    switch($tag)
      {
      case "BUILDERRORDIFF": $this->Difference = $value;break;
      case "TYPE": $this->Type = $value;break;
      }
    }
    /** Return if exists */
  function Exists()
    {
    $query = pdo_query("SELECT count(*) FROM configureerrordiff WHERE buildid='".$this->BuildId."'");  
    $query_array = pdo_fetch_array($query);
    if($query_array['count(*)']>0)
      {
      return true;
      }
    return false;
    }      
      
  /** Save in the database */
  function Save()
    {
    if(!$this->BuildId)
      {
      echo "BuildConfigureErrorDiff::Save(): BuildId not set";
      return false;    
      }
      
    if($this->Exists())
      {
      // Update
      $query = "UPDATE configureerrordiff SET";
      $query .= " type='".$this->Type."'";
      $query .= ",difference='".$this->Difference."'";
      $query .= " WHERE buildid='".$this->BuildId."'";
      if(!pdo_query($query))
        {
        add_last_sql_error("BuildConfigureErrorDiff Update");
        return false;
        }
      }
    else // insert  
      {
      $query = "INSERT INTO configureerrordiff (buildid,type,difference)
                 VALUES ('$this->BuildId','$this->Type','$this->Difference')";                     
      if(!pdo_query($query))
        {
        add_last_sql_error("BuildConfigureErrorDiff Create");
        return false;
        }  
      }
    return true;
    }        
    
}

class BuildConfigure
{
  var $StartTime;
  var $EndTime;
  var $Command;
  var $Log;
  var $Status;
  var $Errors;
  var $ErrorDifferences;
  var $BuildId;
  
  function AddError($error)
    {
    $error->BuildId = $this->BuildId;
    $error->Save();
    }
  
  function AddErrorDifference($diff)
    {
    $diff->BuildId = $this->BuildId;
    $diff->Save();
    }
    
  function SetValue($tag,$value)  
    {
    switch($tag)
      {
      case "STARTTIME": $this->StartTime = $value;break;
      case "ENDTIME": $this->EndTime = $value;break;
      case "COMMAND": $this->Command = $value;break;
      case "LOG": $this->Log = $value;break;
      case "STATUS": $this->Status = $value;break;
      }
    }
      
  // Save in the database
  function Insert()
    {
    if(!$this->BuildId)
      {
      echo "BuildConfigure::Save(): BuildId not set<br>";
      return false;    
      }
      
    $query = "INSERT INTO configure (buildid,starttime,endtime,command,log,status)
              VALUES ('$this->BuildId','$this->StartTime','$this->EndTime','$this->Command','$this->Log','$this->Status')";                     
    if(!pdo_query($query))
      {
      add_last_sql_error("BuildConfigure Create");
      return false;
      }  
    return true;
    }  // end insert            
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

class BuildTestDiff
{
  var $Type;
  var $Difference;
  var $BuildId;
  
  function SetValue($tag,$value)  
    {
    switch($tag)
      {
      case "TESTDIFF": $this->Difference = $value;break;
      }
    }
    
  // Insert in the database
  function Insert()
    {
    if(!$this->BuildId)
      {
      echo "BuildTestDiff::Insert(): BuildId is not set<br>";
      return false;
      }
      
    $query = "INSERT INTO testdiff (buildid,type,difference) VALUES ('$this->BuildId','$this->Type','$this->Difference')";                     
    if(!pdo_query($query))
      {
      add_last_sql_error("BuildTestDiff Insert");
      return false;
      }  
    return true;
    }      
}
          
class BuildTest
{
  var $TestId;
  var $Status;
  var $Time;
  var $TimeMean;
  var $TimeStd;
  var $TimeStatus;
  var $Test;
  var $BuildId;
  
  function SetTest($test)
    {
    $this->TestId = $test->Id;
    $this->Test = $test;
    }
  
  function SetValue($tag,$value)  
    {
    switch($tag)
      {
      case "TESTID": $this->TestId = $value;break;
      case "STATUS": $this->Status = $value;break;
      case "TIME": $this->Time = $value;break;
      case "TIMEMEAN": $this->TimeMean = $value;break;
      case "TIMESTD": $this->TimeStd = $value;break;
      case "TIMESTATUS": $this->TimeStatus = $value;break;
      }
    }    

  // Insert in the database
  function Insert()
    {
    if(!$this->BuildId)
      {
      echo "BuildTest::Insert(): BuildId is not set<br>";
      return false;
      }
      
    if($this->Test)
      {
      // Save the test
      $this->Test->Insert();
      $this->TestId = $this->Test->Id;
      }
      
    if(!$this->TestId)
      {
      echo "BuildTest::Insert(): TestId is not set<br>";
      return false;
      }
      
    $query = "INSERT INTO build2test (buildid,testid,status,time,timemean,timestd,timestatus)
                 VALUES ('$this->BuildId','$this->TestId','$this->Status','$this->Time','$this->TimeMean','$this->TimeStd','$this->TimeStatus')";                     
    if(!pdo_query($query))
      {
      add_last_sql_error("BuildTest Insert");
      return false;
      }  
    return true;
    }    
}

class BuildCoverageSummary
{
  var $LocTested;
  var $LocUntested;
}

class BuildCoverageSummaryDiff
{
  var $LocTested;
  var $LocUntested;
}
    
class BuildCoverageFile
{  
  var $Id;
  var $Filename;
  var $value;
  var $FullPath;
  var $Crc32;
}

class BuildCoverageLog
{  
  var $FileId;
  var $Line;
  var  $Code;  
}

class BuildCoverage
{  
  var $Covered;
  var $LocTested;
  var $LocUntested;
  var $BranchsTested;
  var $branchsUntested;
  var $FunctionsTested;
  var $FunctionsUntested;

  var $FileIds;
  var $Logs;
}

class BuildDynamicAnalysisDefect
{
  var $Type;
  var $Value;
}

class BuildDynamicAnalysis
{
  var $Id;
  var $Status;
  var $Checker;
  var $Name;
  var $Path;
  var $FullCommandLine;
  var $Log;
  var $Defects;
}

class Build
{
  var $Id;
  var $SiteId;
  var $ProjectId;
  var $Stamp;
  var $Name;
  var $Type;
  var $Generator;
  var $Starttime;
  var $Endtime;
  var $Submittime;
  var $Command;
  var $Log;
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
        $idvalue = "'".$this->Id."',";
        }
        
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
      else
        {
        echo "WARNING: Build::Insert(): GroupId not defined!";
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
    return true;
    }   
}
?>
