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
class CoverageSummary
{
  var $LocTested;
  var $LocUntested;
  var $BuildId;
  private $Coverages;
  
  function AddCoverage($coverage)
    {
    $this->Coverages[] = $coverage;
    }
  
  function GetCoverages()
    {
    return $this->Coverages;
    }
  
  /** Remove all coverage information */
  function RemoveAll()
    {
    if(!$this->BuildId)
      {
      echo "CoverageSummary::RemoveAll(): BuildId not set";
      return false;    
      }
    
    $query = "DELETE FROM coveragesummarydiff WHERE buildid=".qnum($this->BuildId);
    if(!pdo_query($query))
      {
      add_last_sql_error("CoverageSummary RemoveAll");
      return false;
      }
 
    $query = "DELETE FROM testdiff WHERE buildid=".qnum($this->BuildId);
    if(!pdo_query($query))
      {
      add_last_sql_error("CoverageSummary RemoveAll");
      return false;
      }  
      
    // coverage file are kept unless they are shared
    $coverage = pdo_query("SELECT fileid FROM coverage WHERE buildid=".qnum($this->BuildId));
    while($coverage_array = pdo_fetch_array($coverage))
      {
      $fileid = $coverage_array["fileid"];
      // Make sur the file is not shared
      $numfiles = pdo_query("SELECT count(*) FROM coveragefile WHERE id='$fileid'");
      if($numfiles[0]==1)
        {
        pdo_query("DELETE FROM coveragefile WHERE id='$fileid'"); 
        }
      }
  
    $query = "DELETE FROM coverage WHERE buildid=".qnum($this->BuildId);
    if(!pdo_query($query))
      {
      add_last_sql_error("CoverageSummary RemoveAll");
      return false;
      }
      
    $query = "DELETE FROM coveragefilelog WHERE buildid=".qnum($this->BuildId);
    if(!pdo_query($query))
      {
      add_last_sql_error("CoverageSummary RemoveAll");
      return false;
      }
      
    $query = "DELETE FROM coveragesummary WHERE buildid=".qnum($this->BuildId);
    if(!pdo_query($query))
      {
      add_last_sql_error("CoverageSummary RemoveAll");
      return false;
      }
    return true;    
    }   // RemoveAll()        
    
  /** Insert a new summary */
  function Insert()
    {  
    if(!$this->BuildId)
      {
      echo "CoverageSummary::Insert(): BuildId not set";
      return false;    
      }
    
    if(!is_numeric($this->BuildId))
      {
      return;
      }
      
    $query = "INSERT INTO coveragesummary (buildid,loctested,locuntested) 
              VALUES (".qnum($this->BuildId).",".qnum($this->LocTested).",".qnum($this->LocUntested).")";                     
    if(!pdo_query($query))
      {
      add_last_sql_error("CoverageSummary Insert");
      return false;
      }
    
    // Add the coverages  
    // Construct the SQL query
    if(count($this->Coverages)>0)
      {
      $sql = "INSERT INTO coverage (buildid,fileid,covered,loctested,locuntested,branchstested,branchsuntested,
                                    functionstested,functionsuntested) VALUES ";
      
      $i=0;
      foreach($this->Coverages as &$coverage)
        {    
        $fullpath = $coverage->CoverageFile->FullPath;
    
        // Create an empty file if doesn't exists
        $coveragefile = pdo_query("SELECT id FROM coveragefile WHERE fullpath='$fullpath' AND file IS NULL");
        if(pdo_num_rows($coveragefile)==0)
          {
          // Do not compute the crc32, that means it's a temporary file
          // Only when the crc32 is computed it means that the file is valid
          pdo_query ("INSERT INTO coveragefile (fullpath) VALUES ('$fullpath')");
          $fileid = pdo_insert_id("coveragefile");
          }
        else
          {
          $coveragefile_array = pdo_fetch_array($coveragefile);
          $fileid = $coveragefile_array["id"];
          }        
        $coverage->CoverageFile->Id = $fileid;
          
        $covered = $coverage->Covered;
        $loctested = $coverage->LocTested;
        $locuntested = $coverage->LocUntested;
        $branchstested = $coverage->BranchesTested;
        $branchsuntested = $coverage->BranchesUntested;
        $functionstested = $coverage->FunctionsTested;
        $functionsuntested = $coverage->FunctionsUntested;

        if($i>0)
          {
          $sql .= ", ";
          }
        else
          {
          $i=1;
          }
           
        $sql .= "(".qnum($this->BuildId).",".qnum($fileid).",".qnum($covered).",".qnum($loctested).",".qnum($locuntested).",
                   ".qnum($branchstested).",".qnum($branchsuntested).
                  ",".qnum($functionstested).",".qnum($functionsuntested).")";    
        }
      // Insert into coverage
      if(!pdo_query($sql))
        {
        add_last_sql_error("CoverageSummary Insert");
        return false;
        }
      }

    return true;
    }   // Insert()  
    
  
  /** Compute the coverage summary diff */  
  function ComputeDifference()
    {
    $build = new Build();
    $build->FillFromId($this->BuildId);
    $previousBuildId = $build->GetPreviousBuildId();
    if($previousBuildId === FALSE)
      {
      return;
      }

    // Look at the number of errors and warnings differences
    $coverage = pdo_query("SELECT loctested,locuntested FROM coveragesummary WHERE buildid=".qnum($this->BuildId));
    if(!$coverage)
      {
      add_last_sql_error("CoverageSummary:ComputeDifference");
      return false;
      }
    $coverage_array  = pdo_fetch_array($coverage);
    $loctested = $coverage_array['loctested']; 
    $locuntested = $coverage_array['locuntested']; 
      
    $previouscoverage = pdo_query("SELECT loctested,locuntested FROM coveragesummary WHERE buildid=".qnum($previousBuildId));
    if(pdo_num_rows($previouscoverage)>0)
      {
      $previouscoverage_array = pdo_fetch_array($previouscoverage);
      $previousloctested = $previouscoverage_array['loctested']; 
      $previouslocuntested = $previouscoverage_array['locuntested']; 
  
      // Don't log if no diff
      $loctesteddiff = $loctested-$previousloctested;
      $locuntesteddiff = $locuntested-$previouslocuntested;
      
      if($loctesteddiff != 0 && $locuntesteddiff != 0)
        {
        $summaryDiff = new CoverageSummaryDiff();
        $summaryDiff->BuildId = $this->BuildId;
        $summaryDiff->LocTested = $loctesteddiff;
        $summaryDiff->LocTested = $locuntesteddiff;
        $summaryDiff->Insert();
        }
      }
    } // end ComputeDifference() 
    
  /** Return the list of buildid which are contributing to the dashboard */
  function GetBuilds($projectid,$timestampbegin,$timestampend)
    {
    $buildids = array();
    $coverage = pdo_query("SELECT buildid FROM coveragesummary,build WHERE coveragesummary.buildid=build.id 
                           AND build.projectid=".qnum($projectid)." AND build.starttime>'".$timestampbegin."'
                           AND endtime<'".$timestampend."'");
    if(!$coverage)
      {
      add_last_sql_error("CoverageSummary:GetBuilds");
      return false;
      }
    while($coverage_array  = pdo_fetch_array($coverage))
      {
      $buildids[] = $coverage_array['buildid']; 
      }
    return $buildids;
    }
    
         
} // end CoverageSummary class

class CoverageSummaryDiff
{
  var $LocTested;
  var $LocUntested;
  var $BuildId;
  
  function Insert()
    {
    pdo_query("INSERT INTO coveragesummarydiff (buildid,loctested,locuntested) 
              VALUES(".qnum($this->BuildId).",".qnum($this->LocTested).",".qnum($this->LocUntested).")");
    add_last_sql_error("CoverageSummary:ComputeDifference");
    }
}
    
class CoverageFileLog
{  
  var $BuildId;
  var $FileId;
  var $Lines;
  
  function AddLine($number,$code)
    {
    $this->Lines[$number] = $code;
    }
  
  /** Update the content of the file */
  function Insert()
    {
    if(!$this->BuildId || !is_numeric($this->BuildId))
      {
      echo "CoverageFileLog::Insert(): BuildId not set";
      return false;    
      }
    
     $sql = "INSERT INTO coveragefilelog (buildid,fileid,line,code) VALUES ";
  
    $i=0;
    foreach($this->Lines as $lineNumber=>$code)
      {
      if($i>0)
        {
        $sql .= ",";
        }  
        
      $sql.= "(".qnum($this->BuildId).",".qnum($this->FileId).",".qnum($lineNumber).",'$code')";
       
      if($i==0)
        {
        $i++;
        }
      }
      
    pdo_query($sql);
    add_last_sql_error("CoverageFileLog::Insert()");
    return true;
    }
}

/** This class shouldn't be used externally */
class CoverageFile
{  
  var $Id;
  var $File;
  var $FullPath;
  var $Crc32;
  
  private $LastPercentCoverage; // used when GetMetric
  
  /** Update the content of the file */
  function Update($buildid)
    {
    if(!is_numeric($buildid) || $buildid == 0)
      {
      return;
      }
    
    // Compute the crc32 of the file
    $this->Crc32 = crc32($this->FullPath.$this->File);
    
    $this->FullPath = pdo_real_escape_string($this->FullPath);
    $this->File = pdo_real_escape_string($this->File);
      
    $coveragefile = pdo_query("SELECT id FROM coveragefile WHERE crc32=".qnum($this->Crc32));
    add_last_sql_error("CoverageFile:Update()");
      
    if(pdo_num_rows($coveragefile)>0) // we have the same crc32
      {
      $coveragefile_array = pdo_fetch_array($coveragefile);
      $this->Id = $coveragefile_array["id"];
  
      // Update the current coverage.fileid
      $coverage = pdo_query("SELECT c.fileid FROM coverage AS c,coveragefile AS cf 
                            WHERE c.fileid=cf.id AND c.buildid=".qnum($buildid)."
                              AND cf.fullpath='$this->FullPath'");
      $coverage_array = pdo_fetch_array($coverage);
      $prevfileid = $coverage_array["fileid"];
  
      pdo_query("UPDATE coverage SET fileid=".qnum($this->Id)." WHERE buildid=".qnum($buildid)." AND fileid=".qnum($prevfileid));
      add_last_sql_error("CoverageFile:Update()");
  
      // Remove the file if the crc32 is NULL
      pdo_query("DELETE FROM coveragefile WHERE id=".qnum($prevfileid)." AND file IS NULL and crc32 IS NULL");
      add_last_sql_error("CoverageFile:Update()");
      }
    else // The file doesn't exist in the database
      {
      // We find the current fileid based on the name and the file should be null
      $coveragefile = pdo_query("SELECT cf.id,cf.file FROM coverage AS c,coveragefile AS cf 
                                   WHERE c.fileid=cf.id AND c.buildid=".qnum($buildid)."
                                   AND cf.fullpath='$this->FullPath' ORDER BY cf.id ASC");
      $coveragefile_array = pdo_fetch_array($coveragefile);
      $this->Id = $coveragefile_array["id"];
      pdo_query("UPDATE coveragefile SET file='$this->File',crc32='$this->Crc32' WHERE id=".qnum($this->Id)); 
      add_last_sql_error("CoverageFile:Update()");
      }
    return true;
    }
    
  /** Get the path */
  function GetPath()
    {
    if(!$this->Id)
      {
      echo "CoverageFile GetPath(): Id not set";
      return false;
      }
    
    $coverage = pdo_query("SELECT fullpath FROM coveragefile WHERE id=".qnum($this->Id));
    if(!$coverage)
      {
      add_last_sql_error("Coverage GetPath");
      return false;
      }
      
    $coverage_array = pdo_fetch_array($coverage);
    return $coverage_array['fullpath'];
      
    }  // GetPath  
  
  /** Return the metric */
  function GetMetric()
    {
    if(!$this->Id)
      {
      echo "CoverageFile GetMetric(): Id not set";
      return false;
      }

    $coveragefile = pdo_query("SELECT loctested,locuntested,branchstested,branchsuntested,
                                     functionstested,functionsuntested FROM coverage WHERE fileid=".qnum($this->Id));
    if(!$coveragefile)
      {
      add_last_sql_error("CoverageFile:GetMetric()");
      return false;
      }

    if(pdo_num_rows($coveragefile)==0)
      {
      return false;
      }

    $coveragemetric = 1;  
    $coveragefile_array = pdo_fetch_array($coveragefile);
    $loctested = $coveragefile_array["loctested"];
    $locuntested = $coveragefile_array["locuntested"];
    $branchstested = $coveragefile_array["branchstested"];
    $branchsuntested = $coveragefile_array["branchsuntested"];
    $functionstested = $coveragefile_array["functionstested"];
    $functionsuntested = $coveragefile_array["functionsuntested"];
    
    // Compute the coverage metric for bullseye
    if($branchstested>0 || $branchsuntested>0 || $functionstested>0 || $functionsuntested>0)
      { 
      // Metric coverage
      $metric = 0;
      if($functionstested+$functionsuntested>0)
        {
        $metric += $functionstested/($functionstested+$functionsuntested);
        }
      if($branchsuntested+$branchsuntested>0)
        {
        $metric += $branchsuntested/($branchstested+$branchsuntested);
        $metric /= 2.0;
        }
      $coveragemetric = $metric;
      $this->LastPercentCoverage = $metric*100;
      }
    else // coverage metric for gcov
      {
      $coveragemetric = ($loctested+10)/($loctested+$locuntested+10);
      $this->LastPercentCoverage = ($loctested/($loctested+$locuntested))*100;    
      }
    
    return $coveragemetric;
    } // end function GetMetric 
    
  // Get the percent coverage
  function GetLastPercentCoverage()
    {
    return $this->LastPercentCoverage;
    }  
 
  // Get the fileid from the name
  function GetIdFromName($file,$buildid)
    {
    $coveragefile = pdo_query("SELECT id FROM coveragefile,coverage WHERE fullpath LIKE '%".$file."%' 
                               AND coverage.buildid=".qnum($buildid)." AND coverage.fileid=coveragefile.id");
    if(!$coveragefile)
      {
      add_last_sql_error("CoverageFile:GetIdFromName()");
      return false;
      }
    if(pdo_num_rows($coveragefile)==0)
      {
      return false;
      }
    $coveragefile_array = pdo_fetch_array($coveragefile);
    return $coveragefile_array['id'];
    }  
    
}

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
    
}


/** Coverage class. Used by CoverageSummary */
class Coverage
{  
  var $BuildId;
  var $Covered;
  var $LocTested;
  var $LocUntested;
  var $BranchesTested;
  var $BranchesUntested;
  var $FunctionsTested;
  var $FunctionsUntested;
  var $CoverageFile;
  
  // Purposely no Insert function. Everything is done from the coverage summary
  
  /** Return the name of a build */
  function GetFiles()
    {
    if(!$this->BuildId)
      {
      echo "Coverage GetFiles(): BuildId not set";
      return false;
      }
    
    $fileids = array();
    
    $coverage = pdo_query("SELECT fileid FROM coverage WHERE buildid=".qnum($this->BuildId));
    if(!$coverage)
      {
      add_last_sql_error("Coverage GetFiles");
      return false;
      }
      
    while($coverage_array = pdo_fetch_array($coverage))
      {
      $fileids[] = $coverage_array['fileid'];
      }
    
    return $fileids;
    } // end function GetFiles()
      
    
} // end class Coverage
?>
