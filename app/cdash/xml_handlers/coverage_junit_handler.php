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

use CDash\Model\Build;
use CDash\Model\BuildInformation;
use CDash\Model\Coverage;
use CDash\Model\CoverageFile;
use CDash\Model\CoverageSummary;
use CDash\Model\Label;
use App\Models\Site;
use App\Models\SiteInformation;

class CoverageJUnitHandler extends AbstractHandler
{
    private $StartTimeStamp;
    private $EndTimeStamp;

    private $Coverage;
    private $CoverageFile;
    private CoverageSummary $CoverageSummary;
    private $Label;

    /** Constructor */
    public function __construct($projectID)
    {
        parent::__construct($projectID);
        $this->Build = new Build();
        $this->Site = new Site();
        $this->CoverageSummary = new CoverageSummary();
    }

    /** startElement */
    public function startElement($parser, $name, $attributes)
    {
        parent::startElement($parser, $name, $attributes);
        $parent = $this->getParent();
        if ($name == 'SITE') {
            $site_name = !empty($attributes['NAME']) ? $attributes['NAME'] : '(empty)';
            $this->Site = Site::firstOrCreate(['name' => $site_name], ['name' => $site_name]);

            $siteInformation = new SiteInformation();
            $buildInformation = new BuildInformation();

            // Fill in the attribute
            foreach ($attributes as $key => $value) {
                $siteInformation->SetValue($key, $value);
                $buildInformation->SetValue($key, $value);
            }

            $this->Site->mostRecentInformation()->save($siteInformation);

            $this->Build->SiteId = $this->Site->id;
            $this->Build->Name = $attributes['BUILDNAME'];
            if (empty($this->Build->Name)) {
                $this->Build->Name = '(empty)';
            }
            $this->Build->SetStamp($attributes['BUILDSTAMP']);
            $this->Build->Generator = $attributes['GENERATOR'];
            $this->Build->Information = $buildInformation;
        } elseif ($name == 'SOURCEFILE') {
            $this->CoverageFile = new CoverageFile();
            $this->Coverage = new Coverage();
            $this->CoverageFile->FullPath = trim($attributes['NAME']);
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
            $this->Build->GetIdFromName($this->SubProjectName);
            $this->Build->RemoveIfDone();

            // If the build doesn't exist we add it
            if ($this->Build->Id == 0) {
                $this->Build->StartTime = $start_time;
                $this->Build->EndTime = $end_time;
                $this->Build->SubmitTime = gmdate(FMT_DATETIME);
                $this->Build->SetSubProject($this->SubProjectName);
                $this->Build->InsertErrors = false;
                add_build($this->Build);
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
        } elseif ($name == 'SOURCEFILE') {
            $this->CoverageSummary->AddCoverage($this->Coverage);
        } elseif ($name == 'LABEL') {
            if (isset($this->Coverage)) {
                $this->Coverage->AddLabel($this->Label);
            }
        }
    }

    /** Text function */
    public function text($parser, $data)
    {
        $element = $this->getElement();
        if ($element == 'LABEL') {
            $this->Label->SetText($data);
        }
    }
}
