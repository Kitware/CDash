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
require_once 'xml_handlers/actionable_build_interface.php';

use CDash\Collection\BuildCollection;
use CDash\Model\Build;
use CDash\Model\Label;
use CDash\Model\Site;
use CDash\Model\DynamicAnalysis;
use CDash\Model\DynamicAnalysisSummary;
use CDash\Model\DynamicAnalysisDefect;
use CDash\Model\SiteInformation;
use CDash\Model\BuildInformation;

class DynamicAnalysisHandler extends AbstractHandler implements ActionableBuildInterface
{
    private $StartTimeStamp;
    private $EndTimeStamp;
    private $Checker;

    private $DynamicAnalysis;
    private $DynamicAnalysisDefect;
    private $DynamicAnalysisSummaries;
    private $Label;

    private $Builds;
    private $BuildInformation;

    // Map SubProjects to Labels
    private $SubProjects;
    private $TestSubProjectName;

    /** Constructor */
    public function __construct($projectID, $scheduleID)
    {
        parent::__construct($projectID, $scheduleID);
        $this->Builds = [];
        $this->SubProjects = [];
        $this->DynamicAnalysisSummaries = [];
    }

    /** Start element */
    public function startElement($parser, $name, $attributes)
    {
        parent::startElement($parser, $name, $attributes);
        $factory = $this->getModelFactory();

        if ($name == 'SITE') {
            $this->Site = $factory->create(Site::class);
            $this->Site->Name = $attributes['NAME'];
            if (empty($this->Site->Name)) {
                $this->Site->Name = '(empty)';
            }
            $this->Site->Insert();

            $siteInformation = $factory->create(SiteInformation::class);
            $this->BuildInformation = $factory->create(BuildInformation::class);

            // Fill in the attribute
            foreach ($attributes as $key => $value) {
                if ($key === 'BUILDNAME') {
                    $this->BuildName = $value;
                } elseif ($key === 'BUILDSTAMP') {
                    $this->BuildStamp = $value;
                } elseif ($key === 'GENERATOR') {
                    $this->Generator = $value;
                } elseif ($key == 'CHANGEID') {
                    $this->PullRequest = $value;
                } else {
                    $siteInformation->SetValue($key, $value);
                    $this->BuildInformation->SetValue($key, $value);
                }
            }

            if (empty($this->BuildName)) {
                $this->BuildName = '(empty)';
            }
            $this->Site->SetInformation($siteInformation);
        } elseif ($name == 'SUBPROJECT') {
            $this->SubProjectName = $attributes['NAME'];
            if (!array_key_exists($this->SubProjectName, $this->SubProjects)) {
                $this->SubProjects[$this->SubProjectName] = [];
            }
        } elseif ($name == 'DYNAMICANALYSIS') {
            $this->Checker = $attributes['CHECKER'];
            if (empty($this->DynamicAnalysisSummaries)) {
                $summary = $factory->create(DynamicAnalysisSummary::class);
                $summary->Empty = true;
                $summary->Checker = $this->Checker;
                $this->DynamicAnalysisSummaries[$this->SubProjectName] = $summary;
            } else {
                foreach ($this->DynamicAnalysisSummaries as $subprojectName => $summary) {
                    $summary->Checker = $this->Checker;
                }
            }
        } elseif ($name == 'TEST' && isset($attributes['STATUS'])) {
            $this->DynamicAnalysis = $factory->create(DynamicAnalysis::class);
            $this->DynamicAnalysis->Checker = $this->Checker;
            $this->DynamicAnalysis->Status = $attributes['STATUS'];
            $this->TestSubProjectName = "";
        } elseif ($name == 'DEFECT') {
            $this->DynamicAnalysisDefect = $factory->create(DynamicAnalysisDefect::class);
            $this->DynamicAnalysisDefect->Type = $attributes['TYPE'];
        } elseif ($name == 'LABEL') {
            $this->Label = $factory->create(Label::class);
        } elseif ($name == 'LOG') {
            $this->DynamicAnalysis->LogCompression = isset($attributes['COMPRESSION']) ? $attributes['COMPRESSION'] : '';
            $this->DynamicAnalysis->LogEncoding = isset($attributes['ENCODING']) ? $attributes['ENCODING'] : '';
        }
    }

    /** Function endElement */
    public function endElement($parser, $name)
    {
        $parent = $this->getParent(); // should be before endElement
        parent::endElement($parser, $name);
        $factory = $this->getModelFactory();
        if ($name == 'STARTTESTTIME' && $parent == 'DYNAMICANALYSIS') {
            if (empty($this->SubProjects)) {
                // Not a SubProject build.
                $this->createBuild('');
            } else {
                // Make sure we have a build for each SubProject.
                foreach ($this->SubProjects as $subproject => $labels) {
                    $this->createBuild($subproject);
                }
            }
        } elseif ($name == 'TEST' && $parent == 'DYNAMICANALYSIS') {
            /** @var Build $build */
            $build = $this->Builds[$this->SubProjectName];
            $GLOBALS['PHP_ERROR_BUILD_ID'] = $build->Id;
            $this->DynamicAnalysisSummaries[$this->SubProjectName]->Empty = false;
            foreach ($this->DynamicAnalysis->GetDefects() as $defect) {
                $this->DynamicAnalysisSummaries[$this->SubProjectName]->AddDefects(
                    $defect->Value);
            }
            $this->DynamicAnalysis->BuildId = $build->Id;
            $this->DynamicAnalysis->Insert();
            $analysis = clone $this->DynamicAnalysis;
            $build->AddDynamicAnalysis($analysis);
        } elseif ($name == 'DEFECT') {
            $this->DynamicAnalysis->AddDefect($this->DynamicAnalysisDefect);
            unset($this->DynamicAnalysisDefect);
        } elseif ($name == 'LABEL') {
            if (!empty($this->TestSubProjectName)) {
                $this->SubProjectName = $this->TestSubProjectName;
            } elseif (isset($this->DynamicAnalysis)) {
                $this->DynamicAnalysis->AddLabel($this->Label);
            }
        } elseif ($name == 'DYNAMICANALYSIS') {
            foreach ($this->Builds as $subprojectName => $build) {
                // Update this build's end time if necessary.
                $build->EndTime = gmdate(FMT_DATETIME, $this->EndTimeStamp);
                $build->UpdateBuild($build->Id, -1, -1);

                // If everything is perfect CTest doesn't send any <test>
                // But we still want a line showing the current dynamic analysis
                if ($this->DynamicAnalysisSummaries[$subprojectName]->Empty) {
                    $this->DynamicAnalysis = $factory->create(DynamicAnalysis::class);
                    $this->DynamicAnalysis->BuildId = $build->Id;
                    $this->DynamicAnalysis->Status = 'passed';
                    $this->DynamicAnalysis->Checker = $this->Checker;
                    $this->DynamicAnalysis->Insert();
                }
                $this->DynamicAnalysisSummaries[$subprojectName]->Insert();

                // If this is a child build append these defects to the parent's
                // summary.
                $parentid = $build->LookupParentBuildId();
                if ($parentid > 0) {
                    $this->DynamicAnalysisSummaries[$subprojectName]->BuildId = $parentid;
                    $this->DynamicAnalysisSummaries[$subprojectName]->Insert(true);
                }
            }
        }
    }

    /** Function Text */
    public function text($parser, $data)
    {
        $parent = $this->getParent();
        $element = $this->getElement();

        if ($parent == 'DYNAMICANALYSIS') {
            switch ($element) {
                case 'STARTTESTTIME':
                    $this->StartTimeStamp = $data;
                    break;
                case 'ENDTESTTIME':
                    $this->EndTimeStamp = $data;
                    break;
            }
        } elseif ($parent == 'TEST') {
            switch ($element) {
                case 'NAME':
                    $this->DynamicAnalysis->Name .= $data;
                    break;
                case 'PATH':
                    $this->DynamicAnalysis->Path .= $data;
                    break;
                case 'FULLCOMMANDLINE':
                    $this->DynamicAnalysis->FullCommandLine .= $data;
                    break;
                case 'LOG':
                    $this->DynamicAnalysis->Log .= $data;
                    break;
            }
        } elseif ($parent == 'RESULTS') {
            if ($element == 'DEFECT') {
                $this->DynamicAnalysisDefect->Value .= $data;
            }
        } elseif ($parent == 'SUBPROJECT' && $element == 'LABEL') {
            $this->SubProjects[$this->SubProjectName][] =  $data;
        } elseif ($element == 'LABEL') {
            // Check if this label belongs to a SubProject.
            foreach ($this->SubProjects as $subproject => $labels) {
                if (in_array($data, $labels)) {
                    $this->TestSubProjectName = $subproject;
                    break;
                }
            }
            if (empty($this->TestSubProjectName)) {
                $this->Label->SetText($data);
            }
        }
    }

    public function getBuildStamp()
    {
        return $this->BuildStamp;
    }

    public function getBuildName()
    {
        return $this->BuildName;
    }

    private function createBuild($subprojectName)
    {
        $factory = $this->getModelFactory();
        $build = $factory->create(Build::class);

        $build->SiteId = $this->Site->Id;
        $build->Name = $this->BuildName;

        $build->SetStamp($this->BuildStamp);
        $build->Generator = $this->Generator;
        $build->Information = $this->BuildInformation;

        $start_time = gmdate(FMT_DATETIME, $this->StartTimeStamp);

        $build->ProjectId = $this->projectid;
        $build->StartTime = $start_time;
        // EndTimeStamp hasn't been parsed yet.  We update this value later.
        $build->EndTime = $start_time;
        $build->SubmitTime = gmdate(FMT_DATETIME);
        $build->SetSubProject($subprojectName);

        $build->GetIdFromName($subprojectName);
        $build->RemoveIfDone();

        // If the build doesn't exist we add it
        if ($build->Id == 0) {
            $build->InsertErrors = false;
            add_build($build, $this->scheduleid);
        } else {
            // Otherwise make sure that the build is up-to-date.
            $build->UpdateBuild($build->Id, -1, -1);

            // Remove any previous analysis.
            $DA = $factory->create(DynamicAnalysis::class);
            $DA->BuildId = $build->Id;
            $DA->RemoveAll();
        }

        $this->Builds[$subprojectName] = $build;

        // Initialize a dynamic analysis summary for this build.
        $summary = $factory->create(DynamicAnalysisSummary::class);
        $summary->Empty = true;
        $summary->BuildId = $build->Id;
        $summary->Checker = $this->Checker;
        $this->DynamicAnalysisSummaries[$subprojectName] = $summary;
    }

    /**
     * @return Build[]
     */
    public function getBuilds()
    {
        return array_values($this->Builds);
    }
    public function getActionableBuilds()
    {
        return $this->Builds;
    }

    /**
     * @return BuildCollection
     * @throws \DI\DependencyException
     * @throws \DI\NotFoundException
     * TODO: consider refactoring into abstract_handler asap
     */
    public function GetBuildCollection()
    {
        $factory = $this->getModelFactory();
        /** @var BuildCollection $collection */
        $collection = $factory->create(BuildCollection::class);
        foreach ($this->Builds as $key => $build) {
            if (is_numeric($key) || empty($key)) {
                $collection->add($build);
            } else {
                $collection->addItem($build, $key);
            }
        }
        return $collection;
    }

    public function getProjectId()
    {
        // TODO: Implement getProjectId() method.
    }
}
