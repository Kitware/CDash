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
  var $Labels;

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
