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
include_once('models/coveragesummary.php');
include_once('models/coveragesummarydiff.php');
include_once('models/coveragefile.php');
include_once('models/coveragefile2user.php');
include_once('models/coveragefilelog.php');
include_once('models/label.php');

/** Coverage class. Used by CoverageSummary */
class coverage
{
    public $BuildId;
    public $Covered;
    public $LocTested = 0;
    public $LocUntested = 0;
    public $BranchesTested = 0;
    public $BranchesUntested = 0;
    public $FunctionsTested = 0;
    public $FunctionsUntested = 0;
    public $CoverageFile;
    public $Labels;


  // Purposely no Insert function. Everything is done from the coverage summary
  public function AddLabel($label)
  {
      if (!isset($this->Labels)) {
          $this->Labels = array();
      }

      $label->CoverageFileId = $this->CoverageFile->Id;
      $label->CoverageFileBuildId = $this->BuildId;
      $this->Labels[] = $label;
  }

  /** Put labels for coverage */
  public function InsertLabelAssociations($buildid)
  {
      if ($buildid &&
       isset($this->CoverageFile) &&
       $this->CoverageFile->Id) {
          if (empty($this->Labels)) {
              return;
          }
      
          foreach ($this->Labels as $label) {
              $label->CoverageFileId = $this->CoverageFile->Id;
              $label->CoverageFileBuildId = $buildid;
              $label->Insert();
          }
      } else {
          add_log('No buildid or coveragefile',
              'Coverage::InsertLabelAssociations', LOG_ERR,
              0, $buildid,
              CDASH_OBJECT_COVERAGE, $this->CoverageFile->Id);
      }
  }


  /** Return the name of a build */
  public function GetFiles()
  {
      if (!$this->BuildId) {
          echo "Coverage GetFiles(): BuildId not set";
          return false;
      }

      $fileids = array();

      $coverage = pdo_query("SELECT fileid FROM coverage WHERE buildid=".qnum($this->BuildId));
      if (!$coverage) {
          add_last_sql_error("Coverage GetFiles");
          return false;
      }

      while ($coverage_array = pdo_fetch_array($coverage)) {
          $fileids[] = $coverage_array['fileid'];
      }

      return $fileids;
  } // end function GetFiles()
} // end class Coverage;
