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
use CDash\Model\DynamicAnalysis;
use CDash\Model\DynamicAnalysisDefect;
use CDash\Model\DynamicAnalysisSummary;
use CDash\Model\Label;
use CDash\Model\Site;
use CDash\Model\SiteInformation;

/**
 * Class DynamicAnalysisParser
 * @package CDash\Lib\Parsing\Xml
 */
class DynamicAnalysisParser extends AbstractXmlParser
{
    private $checker;

    private $dynamicAnalysis;
    private $dynamicAnalysisDefect;
    private $dynamicAnalysisSummaries;
    private $label;

    private $builds;
    private $buildInformation;

    // Map SubProjects to Labels
    private $subProjects;
    private $testSubProjectName;

    private $buildName;
    private $buildStamp;
    private $generator;
    private $pullRequest;


    /**
     * DynamicAnalysisParser constructor.
     * @param $projectId
     */
    public function __construct($projectId)
    {
        parent::__construct($projectId);
        $this->builds = [];
        $this->subProjects = [];
        $this->site = $this->getInstance(Site::class);
        $this->dynamicAnalysisSummaries = [];
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
            $this->buildInformation = $this->getInstance(BuildInformation::class);

            // Fill in the attribute
            foreach ($attributes as $key => $value) {
                if ($key === 'BUILDNAME') {
                    $this->buildName = $value;
                } elseif ($key === 'BUILDSTAMP') {
                    $this->buildStamp = $value;
                } elseif ($key === 'GENERATOR') {
                    $this->generator = $value;
                } elseif ($key == 'CHANGEID') {
                    $this->pullRequest = $value;
                } else {
                    $siteInformation->SetValue($key, $value);
                    $this->buildInformation->SetValue($key, $value);
                }
            }

            if (empty($this->buildName)) {
                $this->buildName = '(empty)';
            }
            $this->site->SetInformation($siteInformation);
        } elseif ($name == 'SUBPROJECT') {
            $this->subProjectName = $attributes['NAME'];
            if (!array_key_exists($this->subProjectName, $this->subProjects)) {
                $this->subProjects[$this->subProjectName] = [];
            }
        } elseif ($name == 'DYNAMICANALYSIS') {
            $this->checker = $attributes['CHECKER'];
            if (empty($this->dynamicAnalysisSummaries)) {
                $summary = $this->getInstance(DynamicAnalysisSummary::class);
                $summary->Empty = true;
                $summary->Checker = $this->checker;
                $this->dynamicAnalysisSummaries[$this->subProjectName] = $summary;
            } else {
                foreach ($this->dynamicAnalysisSummaries as $subprojectName => $summary) {
                    $summary->Checker = $this->checker;
                }
            }
        } elseif ($name == 'TEST' && isset($attributes['STATUS'])) {
            $this->dynamicAnalysis = $this->getInstance(DynamicAnalysis::class);
            $this->dynamicAnalysis->Checker = $this->checker;
            $this->dynamicAnalysis->Status = $attributes['STATUS'];
            $this->testSubProjectName = "";
        } elseif ($name == 'DEFECT') {
            $this->dynamicAnalysisDefect = $this->getInstance(DynamicAnalysisDefect::class);
            $this->dynamicAnalysisDefect->Type = $attributes['TYPE'];
        } elseif ($name == 'LABEL') {
            $this->label = $this->getInstance(Label::class);
        } elseif ($name == 'LOG') {
            $this->dynamicAnalysis->LogCompression = isset($attributes['COMPRESSION']) ? $attributes['COMPRESSION'] : '';
            $this->dynamicAnalysis->LogEncoding = isset($attributes['ENCODING']) ? $attributes['ENCODING'] : '';
        }
    }

    /**
     * @param $parser
     * @param $name
     * @return mixed|void
     */
    public function endElement($parser, $name)
    {
        $parent = $this->getParent(); // should be before endElement
        parent::endElement($parser, $name);

        if ($name == 'STARTTESTTIME' && $parent == 'DYNAMICANALYSIS') {
            if (empty($this->subProjects)) {
                // Not a SubProject build.
                $this->createBuild('');
            } else {
                // Make sure we have a build for each SubProject.
                foreach ($this->subProjects as $subproject => $labels) {
                    $this->createBuild($subproject);
                }
            }
        } elseif ($name == 'TEST' && $parent == 'DYNAMICANALYSIS') {
            $build = $this->builds[$this->subProjectName];
            $GLOBALS['PHP_ERROR_BUILD_ID'] = $build->Id;
            $this->dynamicAnalysisSummaries[$this->subProjectName]->Empty = false;
            foreach ($this->dynamicAnalysis->GetDefects() as $defect) {
                $this->dynamicAnalysisSummaries[$this->subProjectName]->AddDefects(
                    $defect->Value);
            }
            $this->dynamicAnalysis->BuildId = $build->Id;
            $this->dynamicAnalysis->Insert();
        } elseif ($name == 'DEFECT') {
            $this->dynamicAnalysis->AddDefect($this->dynamicAnalysisDefect);
            unset($this->dynamicAnalysisDefect);
        } elseif ($name == 'LABEL') {
            if (!empty($this->testSubProjectName)) {
                $this->subProjectName = $this->testSubProjectName;
            } elseif (isset($this->dynamicAnalysis)) {
                $this->dynamicAnalysis->AddLabel($this->label);
            }
        } elseif ($name == 'DYNAMICANALYSIS') {
            foreach ($this->builds as $subprojectName => $build) {
                // Update this build's end time if necessary.
                $build->EndTime = gmdate(FMT_DATETIME, $this->endTimeStamp);
                $build->UpdateBuild($build->Id, -1, -1);

                // If everything is perfect CTest doesn't send any <test>
                // But we still want a line showing the current dynamic analysis
                if ($this->dynamicAnalysisSummaries[$subprojectName]->Empty) {
                    $this->dynamicAnalysis = $this->getInstance(DynamicAnalysis::class);
                    $this->dynamicAnalysis->BuildId = $build->Id;
                    $this->dynamicAnalysis->Status = 'passed';
                    $this->dynamicAnalysis->Checker = $this->checker;
                    $this->dynamicAnalysis->Insert();
                }
                $this->dynamicAnalysisSummaries[$subprojectName]->Insert();

                // If this is a child build append these defects to the parent's
                // summary.
                $parentid = $build->LookupParentBuildId();
                if ($parentid > 0) {
                    $this->dynamicAnalysisSummaries[$subprojectName]->BuildId = $parentid;
                    $this->dynamicAnalysisSummaries[$subprojectName]->Insert(true);
                }
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

        if ($parent == 'DYNAMICANALYSIS') {
            switch ($element) {
                case 'STARTTESTTIME':
                    $this->startTimeStamp = $data;
                    break;
                case 'ENDTESTTIME':
                    $this->endTimeStamp = $data;
                    break;
            }
        } elseif ($parent == 'TEST') {
            switch ($element) {
                case 'NAME':
                    $this->dynamicAnalysis->Name .= $data;
                    break;
                case 'PATH':
                    $this->dynamicAnalysis->Path .= $data;
                    break;
                case 'FULLCOMMANDLINE':
                    $this->dynamicAnalysis->FullCommandLine .= $data;
                    break;
                case 'LOG':
                    $this->dynamicAnalysis->Log .= $data;
                    break;
            }
        } elseif ($parent == 'RESULTS') {
            if ($element == 'DEFECT') {
                $this->dynamicAnalysisDefect->Value .= $data;
            }
        } elseif ($parent == 'SUBPROJECT' && $element == 'LABEL') {
            $this->subProjects[$this->subProjectName][] =  $data;
        } elseif ($element == 'LABEL') {
            // Check if this label belongs to a SubProject.
            foreach ($this->subProjects as $subproject => $labels) {
                if (in_array($data, $labels)) {
                    $this->testSubProjectName = $subproject;
                    break;
                }
            }
            if (empty($this->testSubProjectName)) {
                $this->label->SetText($data);
            }
        }
    }

    /**
     * @return string
     */
    public function getBuildStamp()
    {
        return $this->buildStamp;
    }

    /**
     * @return string
     */
    public function getBuildName()
    {
        return $this->buildName;
    }

    /**
     * @param $subprojectName
     */
    private function createBuild($subprojectName)
    {
        $build = $this->getInstance(Build::class);

        $build->SiteId = $this->site->Id;
        $build->Name = $this->buildName;

        $build->SetStamp($this->buildStamp);
        $build->Generator = $this->generator;
        $build->Information = $this->buildInformation;

        $start_time = gmdate(FMT_DATETIME, $this->startTimeStamp);

        $build->ProjectId = $this->projectId;
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
            add_build($build, $this->scheduleId);
        } else {
            // Otherwise make sure that the build is up-to-date.
            $build->UpdateBuild($build->Id, -1, -1);

            // Remove any previous analysis.
            $da = $this->getInstance(DynamicAnalysis::class);
            $da->BuildId = $build->Id;
            $da->RemoveAll();
        }

        $this->builds[$subprojectName] = $build;

        // Initialize a dynamic analysis summary for this build.
        $summary = $this->getInstance(DynamicAnalysisSummary::class);
        $summary->Empty = true;
        $summary->BuildId = $build->Id;
        $summary->Checker = $this->checker;
        $this->dynamicAnalysisSummaries[$subprojectName] = $summary;
    }

    /**
     * @return Build[]
     */
    public function getBuilds()
    {
        return array_values($this->builds);
    }

    /**
     * @return array
     */
    public function getActionableBuilds()
    {
        return $this->builds;
    }
}
