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

use App\Http\Submission\Traits\UpdatesSiteInformation;
use App\Models\Site;
use App\Models\SiteInformation;
use App\Utils\SubmissionUtils;
use CDash\Model\Build;
use CDash\Model\Coverage;
use CDash\Model\CoverageFile;
use CDash\Model\CoverageSummary;
use CDash\Model\Label;
use CDash\Model\Project;
use CDash\Model\SubProject;

class CoverageHandler extends AbstractXmlHandler
{
    use UpdatesSiteInformation;

    private $StartTimeStamp;
    private $EndTimeStamp;

    private $Coverage;
    private $Coverages;
    private $CoverageFile;
    private $CoverageSummaries;
    private $Label;
    protected static ?string $schema_file = '/app/Validators/Schemas/Coverage.xsd';

    /** Constructor */
    public function __construct(Project $project)
    {
        parent::__construct($project);
        $this->Site = new Site();
        $this->Coverages = [];
        $this->CoverageSummaries = [];
        $this->HasSubProjects = $this->GetProject()->GetNumberOfSubProjects() > 0;
    }

    /** startElement */
    public function startElement($parser, $name, $attributes): void
    {
        parent::startElement($parser, $name, $attributes);
        if ($name == 'SITE') {
            $site_name = !empty($attributes['NAME']) ? $attributes['NAME'] : '(empty)';
            $this->Site = Site::firstOrCreate(['name' => $site_name], ['name' => $site_name]);

            $siteInformation = new SiteInformation();

            // Fill in the attribute
            foreach ($attributes as $key => $value) {
                $siteInformation->SetValue($key, $value);
                switch ($key) {
                    case 'OSNAME':
                        $this->Build->OSName = $value;
                        break;
                    case 'OSRELEASE':
                        $this->Build->OSRelease = $value;
                        break;
                    case 'OSVERSION':
                        $this->Build->OSVersion = $value;
                        break;
                    case 'OSPLATFORM':
                        $this->Build->OSPlatform = $value;
                        break;
                    case 'COMPILERNAME':
                        $this->Build->CompilerName = $value;
                        break;
                    case 'COMPILERVERSION':
                        $this->Build->CompilerVersion = $value;
                        break;
                }
            }

            $this->updateSiteInfoIfChanged($this->Site, $siteInformation);

            $this->Build->SiteId = $this->Site->id;
            $this->Build->Name = $attributes['BUILDNAME'];
            if (empty($this->Build->Name)) {
                $this->Build->Name = '(empty)';
            }
            $this->Build->SetStamp($attributes['BUILDSTAMP']);
            $this->Build->Generator = $attributes['GENERATOR'];
        } elseif ($name == 'FILE') {
            $this->CoverageFile = new CoverageFile();
            $this->Coverage = new Coverage();
            $this->CoverageFile->FullPath = trim($attributes['FULLPATH']);
            if ($attributes['COVERED'] == 1 || $attributes['COVERED'] == 'true') {
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
    public function endElement($parser, $name): void
    {
        parent::endElement($parser, $name);
        if ($name == 'SITE') {
            $start_time = gmdate(FMT_DATETIME, $this->StartTimeStamp);
            $end_time = gmdate(FMT_DATETIME, $this->EndTimeStamp);

            $this->Build->ProjectId = $this->GetProject()->Id;
            $this->Build->StartTime = $start_time;
            $this->Build->EndTime = $end_time;
            $this->Build->SubmitTime = gmdate(FMT_DATETIME);
            $this->Build->SetSubProject($this->SubProjectName);
            $this->Build->GetIdFromName($this->SubProjectName);
            $this->Build->RemoveIfDone();

            // If the build doesn't exist we add it
            if ($this->Build->Id == 0) {
                $this->Build->InsertErrors = false;
                SubmissionUtils::add_build($this->Build);
            } else {
                // Otherwise make sure that it's up-to-date.
                $this->Build->UpdateBuild($this->Build->Id, -1, -1);
            }

            foreach ($this->Coverages as $coverageInfo) {
                $coverage = $coverageInfo[0];
                $coverageFile = $coverageInfo[1];
                $buildid = $this->Build->Id;
                if ($this->HasSubProjects) {
                    // Make sure this file gets associated with the correct SubProject.
                    $subproject = SubProject::GetSubProjectFromPath(
                        $coverageFile->FullPath, $this->GetProject()->Id);
                    if (!is_null($subproject)) {
                        // Find the sibling build that performed this SubProject.
                        $subprojectBuild = Build::GetSubProjectBuild(
                            $this->Build->GetParentId(), $subproject->GetId());
                        if (is_null($subprojectBuild)) {
                            // Build doesn't exist yet, add it here.
                            $subprojectBuild = new Build();
                            $subprojectBuild->Name = $this->Build->Name;
                            $subprojectBuild->ProjectId = $this->GetProject()->Id;
                            $subprojectBuild->SiteId = $this->Build->SiteId;
                            $subprojectBuild->SetParentId($this->Build->GetParentId());
                            $subprojectBuild->SetStamp($this->Build->GetStamp());
                            $subprojectBuild->SetSubProject($subproject->GetName());
                            $subprojectBuild->StartTime = $this->Build->StartTime;
                            $subprojectBuild->EndTime = $this->Build->EndTime;
                            $subprojectBuild->SubmitTime = gmdate(FMT_DATETIME);
                            SubmissionUtils::add_build($subprojectBuild);
                        }
                        $buildid = $subprojectBuild->Id;
                    }
                }
                if (!array_key_exists($buildid, $this->CoverageSummaries)) {
                    $coverageSummary = new CoverageSummary();
                    $coverageSummary->BuildId = $buildid;
                    $this->CoverageSummaries[$buildid] = $coverageSummary;
                }
                $this->CoverageSummaries[$buildid]->AddCoverage($coverage);
            }

            // Insert coverage summaries
            foreach ($this->CoverageSummaries as $coverageSummary) {
                $GLOBALS['PHP_ERROR_BUILD_ID'] = $coverageSummary->BuildId;
                $coverageSummary->Insert(true);
                $coverageSummary->ComputeDifference();
            }
        } elseif ($name == 'FILE') {
            // Store this data.
            // We will insert it once we're done parsing the whole file.
            $this->Coverages[] = [$this->Coverage, $this->CoverageFile];
        } elseif ($name == 'LABEL') {
            if (isset($this->Coverage)) {
                $this->Coverage->AddLabel($this->Label);
            }
        }
    }

    /** Text function */
    public function text($parser, $data)
    {
        $parent = $this->getParent();
        $element = $this->getElement();

        if ($parent == 'COVERAGE') {
            switch ($element) {
                case 'STARTTIME':
                    $this->StartTimeStamp = $data;
                    break;
                case 'ENDTIME':
                    $this->EndTimeStamp = $data;
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
    }
}
