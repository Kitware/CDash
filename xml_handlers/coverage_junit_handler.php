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
require_once 'xml_handlers/abstract_handler.php';
require_once('models/coverage.php');
require_once('models/label.php');

class CoverageJUnitHandler extends AbstractHandler
{
    private $StartTimeStamp;
    private $EndTimeStamp;

    private $Coverage;
    private $CoverageFile;
    private $CoverageSummary;
    private $Label;


  /** Constructor */
  public function __construct($projectID, $scheduleID)
  {
      parent::__construct($projectID, $scheduleID);
      $this->Build = new Build();
      $this->Site = new Site();
      $this->CoverageSummary = new CoverageSummary();
  }


  /** startElement */
  public function startElement($parser, $name, $attributes)
  {
      parent::startElement($parser, $name, $attributes);
      $parent = $this->getParent();
      if ($name=='SITE') {
          $this->Site->Name = $attributes['NAME'];
          if (empty($this->Site->Name)) {
              $this->Site->Name = "(empty)";
          }
          $this->Site->Insert();

          $siteInformation = new SiteInformation();
          $buildInformation =  new BuildInformation();

      // Fill in the attribute
      foreach ($attributes as $key=>$value) {
          $siteInformation->SetValue($key, $value);
          $buildInformation->SetValue($key, $value);
      }

          $this->Site->SetInformation($siteInformation);

          $this->Build->SiteId = $this->Site->Id;
          $this->Build->Name = $attributes['BUILDNAME'];
          if (empty($this->Build->Name)) {
              $this->Build->Name = "(empty)";
          }
          $this->Build->SetStamp($attributes['BUILDSTAMP']);
          $this->Build->Generator = $attributes['GENERATOR'];
          $this->Build->Information = $buildInformation;
      } elseif ($name=='SOURCEFILE') {
          $this->CoverageFile = new CoverageFile();
          $this->Coverage = new Coverage();
          $this->CoverageFile->FullPath = $attributes['NAME'];
          $this->Coverage->Covered = 1;
          $this->Coverage->CoverageFile = $this->CoverageFile;
      } elseif ($name == 'LABEL') {
          $this->Label = new Label();
      } elseif ($parent == 'REPORT' && $name == 'SESSIONINFO') {
          // timestamp are in miliseconds
      $this->StartTimeStamp = substr($attributes['START'], 0, -3);
          $this->EndTimeStamp = substr($attributes['DUMP'], 0, -3);
      } elseif ($parent == 'REPORT' && $name == 'COUNTER') {
          switch ($attributes['TYPE']) {
        case 'LINE':
          $this->CoverageSummary->LocTested = intval($attributes['COVERED']);
          $this->CoverageSummary->LocUntested = intval($attributes['MISSED']);
          break;
        case 'COMPLEXITY':
          $this->CoverageSummary->BranchesTested = intval($attributes['COVERED']);
          $this->CoverageSummary->BranchesUntested = intval($attributes['MISSED']);
          break;
        case 'METHOD':
          $this->CoverageSummary->FunctionsTested = intval($attributes['COVERED']);
          $this->CoverageSummary->FunctionsUntested = intval($attributes['MISSED']);
          break;
        }
      } elseif ($parent == 'SOURCEFILE' && $name == 'COUNTER') {
          switch ($attributes['TYPE']) {
        case 'LINE':
          $this->Coverage->LocTested = intval($attributes['COVERED']);
          $this->Coverage->LocUntested = intval($attributes['MISSED']);
          break;
        case 'COMPLEXITY':
          $this->Coverage->BranchesTested = intval($attributes['COVERED']);
          $this->Coverage->BranchesUntested = intval($attributes['MISSED']);
          break;
        case 'METHOD':
          $this->Coverage->FunctionsTested = intval($attributes['COVERED']);
          $this->Coverage->FunctionsUntested = intval($attributes['MISSED']);
          break;
        }
      }
  } // start element


  /** End element */
  public function endElement($parser, $name)
  {
      parent::endElement($parser, $name);
      if ($name == 'SITE') {
          $start_time = gmdate(FMT_DATETIME, $this->StartTimeStamp);
          $end_time = gmdate(FMT_DATETIME, $this->EndTimeStamp);

          $this->Build->ProjectId = $this->projectid;
          $buildid = $this->Build->GetIdFromName($this->SubProjectName);

      // If the build doesn't exist we add it
      if ($buildid==0) {
          $this->Build->ProjectId = $this->projectid;
          $this->Build->StartTime = $start_time;
          $this->Build->EndTime = $end_time;
          $this->Build->SubmitTime = gmdate(FMT_DATETIME);
          $this->Build->InsertErrors = false;
          add_build($this->Build, $this->scheduleid);
          $buildid = $this->Build->Id;
      }

      // Remove any previous coverage information
      $GLOBALS['PHP_ERROR_BUILD_ID'] = $buildid;
          $this->CoverageSummary->BuildId=$buildid;
          $this->CoverageSummary->RemoveAll();

      // Insert coverage summary
      $this->CoverageSummary->Insert();
          $this->CoverageSummary->ComputeDifference();
      } elseif ($name=='SOURCEFILE') {
          $this->CoverageSummary->AddCoverage($this->Coverage);
      } elseif ($name == 'LABEL') {
          if (isset($this->Coverage)) {
              $this->Coverage->AddLabel($this->Label);
          }
      }
  } // end element


  /** Text function */
  public function text($parser, $data)
  {
      //$parent = $this->getParent();
    //$element = $this->getElement();
    if ($element == 'LABEL') {
        $this->Label->SetText($data);
    }
  } // end text function
}
