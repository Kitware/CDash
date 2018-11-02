<?php
/**
 * =========================================================================
 *   Program:   CDash - Cross-Platform Dashboard System
 *   Module:    $Id$
 *   Language:  PHP
 *   Date:      $Date$
 *   Version:   $Revision$
 *   Copyright (c) Kitware, Inc. All rights reserved.
 *   See LICENSE or http://www.cdash.org/licensing/ for details.
 *   This software is distributed WITHOUT ANY WARRANTY; without even
 *   the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR
 *   PURPOSE. See the above copyright notices for more information.
 * =========================================================================
 */

namespace CDash\Lib\Parser\JUnit;


use CDash\Model\Build;
use CDash\Model\BuildInformation;
use CDash\Model\Coverage;
use CDash\Model\CoverageFile;
use CDash\Model\CoverageSummary;
use CDash\Model\Label;
use CDash\Model\Site;
use CDash\Model\SiteInformation;

class CoverageParser extends AbstractXmlParser
{
    protected $coverage;
    protected $coverageFile;
    protected $coverageSummary;
    protected $label;

    /** Constructor */
    public function __construct($projectId)
    {
        parent::__construct($projectId);
        $this->build = $this->getInstance(Build::class);
        $this->site = $this->getInstance(Site::class);
        $this->coverageSummary = $this->getInstance(CoverageSummary::class);
    }

    /** startElement */
    public function startElement($parser, $name, $attributes)
    {
        parent::startElement($parser, $name, $attributes);
        $parent = $this->getParent();
        if ($name == 'SITE') {
            $this->site->Name = $attributes['NAME'];
            if (empty($this->site->Name)) {
                $this->site->Name = '(empty)';
            }
            $this->site->Insert();

            $siteInformation = $this->getInstance(SiteInformation::class);
            $buildInformation = $this->getInstance(BuildInformation::class);

            // Fill in the attribute
            foreach ($attributes as $key => $value) {
                $siteInformation->SetValue($key, $value);
                $buildInformation->SetValue($key, $value);
            }

            $this->site->SetInformation($siteInformation);

            $this->build->SiteId = $this->site->Id;
            $this->build->Name = $attributes['BUILDNAME'];
            if (empty($this->build->Name)) {
                $this->build->Name = '(empty)';
            }
            $this->build->SetStamp($attributes['BUILDSTAMP']);
            $this->build->Generator = $attributes['GENERATOR'];
            $this->build->Information = $buildInformation;
        } elseif ($name == 'SOURCEFILE') {
            $this->coverageFile = $this->getInstance(CoverageFile::class);
            $this->coverage = $this->getInstance(Coverage::class);
            $this->coverageFile->FullPath = trim($attributes['NAME']);
            $this->coverage->Covered = 1;
            $this->coverage->CoverageFile = $this->coverageFile;
        } elseif ($name == 'LABEL') {
            $this->label = $this->getInstance(Label::class);
        } elseif ($parent == 'REPORT' && $name == 'SESSIONINFO') {
            // timestamp are in miliseconds
            $this->startTimeStamp = substr($attributes['START'], 0, -3);
            $this->endTimeStamp = substr($attributes['DUMP'], 0, -3);
        } elseif ($parent == 'REPORT' && $name == 'COUNTER') {
            switch ($attributes['TYPE']) {
                case 'COMPLEXITY':
                    $this->coverageSummary->BranchesTested = intval($attributes['COVERED']);
                    $this->coverageSummary->BranchesUntested = intval($attributes['MISSED']);
                    break;
                case 'METHOD':
                    $this->coverageSummary->FunctionsTested = intval($attributes['COVERED']);
                    $this->coverageSummary->FunctionsUntested = intval($attributes['MISSED']);
                    break;
            }
        } elseif ($parent == 'SOURCEFILE' && $name == 'COUNTER') {
            switch ($attributes['TYPE']) {
                case 'LINE':
                    $this->coverage->LocTested = intval($attributes['COVERED']);
                    $this->coverage->LocUntested = intval($attributes['MISSED']);
                    break;
                case 'COMPLEXITY':
                    $this->coverage->BranchesTested = intval($attributes['COVERED']);
                    $this->coverage->BranchesUntested = intval($attributes['MISSED']);
                    break;
                case 'METHOD':
                    $this->coverage->FunctionsTested = intval($attributes['COVERED']);
                    $this->coverage->FunctionsUntested = intval($attributes['MISSED']);
                    break;
            }
        }
    } // start element

    /** End element */
    public function endElement($parser, $name)
    {
        parent::endElement($parser, $name);
        if ($name == 'SITE') {
            $start_time = gmdate(FMT_DATETIME, $this->startTimeStamp);
            $end_time = gmdate(FMT_DATETIME, $this->endTimeStamp);

            $this->build->ProjectId = $this->projectId;
            $this->build->GetIdFromName($this->subProjectName);
            $this->build->RemoveIfDone();

            // If the build doesn't exist we add it
            if ($this->build->Id == 0) {
                $this->build->StartTime = $start_time;
                $this->build->EndTime = $end_time;
                $this->build->SubmitTime = gmdate(FMT_DATETIME);
                $this->build->SetSubProject($this->subProjectName);
                $this->build->InsertErrors = false;
                add_build($this->build);
            } else {
                // Otherwise make sure that it's up-to-date.
                $this->build->UpdateBuild($this->build->Id, -1, -1);
            }

            $GLOBALS['PHP_ERROR_BUILD_ID'] = $this->build->Id;
            $this->coverageSummary->BuildId = $this->build->Id;
            if ($this->coverageSummary->Exists()) {
                // Remove any previous coverage information.
                $this->coverageSummary->RemoveAll();
            }

            // Insert coverage summary
            $this->coverageSummary->Insert();
            $this->coverageSummary->ComputeDifference();
        } elseif ($name == 'SOURCEFILE') {
            $this->coverageSummary->AddCoverage($this->coverage);
        } elseif ($name == 'LABEL') {
            if (isset($this->coverage)) {
                $this->coverage->AddLabel($this->label);
            }
        }
    }

    /** Text function */
    public function text($parser, $data)
    {
        $element = $this->getElement();
        if ($element == 'LABEL') {
            $this->label->SetText($data);
        }
    }
}
