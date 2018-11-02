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

namespace CDash\Lib\Parsing\Xml;

use CDash\Model\Build;
use CDash\Model\BuildInformation;
use CDash\Model\Coverage;
use CDash\Model\CoverageFile;
use CDash\Model\CoverageSummary;
use CDash\Model\Label;
use CDash\Model\Project;
use CDash\Model\Site;
use CDash\Model\SiteInformation;
use CDash\Model\SubProject;

/**
 * Class CoverageParser
 * @package CDash\Lib\Parsing\Xml
 */
class CoverageParser extends AbstractXmlParser
{
    protected $coverage;
    protected $coverages;
    protected $coverageFile;
    protected $coverageSummaries;
    protected $label;
    protected $project;
    protected $hasSubProjects;

    /**
     * CoverageParser constructor.
     * @param $projectId
     */
    public function __construct($projectId)
    {
        parent::__construct($projectId);
        $this->build = $this->getInstance(Build::class);
        $this->site = $this->getInstance(Site::class);
        $this->coverages = [];
        $this->coverageSummaries = [];
        $this->project = $this->getInstance(Project::class);
        $this->project->Id = $this->projectId;
        $this->hasSubProjects = $this->project->GetNumberOfSubProjects() > 0;
    }

    /**
     * @param $parser
     * @param $name
     * @param $attributes
     * @return mixed|void
     */
    public function startElement($parser, $name, $attributes)
    {
        parent::startElement($parser, $name, $attributes);
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
        } elseif ($name == 'FILE') {
            $this->coverageFile = $this->getInstance(CoverageFile::class);
            $this->coverage = $this->getInstance(Coverage::class);
            $this->coverageFile->FullPath = trim($attributes['FULLPATH']);
            if ($attributes['COVERED'] == 1 || $attributes['COVERED'] == 'true') {
                $this->coverage->Covered = 1;
            } else {
                $this->coverage->Covered = 0;
            }
            $this->coverage->CoverageFile = $this->coverageFile;
        } elseif ($name == 'LABEL') {
            $this->label = $this->getInstance(Label::class);
        }
    } // start element

    /**
     * @param $parser
     * @param $name
     * @return mixed|void
     */
    public function endElement($parser, $name)
    {
        parent::endElement($parser, $name);
        if ($name == 'SITE') {
            $start_time = gmdate(FMT_DATETIME, $this->startTimeStamp);
            $end_time = gmdate(FMT_DATETIME, $this->endTimeStamp);

            $this->build->ProjectId = $this->projectId;
            $this->build->StartTime = $start_time;
            $this->build->EndTime = $end_time;
            $this->build->SubmitTime = gmdate(FMT_DATETIME);
            $this->build->SetSubProject($this->subProjectName);
            $this->build->GetIdFromName($this->subProjectName);
            $this->build->RemoveIfDone();

            // If the build doesn't exist we add it
            if ($this->build->Id == 0) {
                $this->build->InsertErrors = false;
                add_build($this->build, $this->scheduleId);
            } else {
                // Otherwise make sure that it's up-to-date.
                $this->build->UpdateBuild($this->build->Id, -1, -1);
            }

            foreach ($this->coverages as $coverageInfo) {
                $coverage = $coverageInfo[0];
                $coverageFile = $coverageInfo[1];
                $buildid = $this->build->Id;
                if ($this->hasSubProjects) {
                    // Make sure this file gets associated with the correct SubProject.
                    $subproject = SubProject::GetSubProjectFromPath(
                        $coverageFile->FullPath, $this->projectId);
                    if (!is_null($subproject)) {
                        // Find the sibling build that performed this SubProject.
                        $subprojectBuild = Build::GetSubProjectBuild(
                            $this->build->GetParentId(), $subproject->GetId());
                        if (is_null($subprojectBuild)) {
                            // Build doesn't exist yet, add it here.
                            $subprojectBuild = new Build();
                            $subprojectBuild->Name = $this->build->Name;
                            $subprojectBuild->ProjectId = $this->projectId;
                            $subprojectBuild->SiteId = $this->build->SiteId;
                            $subprojectBuild->SetParentId($this->build->GetParentId());
                            $subprojectBuild->SetStamp($this->build->GetStamp());
                            $subprojectBuild->SetSubProject($subproject->GetName());
                            $subprojectBuild->StartTime = $this->build->StartTime;
                            $subprojectBuild->EndTime = $this->build->EndTime;
                            $subprojectBuild->SubmitTime = gmdate(FMT_DATETIME);
                            add_build($subprojectBuild, 0);
                        }
                        $buildid = $subprojectBuild->Id;
                    }
                }
                if (!array_key_exists($buildid, $this->coverageSummaries)) {
                    $coverageSummary = $this->getInstance(CoverageSummary::class);
                    $coverageSummary->BuildId = $buildid;
                    $this->coverageSummaries[$buildid] = $coverageSummary;
                }
                $this->coverageSummaries[$buildid]->AddCoverage($coverage);
            }

            // Insert coverage summaries
            foreach ($this->coverageSummaries as $coverageSummary) {
                $GLOBALS['PHP_ERROR_BUILD_ID'] = $coverageSummary->BuildId;
                $coverageSummary->Insert(true);
                $coverageSummary->ComputeDifference();
            }
        } elseif ($name == 'FILE') {
            // Store this data.
            // We will insert it once we're done parsing the whole file.
            $this->coverages[] = [$this->coverage, $this->coverageFile];
        } elseif ($name == 'LABEL') {
            if (isset($this->coverage)) {
                $this->coverage->AddLabel($this->label);
            }
        }
    }

    /**
     * @param $parser
     * @param $data
     * @return mixed|void
     */
    public function text($parser, $data)
    {
        $parent = $this->getParent();
        $element = $this->getElement();

        if ($parent == 'COVERAGE') {
            switch ($element) {
                case 'STARTTIME':
                    $this->startTimeStamp = $data;
                    break;
                case 'ENDTIME':
                    $this->endTimeStamp = $data;
                    break;
            }
        } elseif ($parent == 'FILE') {
            switch ($element) {
                case 'LOCTESTED':
                    $this->coverage->LocTested .= $data;
                    break;
                case 'LOCUNTESTED':
                    $this->coverage->LocUntested .= $data;
                    break;
                case 'BRANCHESTESTED':
                    $this->coverage->BranchesTested .= $data;
                    break;
                case 'BRANCHESUNTESTED':
                    $this->coverage->BranchesUntested .= $data;
                    break;
                case 'FUNCTIONSTESTED':
                    $this->coverage->FunctionsTested .= $data;
                    break;
                case 'FUNCTIONSUNTESTED':
                    $this->coverage->FunctionsUntested .= $data;
                    break;
            }
        } elseif ($element == 'LABEL') {
            $this->label->SetText($data);
        }
    }
}
