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

class CoverageHandler extends AbstractHandler
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
      } elseif ($name=='FILE') {
          $this->CoverageFile = new CoverageFile();
          $this->Coverage = new Coverage();
          $this->CoverageFile->FullPath = $attributes['FULLPATH'];
          if ($attributes['COVERED'] == 1 || $attributes['COVERED']=="true") {
              $this->Coverage->Covered = 1;
          } else {
              $this->Coverage->Covered = 0;
          }
          $this->Coverage->CoverageFile = $this->CoverageFile;
      } elseif ($name == 'LABEL') {
          $this->Label = new Label();
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
          $this->Build->StartTime = $start_time;
          $this->Build->EndTime = $end_time;
          $this->Build->SubmitTime = gmdate(FMT_DATETIME);
          $this->Build->SetSubProject($this->SubProjectName);
          $this->Build->GetIdFromName($this->SubProjectName);
          $this->Build->RemoveIfDone();

          // If the build doesn't exist we add it
          if ($this->Build->Id == 0) {
              $this->Build->InsertErrors = false;
              add_build($this->Build, $this->scheduleid);
          } else {
            // Otherwise make sure that it's up-to-date.
            $this->Build->UpdateBuild($this->Build->Id, -1, -1);
          }

          $GLOBALS['PHP_ERROR_BUILD_ID'] = $this->Build->Id;
          $this->CoverageSummary->BuildId = $this->Build->Id;
          if ($this->CoverageSummary->Exists()) {
              // Remove any previous coverage information.
              $this->CoverageSummary->RemoveAll();
          }

          // Insert coverage summary
          $this->CoverageSummary->Insert();
          $this->CoverageSummary->ComputeDifference();
      } elseif ($name=='FILE') {
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
      $parent = $this->getParent();
      $element = $this->getElement();

      if ($parent == 'COVERAGE') {
          switch ($element) {
        case 'STARTBUILDTIME':
          $this->StartTimeStamp .= $data;
          break;
        case 'STARTDATETIME':
          $this->StartTimeStamp = str_to_time($data, $this->Build->GetStamp());
          break;
        case 'ELAPSEDMINUTES':
          $this->EndTimeStamp = $this->StartTimeStamp+$data*60;
          break;
        case 'LOCTESTED':
          $this->CoverageSummary->LocTested .= $data;
          break;
        case 'LOCUNTESTED':
          $this->CoverageSummary->LocUntested .= $data;
          break;
        }
      } elseif ($parent == 'FILE') {
          switch ($element) {
        case 'LOCTESTED':
          $this->Coverage->LocTested .= $data;
          break;
        case 'LOCUNTESTED':
          $this->Coverage->LocUntested .= $data;
          break;
        case 'BRANCHESTESTED':
          $this->Coverage->BranchesTested .= $data;
          break;
        case 'BRANCHESUNTESTED':
          $this->Coverage->BranchesUntested .= $data;
          break;
        case 'FUNCTIONSTESTED':
          $this->Coverage->FunctionsTested .= $data;
          break;
        case 'FUNCTIONSUNTESTED':
          $this->Coverage->FunctionsUntested .= $data;
          break;
        }
      } elseif ($element == 'LABEL') {
          $this->Label->SetText($data);
      }
  } // end text function
}
