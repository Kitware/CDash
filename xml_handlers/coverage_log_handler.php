<?php
/*=========================================================================
  Program:   CDash - Cross-Platform Dashboard System
  Module:    $Id$
  Language:  PHP
  Date:      $Date$
  Version:   $Revision$

  Copyright (c) Kitware, Inc. All rights reserved.
  See LICENSE or http://www.cdash.org/licensing/ for details.

  This software is distributed WITHOUT ANY WARRANTY; without even
  the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR
  PURPOSE. See the above copyright notices for more information.
=========================================================================*/

require_once 'xml_handlers/abstract_handler.php';
require_once('models/coverage.php');

class CoverageLogHandler extends AbstractHandler
{
    private $StartTimeStamp;
    private $EndTimeStamp;

    private $CurrentCoverageFile;
    private $CurrentCoverageFileLog;
    private $CoverageFiles;

    private $CurrentAggregateCoverageFile;
    private $CurrentAggregateCoverageFileLog;
    private $AggregateCoverageFiles;

    private $CoverageSummary;

    /** Constructor */
    public function __construct($projectID, $scheduleID)
    {
        parent::__construct($projectID, $scheduleID);
        $this->Build = new Build();
        $this->Site = new Site();
        $this->UpdateEndTime = false;
        $this->CoverageFiles = array();
        $this->AggregateBuild = new Build();
        $this->AggregateCoverageFiles = array();
    }

    /** Start element */
    public function startElement($parser, $name, $attributes)
    {
        parent::startElement($parser, $name, $attributes);
        if ($name=='SITE') {
            $this->Site->Name = $attributes['NAME'];
            if (empty($this->Site->Name)) {
                $this->Site->Name = "(empty)";
            }
            $this->Site->Insert();
            $this->Build->SiteId = $this->Site->Id;
            $this->Build->Name = $attributes['BUILDNAME'];
            if (empty($this->Build->Name)) {
                $this->Build->Name = "(empty)";
            }
            $this->Build->SetStamp($attributes['BUILDSTAMP']);
            $this->Build->Generator = $attributes['GENERATOR'];

            if ($this->Build->Type == "Nightly") {

                // Get siteid for 'CDash Server'.
                $server = new Site();
                $server->Name = "CDash Server";
                if (!$server->Exists()) {
                    // Create it if it doesn't exist.
                    $server_ip = $_SERVER['SERVER_ADDR'];
                    $server->Ip = $server_ip;
                    $server->Insert();
                }
                $this->AggregateBuild->SiteId = $server->Id;

                $this->AggregateBuild->Name = 'Aggregate Coverage';
                $date = substr($attributes['BUILDSTAMP'], 0, strpos($attributes['BUILDSTAMP'], '-'));
                $this->AggregateBuild->SetStamp($date."-0000-Nightly");
                $this->AggregateBuild->Generator = $attributes['GENERATOR'];
            }
        } elseif ($name=='FILE') {
            $this->CurrentCoverageFile = new CoverageFile();
            $this->CurrentCoverageFileLog = new CoverageFileLog();
            $this->CurrentCoverageFile->FullPath = $attributes['FULLPATH'];

            $this->CurrentAggregateCoverageFile = new CoverageFile();
            $this->CurrentAggregateCoverageFileLog = new CoverageFileLog();
            $this->CurrentAggregateCoverageFile->FullPath = $attributes['FULLPATH'];
        } elseif ($name=='LINE') {
            if ($attributes['COUNT']>=0) {
                $this->CurrentCoverageFileLog->AddLine($attributes['NUMBER'], $attributes['COUNT']);
                $this->CurrentAggregateCoverageFileLog->AddLine($attributes['NUMBER'], $attributes['COUNT']);
            }
        }
    } // end startElement()

    /** End Element */
    public function endElement($parser, $name)
    {
        $parent = $this->getParent(); // should be before endElement
        parent::endElement($parser, $name);

        if ($name === 'SITE') {
            $start_time = gmdate(FMT_DATETIME, $this->StartTimeStamp);
            $end_time = gmdate(FMT_DATETIME, $this->EndTimeStamp);

            if ($this->Build->Type == "Nightly") {
                $this->AggregateBuild->ProjectId = $this->projectid;
                $this->AggregateBuild->StartTime = $start_time;
                $this->AggregateBuild->EndTime = $end_time;
                $this->AggregateBuild->SubmitTime = gmdate(FMT_DATETIME);
                $this->AggregateBuild->SetSubProject($this->SubProjectName);
                $this->AggregateBuild->GetIdFromName($this->SubProjectName);
                $this->AggregateBuild->RemoveIfDone();

              // If the build doesn't exist we add it
              if ($this->AggregateBuild->Id == 0) {
                  $this->AggregateBuild->InsertErrors = false;
                  add_build($this->AggregateBuild);
              }

                $this->CoverageSummary = new CoverageSummary();
                $this->CoverageSummary->BuildId = $this->AggregateBuild->Id;

              // Record the coverage data that we parsed from this file.
              foreach ($this->AggregateCoverageFiles as $coverageInfo) {
                  $coverageFile = $coverageInfo[0];
                  $coverageFileLog = $coverageInfo[1];
                  $coverageFile->Update($this->AggregateBuild->Id);
                  $coverageFileLog->BuildId = $this->AggregateBuild->Id;
                  $coverageFileLog->FileId = $coverageFile->Id;
                  $coverageFileLog->Insert(true);

                  // Query the filelog to get how many lines & branches were covered.
                  // We do this after inserting the filelog because we want to accurately
                  // reflect the union of the current and previously existing results
                  // (if any).
                  $stats = $coverageFileLog->GetStats();
                  $coverage = new Coverage();
                  $coverage->CoverageFile = $coverageFile;
                  $coverage->LocUntested = $stats['locuntested'];
                  $coverage->LocTested = $stats['loctested'];
                  if ($coverage->LocTested > 0) {
                      $coverage->Covered = 1;
                  } else {
                      $coverage->Covered = 0;
                  }
                  $coverage->BranchesUntested = $stats['branchesuntested'];
                  $coverage->BranchesTested = $stats['branchestested'];

                  // Add this Coverage to our summary.
                  $this->CoverageSummary->AddCoverage($coverage);
              }

              // Insert coverage summary
              $this->CoverageSummary->Insert(true);
                $this->CoverageSummary->ComputeDifference();
            }

            $this->Build->ProjectId = $this->projectid;
            $this->Build->StartTime = $start_time;
            $this->Build->EndTime = $end_time;
            $this->Build->SubmitTime = gmdate(FMT_DATETIME);
            $this->Build->SetSubProject($this->SubProjectName);
            $this->Build->GetIdFromName($this->SubProjectName);
            $this->Build->RemoveIfDone();
            if ($this->Build->Id == 0) {
                // If the build doesn't exist we add it.
                $this->Build->InsertErrors = false;
                add_build($this->Build, $this->scheduleid);
            } else {
                // Otherwise make sure that it's up-to-date.
                $this->Build->UpdateBuild($this->Build->Id, -1, -1);
            }
            // Record the coverage data that we parsed from this file.
            foreach ($this->CoverageFiles as $coverageInfo) {
                $coverageFile = $coverageInfo[0];
                $coverageFileLog = $coverageInfo[1];
                $coverageFile->Update($this->Build->Id);
                $coverageFileLog->BuildId = $this->Build->Id;
                $coverageFileLog->FileId = $coverageFile->Id;
                $coverageFileLog->Insert();
            }
        } elseif ($name == 'LINE') {
            // Cannot be <br/> for backward compatibility.
            $this->CurrentCoverageFile->File .= '<br>';
            $this->CurrentAggregateCoverageFile->File .= '<br>';
        } elseif ($name == 'FILE') {
            // Store these objects to be inserted after we're guaranteed
            // to have a valid buildid.
            $this->CoverageFiles[] = array($this->CurrentCoverageFile,
                    $this->CurrentCoverageFileLog);

            $this->AggregateCoverageFiles[] = array($this->CurrentAggregateCoverageFile,
                    $this->CurrentAggregateCoverageFileLog);
        }
    } // end endElement()

    /** Text */
    public function text($parser, $data)
    {
        $parent = $this->getParent();
        $element = $this->getElement();
        switch ($element) {
            case 'LINE':
                $this->CurrentCoverageFile->File .= $data;
                $this->CurrentAggregateCoverageFile->File .= $data;
                break;
            case 'STARTDATETIME':
                $this->StartTimeStamp =
                    str_to_time($data, $this->Build->GetStamp());
                break;
            case 'ENDDATETIME':
                $this->EndTimeStamp =
                    str_to_time($data, $this->Build->GetStamp());
                break;
        }
    } // end text()
} // end class;
