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
  var $LocTested = 0;
  var $LocUntested = 0;
  var $BuildId;
  private $Coverages;

  function __construct()
    {
    $this->Coverages = array();
    }

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

    // coverage file are kept unless they are shared
    $coverage = pdo_query("SELECT fileid FROM coverage WHERE buildid=".qnum($this->BuildId));
    while($coverage_array = pdo_fetch_array($coverage))
      {
      $fileid = $coverage_array["fileid"];
      // Make sure the file is not shared
      $numfiles = pdo_query("SELECT count(*) FROM coveragefile WHERE id='$fileid'");
      $numfiles_array = pdo_fetch_row($numfiles);
      if($numfiles_array[0]==1)
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

    if(!is_numeric($this->BuildId) || !is_numeric($this->LocTested) || !is_numeric($this->LocUntested))
      {
      return;
      }

    if(empty($this->LocTested))
      {
      $this->LocTested = 0;
      }

   if(empty($this->LocUntested))
      {
      $this->LocUntested = 0;
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

        // GcovTarHandler creates its own coveragefiles, no need to do
        // it again here.
        $fileid = -1;
        if (!empty($coverage->CoverageFile->Crc32))
          {
          $fileid = $coverage->CoverageFile->Id;
          }

        // Create an empty file if doesn't exists
        if ($fileid === -1)
          {
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
          }

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

        if(empty($covered))
          {
          $covered = 0;
          }
        if(empty($loctested))
          {
          $loctested = 0;
          }
        if(empty($locuntested))
          {
          $locuntested = 0;
          }
        if(empty($branchstested))
          {
          $branchstested = 0;
          }
        if(empty($branchsuntested))
          {
          $branchsuntested = 0;
          }
        if(empty($functionstested))
          {
          $functionstested = 0;
          }
        if(empty($functionsuntested))
          {
          $functionsuntested = 0;
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

      // Add labels
      foreach($this->Coverages as &$coverage)
        {
        $coverage->InsertLabelAssociations($this->BuildId);
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

?>
