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
/** Coverage file to users */
class CoverageFile2User
{
  var $UserId;
  var $FileId;
  
  /** Return if exists */
  function Exists()
    {
    $query = pdo_query("SELECT count(*) FROM coveragefile2user WHERE userid=".qnum($this->UserId)."
                        AND fileid=".qnum($this->FileId));  
    $query_array = pdo_fetch_array($query);
    if($query_array['count(*)']>0)
      {
      return true;
      }
    return false;
    }      
  
  /** Insert the new user */  
  function Insert()
    {
    if(!isset($this->UserId) || $this->UserId<1)
      {
      return false;
      }
    if(!isset($this->FileId) || $this->FileId<1)
      {
      return false;
      }
    
    // Check if is already in the database
    if(!$this->Exists())
      {
      // Find the new position
      $query = pdo_query("SELECT count(*) FROM coveragefile2user WHERE fileid=".qnum($this->FileId));
      $query_array = pdo_fetch_array($query);
      $position = $query_array['count(*)']+1;
      
      $query = "INSERT INTO coveragefile2user (userid,fileid,position)
                VALUES (".qnum($this->UserId).",".qnum($this->FileId).",".qnum($position).")";                     
      if(!pdo_query($query))
        {
        add_last_sql_error("CoverageFile2User:Insert");
        echo $query;
        return false;
        }
      return true;   
      }
    return false;
    } // function Insert


  /** Remove the new user */  
  function Remove()
    {
    if(!isset($this->UserId) || $this->UserId<1)
      {
      return false;
      }
    if(!isset($this->FileId) || $this->FileId<1)
      {
      return false;
      }
    
    $query = "DELETE FROM coveragefile2user WHERE userid=".qnum($this->UserId)."
                AND fileid=".qnum($this->FileId);                     
    if(!pdo_query($query))
      {
      add_last_sql_error("CoverageFile2User:Remove");
      echo $query;
      return false;
      }
    
    $this->FixPosition();  
      
    return true;   
    } // end function Remove

  /** Fix the position given a file */
  private function FixPosition()
    {
    if(!isset($this->FileId) || $this->FileId<1)
      {
      return false;
      }
    
    $query = pdo_query("SELECT userid FROM coveragefile2user WHERE fileid=".qnum($this->FileId)." ORDER BY position ASC");                   
    if(!$query)
      {
      add_last_sql_error("CoverageFile2User:FixPosition");
      echo $query;
      return false;
      }
      
    $position = 1;
    while($query_array = pdo_fetch_array($query))
      {
      pdo_query("UPDATE coveragefile2user SET position=".qnum($position)." WHERE fileid=".qnum($this->FileId)." 
                 AND userid=".qnum($query_array['userid']));   
      $position ++;
      }
    return true;
    } // end FixPosition

  /** Get authors of a file */  
  function GetAuthors()
    {
    if(!isset($this->FileId) || $this->FileId<1)
      {
      echo "CoverageFile2User:GetAuthors: FileId not set";
      return false;
      }
    $query = pdo_query("SELECT userid FROM coveragefile2user WHERE fileid=".qnum($this->FileId)." ORDER BY position ASC");                   
    if(!$query)
      {
      add_last_sql_error("CoverageFile2User:GetAuthors");
      echo $query;
      return false;
      }
    $authorids = array();
    while($query_array = pdo_fetch_array($query))
      {
      $authorids[] = $query_array['userid'];
      }
    return $authorids;
    } // end function GetAuthors

  /** Get files given an author */  
  function GetFiles()
    {
    if(!isset($this->UserId) || $this->UserId<1)
      {
      echo "CoverageFile2User:GetFiles: UserId not set";
      return false;
      }
    $query = pdo_query("SELECT fileid FROM coveragefile2user WHERE userid=".qnum($this->UserId));                   
    if(!$query)
      {
      add_last_sql_error("CoverageFile2User:GetFiles");
      echo $query;
      return false;
      }
      
    $fileids = array();
    while($query_array = pdo_fetch_array($query))
      {
      $fileids[] = $query_array['fileid'];
      }
    return $fileids;
    } // end function GetFiles

  /** Get the list of authors for the project */
  function GetUsersFromProject($projectid)
    {
    if(!isset($projectid) || $projectid<1)
      {
      echo "CoverageFile2User:GetUsersFromProject: projectid not valid";
      return false;
      }
    
    $query = pdo_query("SELECT DISTINCT userid FROM coveragefile2user,coverage,build WHERE 
                        coverage.buildid=build.id AND coveragefile2user.fileid=coverage.fileid
                        AND build.projectid=".qnum($projectid));                   
    if(!$query)
      {
      add_last_sql_error("CoverageFile2User:GetUsersFromProject");
      echo $query;
      return false;
      }
    $userids = array();
    while($query_array = pdo_fetch_array($query))
      {
      $userids[] = $query_array['userid'];
      }
    return $userids;
    } // end GetUsersFromProject

  /** Assign the last author */
  function AssignLastAuthor($projectid,$beginUTCTime,$currentUTCTime)
    {
    include_once('models/dailyupdate.php');
    
    // Find the last build
    $CoverageSummary = new CoverageSummary();
    $buildids = $CoverageSummary->GetBuilds($projectid,$beginUTCTime,$currentUTCTime);
    // For now take the first one
    if(count($buildids)==0)
      {
      return false;
      }
      
    $buildid = $buildids[0];
    
    // Find the files associated with the build
    $Coverage = new Coverage();
    $Coverage->BuildId = $buildid;
    $fileIds = $Coverage->GetFiles();
    foreach($fileIds as $fileid)
      {
      $CoverageFile = new CoverageFile();
      $CoverageFile->Id = $fileid;
      $fullpath = $CoverageFile->GetPath();
      
      $DailyUpdate = new DailyUpdate();
      $DailyUpdate->ProjectId = $projectid;
      $userids = $DailyUpdate->GetAuthors($fullpath,true); // only last
      
      foreach($userids as $userid)
        {
        $this->FileId = $fileid;
        $this->UserId = $userid;
        $this->Insert();
        }
      }
      
    return true;
    } // end AssignLastAuthor

  /** Assign all author author */
  function AssignAllAuthors($projectid,$beginUTCTime,$currentUTCTime)
    {
    include_once('models/dailyupdate.php');
    
    // Find the last build
    $CoverageSummary = new CoverageSummary();
    $buildids = $CoverageSummary->GetBuilds($projectid,$beginUTCTime,$currentUTCTime);
    // For now take the first one
    if(count($buildids)==0)
      {
      return false;
      }
      
    $buildid = $buildids[0];
    
    // Find the files associated with the build
    $Coverage = new Coverage();
    $Coverage->BuildId = $buildid;
    $fileIds = $Coverage->GetFiles();
    foreach($fileIds as $fileid)
      {
      $CoverageFile = new CoverageFile();
      $CoverageFile->Id = $fileid;
      $fullpath = $CoverageFile->GetPath();
      
      $DailyUpdate = new DailyUpdate();
      $DailyUpdate->ProjectId = $projectid;
      $userids = $DailyUpdate->GetAuthors($fullpath);
      
      foreach($userids as $userid)
        {
        $this->FileId = $fileid;
        $this->UserId = $userid;
        $this->Insert();
        }
      }
      
    return true;
    } // end AssignAllAuthors 
  
  // Function get the priority to a file
  function GetPriority()
    {
    if(!isset($this->FileId))
      {
      echo "CoverageFile2User:GetPriority: FileId not set";
      return false;
      }
    $query = pdo_query("SELECT priority FROM coveragefilepriority WHERE fileid=".qnum($this->FileId));                   
    if(!$query)
      {
      add_last_sql_error("CoverageFile2User:GetPriority");
      return false;
      }
    
    if(pdo_num_rows($query) == 0)
      {
      return 0;
      }   
    $query_array = pdo_fetch_array($query);
    return $query_array[0];
    }
      
  // Function set the priority to a file
  function SetPriority($priority)
    {
    if(!isset($this->FileId))
      {
      echo "CoverageFile2User:SetPriority: FileId not set";
      return false;
      }
    $query = pdo_query("SELECT count(*) FROM coveragefilepriority WHERE fileid=".qnum($this->FileId));                   
    if(!$query)
      {
      add_last_sql_error("CoverageFile2User:SetPriority");
      return false;
      }
    
    $sql = "";
    $query_array = pdo_fetch_array($query);
    if($query_array[0] == 0)
      {
      $sql = "INSERT INTO coveragefilepriority (fileid,priority) VALUES (".qnum($this->FileId).",".qnum($priority).")";
      }
    else
      {
      $sql = "UPDATE coveragefilepriority set priority=".qnum($priority)." WHERE fileid=".qnum($this->FileId);
      }   
      
    $query = pdo_query($sql);                   
    if(!$query)
      {
      add_last_sql_error("CoverageFile2User:SetPriority");
      return false;
      }   
    return true;  
    }
    
    
    
}
?>
